<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSallaWebhookJob;
use App\Models\WhatsappConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SandboxController extends Controller
{
    /**
     * Simulates a Salla webhook order delivered event.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function simulateOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_phone' => 'required|string|max:20',
            'order_reference' => 'required|string|max:50',
            'order_total' => 'required|numeric|min:0',
            'force_immediate' => 'nullable|boolean',
        ]);

        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new \RuntimeException('Tenant context missing.');
            }

            // Construct the mock Salla order.status.updated webhook payload
            $names = explode(' ', trim($validated['customer_name']), 2);
            $firstName = $names[0] ?? '';
            $lastName = $names[1] ?? '';

            $mockSallaCustomerId = 'sim_cust_' . rand(1000, 9999);
            $mockSallaOrderId = 'sim_ord_' . rand(1000, 9999);

            $product = \App\Models\Product::first();
            $items = [];
            if ($product) {
                $items[] = [
                    'id' => 'sim_item_' . rand(1000, 9999),
                    'product' => [
                        'id' => $product->salla_product_id,
                        'name' => $product->name,
                        'image' => $product->image_url,
                        'urls' => ['customer' => $product->product_url],
                    ]
                ];
            } else {
                $items[] = [
                    'id' => 'sim_item_' . rand(1000, 9999),
                    'product' => [
                        'id' => 'sim_prod_123',
                        'name' => 'منتج تجريبي (Demo Product)',
                        'image' => 'https://via.placeholder.com/150',
                        'urls' => ['customer' => '#'],
                    ]
                ];
            }

            $payload = [
                'event' => 'order.status.updated',
                'merchant' => 11223344, // simulated merchant id
                'data' => [
                    'id' => $mockSallaOrderId,
                    'reference_id' => $validated['order_reference'],
                    'status' => [
                        'name' => 'delivered',
                    ],
                    'amounts' => [
                        'total' => [
                            'amount' => (float) $validated['order_total'],
                            'currency' => 'SAR',
                        ]
                    ],
                    'customer' => [
                        'id' => $mockSallaCustomerId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'mobile' => $validated['customer_phone'],
                        'email' => 'test-customer@example.com',
                    ],
                    'items' => $items
                ]
            ];

            // If force_immediate is checked, we temporarily override delay_hours in DB
            $whatsappConfig = WhatsappConfig::where('tenant_id', $tenant->id)->first();
            $originalDelay = null;
            if ($whatsappConfig && $validated['force_immediate']) {
                $originalDelay = $whatsappConfig->delay_hours;
                $whatsappConfig->update(['delay_hours' => 0]);
            }

            // Execute the webhook processing job synchronously for testing
            $job = new ProcessSallaWebhookJob($tenant->id, $payload);
            $job->handle();

            // Restore original delay hours if overridden
            if ($whatsappConfig && $originalDelay !== null) {
                $whatsappConfig->update(['delay_hours' => $originalDelay]);
            }

            return redirect()->route('dashboard')->with('success', 'تمت محاكاة الطلب وإرسال رسالة التقييم التفاعلية للعميل بنجاح!');
        } catch (\Exception $e) {
            Log::error('Sandbox order simulation failed.', ['message' => $e->getMessage()]);
            return redirect()->route('dashboard')->with('error', 'فشلت عملية المحاكاة: ' . $e->getMessage());
        }
    }
}
