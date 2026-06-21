<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    /**
     * Upgrade/Downgrade the merchant's subscription (Simulated checkout/payment).
     */
    public function upgrade(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_name' => 'required|in:free,startup,growth',
        ]);

        $user = $request->user();
        if (!$user || !$user->tenant) {
            return redirect()->back()->with('error', 'مستأجر غير صالح أو منتهية صلاحيته.');
        }

        $plan = strtolower($request->plan_name);
        
        // Define plan parameters
        $limits = [
            'free' => 50,
            'startup' => 400,
            'growth' => 1000,
        ];

        $prices = [
            'free' => 0.00,
            'startup' => 99.00,
            'growth' => 199.00,
        ];

        try {
            $subscription = Subscription::where('tenant_id', $user->tenant->id)->first();

            if ($subscription) {
                $subscription->update([
                    'plan_name' => $plan,
                    'price' => $prices[$plan],
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'monthly_limit' => $limits[$plan],
                    'current_period_usage' => 0,
                    'gateway_token' => 'simulated_' . $plan . '_' . uniqid(),
                ]);
            } else {
                Subscription::create([
                    'tenant_id' => $user->tenant->id,
                    'plan_name' => $plan,
                    'price' => $prices[$plan],
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'monthly_limit' => $limits[$plan],
                    'current_period_usage' => 0,
                    'gateway_token' => 'simulated_' . $plan . '_' . uniqid(),
                ]);
            }

            Log::info("Tenant subscription updated successfully to plan: {$plan} for tenant: {$user->tenant->id}");

            return redirect()->back()->with('success', 'تم تفعيل وترقية باقة اشتراكك بنجاح وسحب المبلغ التجريبي.');
        } catch (\Exception $e) {
            Log::error("Error processing simulated subscription upgrade: " . $e->getMessage());
            return redirect()->back()->with('error', 'حدث خطأ أثناء معالجة الاشتراك، يرجى المحاولة لاحقاً.');
        }
    }
}
