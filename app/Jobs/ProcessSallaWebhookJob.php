<?php

namespace App\Jobs;

use App\Events\OrderDelivered;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class ProcessSallaWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $tenantId;
    protected array $payload;

    /**
     * Creates a new webhook processor job instance.
     *
     * @param  string  $tenantId
     * @param  array  $payload
     */
    public function __construct(string $tenantId, array $payload)
    {
        $this->tenantId = $tenantId;
        $this->payload = $payload;
    }

    /**
     * Executes the job to parse and trigger internal actions from Salla webhooks.
     *
     * @return void
     */
    public function handle(): void
    {
        // Enforce the tenant scope to isolate database operations
        App::bind('current_tenant_id', fn () => $this->tenantId);

        $event = $this->payload['event'] ?? null;
        Log::info('Processing Salla webhook event in background', [
            'tenant_id' => $this->tenantId,
            'event' => $event,
        ]);

        if ($event === 'order.status.updated') {
            $orderData = $this->payload['data'] ?? [];
            $statusName = $orderData['status']['name'] ?? '';

            // Listen specifically for delivered or completed orders
            if ($statusName === 'delivered' || $statusName === 'completed') {
                $customerData = $orderData['customer'] ?? null;

                if ($customerData) {
                    $customerName = trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? ''));
                    if (empty($customerName)) {
                        $customerName = 'Customer ' . ($customerData['id'] ?? 'Unknown');
                    }

                    // Ensure customer info is cached locally
                    $customer = Customer::updateOrCreate(
                        ['salla_customer_id' => (string) $customerData['id']],
                        [
                            'name' => $customerName,
                            'phone' => (string) ($customerData['mobile'] ?? ''),
                            'email' => $customerData['email'] ?? null,
                            'avatar_url' => $customerData['avatar'] ?? null,
                        ]
                    );

                    // Sync the order details
                    $order = Order::updateOrCreate(
                        ['salla_order_id' => (string) $orderData['id']],
                        [
                            'customer_id' => $customer->id,
                            'invoice_number' => (string) ($orderData['reference_id'] ?? ''),
                            'total' => (float) ($orderData['amounts']['total']['amount'] ?? 0.0),
                            'status' => $statusName,
                            'delivered_at' => now(),
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

                    // Dispatch modular event for WhatsApp feedback chatbot scheduling
                    event(new OrderDelivered($order));

                    Log::info('Salla order delivered webhook processed successfully', [
                        'tenant_id' => $this->tenantId,
                        'order_id' => $order->id,
                    ]);
                }
            }
        } elseif ($event === 'product.created' || $event === 'product.updated') {
            $productData = $this->payload['data'] ?? [];
            if (!empty($productData['id'])) {
                \App\Models\Product::updateOrCreate(
                    ['salla_product_id' => (string) $productData['id']],
                    [
                        'name' => $productData['name'] ?? '',
                        'image_url' => $productData['image'] ?? null,
                        'product_url' => $productData['urls']['customer'] ?? null,
                    ]
                );
                Log::info("Salla product {$event} webhook processed successfully", [
                    'tenant_id' => $this->tenantId,
                    'product_id' => $productData['id'],
                ]);
            }
        } elseif ($event === 'product.deleted') {
            $productData = $this->payload['data'] ?? [];
            if (!empty($productData['id'])) {
                \App\Models\Product::where('salla_product_id', (string) $productData['id'])->delete();
                Log::info('Salla product deleted webhook processed successfully', [
                    'tenant_id' => $this->tenantId,
                    'product_id' => $productData['id'],
                ]);
            }
        } elseif ($event === 'app.uninstalled') {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($tenant) {
                    $tenant->sallaConfig()->delete();
                    $tenant->products()->delete();
                    $tenant->orders()->delete();
                    $tenant->customers()->delete();
                });
                Log::info('Salla app.uninstalled webhook processed and all data deleted successfully', [
                    'tenant_id' => $this->tenantId,
                ]);
            }
        }
    }
}
