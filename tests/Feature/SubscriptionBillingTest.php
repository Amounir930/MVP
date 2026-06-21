<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Demo Store', 'status' => 'active']);
        
        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->merchant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@demo.com',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        WhatsappConfig::create([
            'tenant_id' => $this->tenant->id,
            'waba_id' => 'waba_123',
            'phone_number_id' => 'phone_123',
            'status' => 'connected',
        ]);
    }

    public function test_admin_can_manually_update_tenant_subscription(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/simulator/update-subscription', [
            'tenant_id' => $this->tenant->id,
            'plan_name' => 'startup',
            'status' => 'active',
            'current_period_usage' => 150,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('subscription.plan_name', 'startup');
        $response->assertJsonPath('subscription.current_period_usage', 150);

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('startup', $subscription->plan_name);
        $this->assertEquals(150, $subscription->current_period_usage);
        $this->assertEquals(400, $subscription->monthly_limit);
    }

    public function test_admin_can_simulate_payment_webhook(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/simulator/trigger-webhook', [
            'tenant_id' => $this->tenant->id,
            'plan_name' => 'growth',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('subscription.plan_name', 'growth');
        $response->assertJsonPath('subscription.current_period_usage', 0); // resets usage on pay

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('growth', $subscription->plan_name);
        $this->assertEquals(1000, $subscription->monthly_limit);
        $this->assertEquals('active', $subscription->status);
    }

    public function test_whatsapp_job_is_throttled_when_limit_is_exceeded(): void
    {
        // 1. Setup subscription with usage = limit (e.g. 50/50 for free)
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_name' => 'free',
            'price' => 0.00,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'monthly_limit' => 50,
            'current_period_usage' => 50, // reached limit!
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'salla_product_id' => 'p_11',
            'name' => 'Sample Product',
        ]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'salla_customer_id' => 'c_11',
            'name' => 'John Doe',
            'phone' => '966500000000',
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'salla_order_id' => 'o_11',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-11',
            'total' => 150.00,
            'status' => 'delivered',
        ]);

        // Mock the evolution API driver so it doesn't make real requests
        $this->mock(\App\Integration\Drivers\EvolutionAPIDriver::class, function ($mock) {
            $mock->shouldNotReceive('sendInteractiveList');
        });

        // Run the job
        $job = new \App\Jobs\SendWhatsAppRatingMessageJob($order);
        $job->handle();

        // Assert message log status is failed because limit reached
        $log = \App\Models\WhatsappMessageLog::where('order_id', $order->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
    }

    public function test_watermark_visibility_matches_plan_type(): void
    {
        // Free plan -> shows watermark
        $sub = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_name' => 'free',
            'price' => 0.00,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'monthly_limit' => 50,
            'current_period_usage' => 0,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'salla_product_id' => 'p_widget_test',
            'name' => 'Widget Test Product',
        ]);

        $response = $this->getJson("/api/v1/widget/data?product_id=p_widget_test");
        $response->assertStatus(200);
        $response->assertJsonPath('show_watermark', true);

        // Update plan to startup -> hides watermark
        $sub->update([
            'plan_name' => 'startup',
            'monthly_limit' => 400,
        ]);
        // Clear cache so it fetches fresh
        \Illuminate\Support\Facades\Cache::forget("widget_data_p_widget_test");

        $response = $this->getJson("/api/v1/widget/data?product_id=p_widget_test");
        $response->assertStatus(200);
        $response->assertJsonPath('show_watermark', false);
    }
}
