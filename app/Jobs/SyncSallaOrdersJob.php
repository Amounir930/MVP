<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncSallaOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    /**
     * Creates a new job instance targeting the specific tenant context.
     *
     * @param  \App\Models\Tenant  $tenant
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Executes the job to fetch and synchronize orders.
     *
     * @return void
     */
    public function handle(): void
    {
        // Bind the tenant ID to the container to enforce database scoping
        App::bind('current_tenant_id', fn () => $this->tenant->id);

        $config = $this->tenant->sallaConfig;
        if (empty($config)) {
            Log::error('Salla configuration missing for tenant order synchronization', [
                'tenant_id' => $this->tenant->id,
            ]);
            return;
        }

        $url = 'https://api.salla.dev/admin/v2/orders';
        $fetchedOrderIds = [];
        $syncSuccess = true;

        $whatsappConfig = \App\Models\WhatsappConfig::where('tenant_id', $this->tenant->id)->first();
        $isWhatsappConnected = $whatsappConfig && $whatsappConfig->status === 'connected';
        $isScannerEnabled = $whatsappConfig && (!isset($whatsappConfig->custom_questions['enable_salla_scanner']) || $whatsappConfig->custom_questions['enable_salla_scanner'] !== false);
        $scannerLookbackHours = $whatsappConfig ? (int) ($whatsappConfig->custom_questions['salla_scanner_lookback_hours'] ?? 24) : 24;

        try {
            while ($url) {
                $response = Http::withToken($config->access_token)->get($url);

                if ($response->failed()) {
                    Log::error('Failed to fetch orders page from Salla API', [
                        'tenant_id' => $this->tenant->id,
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                    $syncSuccess = false;
                    break;
                }

                $body = $response->json();
                $orders = $body['data'] ?? [];

                foreach ($orders as $orderData) {
                    $sallaOrderId = (string) $orderData['id'];
                    $fetchedOrderIds[] = $sallaOrderId;

                    $customerData = $orderData['customer'] ?? null;
                    if (empty($customerData)) {
                        continue;
                    }

                    // Synchronizing customer contact first to fulfill relational mapping
                    $customerName = trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? ''));
                    if (empty($customerName)) {
                        $customerName = 'Customer ' . ($customerData['id'] ?? 'Unknown');
                    }

                    $customer = Customer::updateOrCreate(
                        ['salla_customer_id' => (string) $customerData['id']],
                        [
                            'name' => $customerName,
                            'phone' => (string) ($customerData['mobile'] ?? ''),
                            'email' => $customerData['email'] ?? null,
                            'avatar_url' => $customerData['avatar'] ?? null,
                        ]
                    );

                    // Syncing order with relational association to customer
                    $statusName = $orderData['status']['name'] ?? '';
                    $deliveredAt = null;
                    if ($statusName === 'delivered' || $statusName === 'completed') {
                        $orderDateStr = $orderData['updated_at'] ?? $orderData['created_at'] ?? ($orderData['date']['date'] ?? null);
                        $deliveredAt = $orderDateStr ? \Carbon\Carbon::parse($orderDateStr) : now();
                    }

                    $localOrder = Order::where('salla_order_id', $sallaOrderId)->first();
                    $isAlreadyScheduled = false;
                    if ($localOrder) {
                        $isAlreadyScheduled = $localOrder->rating_message_scheduled 
                            || \App\Models\WhatsappChatSession::where('order_id', $localOrder->id)->exists()
                            || \App\Models\Review::where('order_id', $localOrder->id)->exists();
                    }

                    $order = Order::updateOrCreate(
                        ['salla_order_id' => $sallaOrderId],
                        [
                            'customer_id' => $customer->id,
                            'invoice_number' => (string) ($orderData['reference_id'] ?? ''),
                            'total' => (float) ($orderData['amounts']['total']['amount'] ?? 0.0),
                            'status' => $statusName,
                            'delivered_at' => $deliveredAt,
                        ]
                    );

                    // Sync order products
                    $productIds = [];
                    foreach ($orderData['items'] ?? [] as $item) {
                        $productData = $item['product'] ?? null;
                        if ($productData && !empty($productData['id'])) {
                            $sallaProductId = (string) $productData['id'];
                            $localProduct = \App\Models\Product::updateOrCreate(
                                ['salla_product_id' => $sallaProductId],
                                [
                                    'name' => $productData['name'] ?? '',
                                    'image_url' => $productData['image'] ?? null,
                                    'product_url' => $productData['urls']['customer'] ?? null,
                                ]
                            );
                            $productIds[] = $localProduct->id;
                        }
                    }
                    $order->products()->sync($productIds);

                    if ($isWhatsappConnected && $isScannerEnabled && $deliveredAt && $deliveredAt->greaterThanOrEqualTo(now()->subHours($scannerLookbackHours)) && !$isAlreadyScheduled) {
                        $order->rating_message_scheduled = true;
                        $order->save();

                        $delayHours = (int) ($whatsappConfig->delay_hours ?? 24);
                        $targetSendTime = $deliveredAt->copy()->addHours($delayHours);

                        $job = new \App\Jobs\SendWhatsAppRatingMessageJob($order);
                        if ($targetSendTime->isFuture()) {
                            $delaySeconds = now()->diffInSeconds($targetSendTime);
                            $job->delay($delaySeconds);
                            Log::info('Scanner scheduled WhatsApp rating message with remaining delay seconds.', [
                                'tenant_id' => $this->tenant->id,
                                'order_id' => $order->id,
                                'delay_seconds' => $delaySeconds,
                            ]);
                        } else {
                            Log::info('Scanner dispatching WhatsApp rating message immediately (target time is in the past).', [
                                'tenant_id' => $this->tenant->id,
                                'order_id' => $order->id,
                            ]);
                        }
                        dispatch($job);
                    }
                }

                // Retrieve next page link to continue paginated synchronization
                $url = $body['pagination']['links']['next'] ?? null;

                // Throttling requests slightly to respect API rate boundaries
                if ($url) {
                    usleep(500000); // 0.5 seconds delay
                }
            }

            if ($syncSuccess) {
                // Delete local orders that are no longer present on Salla store
                Order::whereNotIn('salla_order_id', $fetchedOrderIds)->delete();
            }

            Log::info('Successfully completed order synchronization from Salla', [
                'tenant_id' => $this->tenant->id,
            ]);
        } catch (\Exception $exception) {
            Log::error('Exception triggered during Salla order synchronization', [
                'tenant_id' => $this->tenant->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
