<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin overview dashboard.
     */
    public function index(Request $request): Response
    {
        $tenants = Tenant::with(['users', 'sallaConfig', 'whatsappConfig', 'subscription'])
            ->withCount(['reviews', 'orders', 'products'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate global metrics
        $totalStores = $tenants->count();
        
        $activeStores = $tenants->filter(function ($t) {
            return $t->sallaConfig !== null && $t->status === 'active';
        })->count();

        $totalMessagesConsumed = WhatsappMessageLog::withoutGlobalScopes()->count();

        // Format stores directory list
        $stores = $tenants->map(function ($tenant) {
            $owner = $tenant->users->first(); // Get first registered user as default owner
            $sub = $tenant->subscription;
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at ? $tenant->created_at->format('Y-m-d') : 'N/A',
                'owner_name' => $owner ? $owner->name : 'N/A',
                'owner_email' => $owner ? $owner->email : 'N/A',
                'salla_connected' => $tenant->sallaConfig !== null,
                'whatsapp_status' => $tenant->whatsappConfig ? $tenant->whatsappConfig->status : 'disconnected',
                'reviews_count' => $tenant->reviews_count,
                'orders_count' => $tenant->orders_count,
                'products_count' => $tenant->products_count,
                'subscription' => $sub ? [
                    'plan_name' => $sub->plan_name,
                    'price' => $sub->price,
                    'status' => $sub->status,
                    'monthly_limit' => $sub->monthly_limit,
                    'current_period_usage' => $sub->current_period_usage,
                    'current_period_end' => $sub->current_period_end->format('Y-m-d H:i:s'),
                ] : null,
            ];
        });

        return Inertia::render('Admin/Overview', [
            'stats' => [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'total_messages_consumed' => $totalMessagesConsumed,
            ],
            'stores' => $stores,
            'gateway_keys' => [
                'tap_api_key' => env('TAP_API_KEY') ?: 'sk_test_placeholder_key_for_billing_sim',
                'tap_webhook_secret' => env('TAP_WEBHOOK_SECRET') ?: 'whsec_placeholder_secret_for_billing_sim',
            ]
        ]);
    }

    /**
     * Toggle the active status of a store/tenant.
     */
    public function toggleStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $newStatus = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة المتجر بنجاح.',
            'status' => $newStatus,
        ]);
    }

    /**
     * Manually update/create a tenant's subscription.
     */
    public function updateSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_name' => 'required|in:free,startup,growth',
            'status' => 'required|in:active,expired,suspended',
            'current_period_usage' => 'required|integer|min:0',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        
        $limits = [
            'free' => 50,
            'startup' => 400,
            'growth' => 1000
        ];
        $prices = [
            'free' => 0.00,
            'startup' => 49.00,
            'growth' => 99.00
        ];

        $plan = $validated['plan_name'];

        $subscription = \App\Models\Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_name' => $plan,
                'price' => $prices[$plan],
                'status' => $validated['status'],
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'monthly_limit' => $limits[$plan],
                'current_period_usage' => $validated['current_period_usage'],
                'gateway_token' => 'sim_token_' . uniqid(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث اشتراك المتجر بنجاح يدوياً.',
            'subscription' => [
                'plan_name' => $subscription->plan_name,
                'price' => $subscription->price,
                'status' => $subscription->status,
                'monthly_limit' => $subscription->monthly_limit,
                'current_period_usage' => $subscription->current_period_usage,
                'current_period_end' => $subscription->current_period_end->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Simulate a gateway webhook charge.verified event.
     */
    public function triggerWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_name' => 'required|in:free,startup,growth',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = $validated['plan_name'];

        $limits = [
            'free' => 50,
            'startup' => 400,
            'growth' => 1000
        ];
        $prices = [
            'free' => 0.00,
            'startup' => 49.00,
            'growth' => 99.00
        ];

        $fakeTransactionId = 'ch_sim_' . uniqid();
        $fakeCustomerEmail = $tenant->users()->first()?->email ?: 'customer@example.com';

        // Webhook processing logic to trigger updates
        $subscription = \App\Models\Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_name' => $plan,
                'price' => $prices[$plan],
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'monthly_limit' => $limits[$plan],
                'current_period_usage' => 0, // Reset usage counter on new billing period
                'gateway_token' => 'sim_gateway_token_' . uniqid(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت محاكاة ويب هوك الدفع وتنشيط الباقة بنجاح.',
            'webhook_details' => [
                'event' => 'charge.verified',
                'gateway' => 'Tap Payments',
                'transaction_id' => $fakeTransactionId,
                'amount' => $prices[$plan],
                'currency' => 'USD',
                'customer_email' => $fakeCustomerEmail,
            ],
            'subscription' => [
                'plan_name' => $subscription->plan_name,
                'price' => $subscription->price,
                'status' => $subscription->status,
                'monthly_limit' => $subscription->monthly_limit,
                'current_period_usage' => $subscription->current_period_usage,
                'current_period_end' => $subscription->current_period_end->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}
