<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $sallaConnected = false;
        $merchantId = null;
        $productsCount = 0;
        $ordersCount = 0;

        $whatsappConnected = false;
        $instanceName = null;
        $status = 'disconnected';
        $delayHours = 24;
        $customQuestions = null;

        if ($user && $user->tenant) {
            $sallaConfig = \App\Models\SallaConfig::where('tenant_id', $user->tenant->id)->first();
            if ($sallaConfig && $sallaConfig->access_token) {
                $sallaConnected = true;
                $merchantId = $sallaConfig->merchant_id;
                
                // Fetch the actual counts of synchronized resources
                $productsCount = \App\Models\Product::count();
                $ordersCount = \App\Models\Order::count();
            }

            $whatsappConfig = \App\Models\WhatsappConfig::where('tenant_id', $user->tenant->id)->first();
            if ($whatsappConfig) {
                $whatsappConnected = ($whatsappConfig->status === 'connected');
                $instanceName = $whatsappConfig->instance_name;
                $status = $whatsappConfig->status ?? 'disconnected';
                $delayHours = (int) $whatsappConfig->delay_hours;
                $customQuestions = $whatsappConfig->custom_questions;
            }

            // Fetch active subscription details
            $sub = \App\Models\Subscription::where('tenant_id', $user->tenant->id)->first();
            if ($sub) {
                $subscription = [
                    'plan_name' => $sub->plan_name,
                    'price' => (float) $sub->price,
                    'status' => $sub->status,
                    'current_period_start' => $sub->current_period_start ? $sub->current_period_start->toIso8601String() : null,
                    'current_period_end' => $sub->current_period_end ? $sub->current_period_end->toIso8601String() : null,
                    'monthly_limit' => (int) $sub->monthly_limit,
                    'current_period_usage' => (int) $sub->current_period_usage,
                ];
            } else {
                // Backfill default Free subscription for legacy accounts
                $newSub = \App\Models\Subscription::create([
                    'tenant_id' => $user->tenant->id,
                    'plan_name' => 'free',
                    'price' => 0.00,
                    'status' => 'active',
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->addMonth(),
                    'monthly_limit' => 50,
                    'current_period_usage' => 0,
                    'gateway_token' => 'backfill_' . uniqid(),
                ]);
                $subscription = [
                    'plan_name' => $newSub->plan_name,
                    'price' => (float) $newSub->price,
                    'status' => $newSub->status,
                    'current_period_start' => $newSub->current_period_start->toIso8601String(),
                    'current_period_end' => $newSub->current_period_end->toIso8601String(),
                    'monthly_limit' => $newSub->monthly_limit,
                    'current_period_usage' => $newSub->current_period_usage,
                ];
            }
        } else {
            $subscription = null;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'salla' => [
                'connected' => $sallaConnected,
                'merchant_id' => $merchantId,
                'products_count' => $productsCount,
                'orders_count' => $ordersCount,
            ],
            'whatsapp' => [
                'connected' => $whatsappConnected,
                'instance_name' => $instanceName,
                'status' => $status,
                'delay_hours' => $delayHours,
                'custom_questions' => $customQuestions,
            ],
            'subscription' => $subscription,
        ];
    }
}
