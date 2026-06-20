<?php

namespace Tests\Feature;

use App\Events\OrderDelivered;
use App\Integration\Drivers\SallaDriver;
use App\Jobs\ProcessSallaWebhookJob;
use App\Jobs\SyncSallaOrdersJob;
use App\Jobs\SyncSallaProductsJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\SallaConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SallaOAuthClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SallaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up default environment configurations.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        config([
            'services.salla.client_id' => 'test-client-id',
            'services.salla.client_secret' => 'test-client-secret',
            'services.salla.redirect_uri' => 'https://mvp.test/auth/salla/callback',
        ]);
    }

    /**
     * Verifies that the redirect endpoint sets a state parameter in the session
     * and redirects the user to the Salla authorization URL.
     */
    public function test_oauth_redirect_stores_state_and_redirects(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $response = $this->actingAs($user)
            ->get(route('salla.auth.redirect'));

        $response->assertRedirect();
        $this->assertTrue(session()->has('salla_oauth_state'));
        
        $state = session('salla_oauth_state');
        $expectedUrl = "https://accounts.salla.sa/oauth2/auth?" . http_build_query([
            'response_type' => 'code',
            'client_id' => 'test-client-id',
            'redirect_uri' => 'https://mvp.test/auth/salla/callback',
            'state' => $state,
            'scope' => 'offline_access',
        ]);
        
        $response->assertRedirect($expectedUrl);
    }

    /**
     * Verifies that the callback endpoint exchanges the code for tokens, retrieves merchant details,
     * stores them encrypted in the database, and redirects with a success message.
     */
    public function test_oauth_callback_success_stores_tokens_encrypted(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $state = 'test-random-state-string';
        session(['salla_oauth_state' => $state]);

        $oauthMock = $this->mock(SallaOAuthClient::class);
        $oauthMock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->with('auth-code-123')
            ->andReturn([
                'access_token' => 'access-token-val',
                'refresh_token' => 'refresh-token-val',
                'expires_in' => 86400,
                'scope' => 'offline_access',
            ]);

        $oauthMock->shouldReceive('getMerchantDetails')
            ->once()
            ->with('access-token-val')
            ->andReturn([
                'data' => [
                    'id' => 'salla-merchant-123',
                ]
            ]);

        $response = $this->actingAs($user)
            ->get(route('salla.auth.callback', [
                'code' => 'auth-code-123',
                'state' => $state,
            ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('salla_configs', [
            'tenant_id' => $tenant->id,
            'merchant_id' => 'salla-merchant-123',
        ]);

        $config = SallaConfig::where('tenant_id', $tenant->id)->first();
        $this->assertEquals('access-token-val', $config->access_token);
        $this->assertEquals('refresh-token-val', $config->refresh_token);

        Queue::assertPushed(SyncSallaProductsJob::class);
        Queue::assertPushed(SyncSallaOrdersJob::class);
    }

    /**
     * Verifies that the callback endpoint rejects requests when the session state token
     * does not match the query parameter state token.
     */
    public function test_oauth_callback_fails_on_state_mismatch(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        session(['salla_oauth_state' => 'expected-state']);

        $response = $this->actingAs($user)
            ->get(route('salla.auth.callback', [
                'code' => 'auth-code-123',
                'state' => 'malicious-mismatched-state',
            ]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('salla_configs', 0);
    }

    /**
     * Verifies that the token refresh scheduled command refreshes expiring access tokens
     * and updates the expiration timestamp and credentials in the database.
     */
    public function test_console_command_refreshes_expiring_tokens(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 12:00:00'));

        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        $config = SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'expires_at' => Carbon::now()->addDays(2),
        ]);

        $oauthMock = $this->mock(SallaOAuthClient::class);
        $oauthMock->shouldReceive('refreshAccessToken')
            ->once()
            ->with('old-refresh-token')
            ->andReturn([
                'access_token' => 'new-access-token-val',
                'refresh_token' => 'new-refresh-token-val',
                'expires_in' => 86400,
            ]);

        $this->artisan('salla:refresh-tokens')
            ->assertExitCode(0);

        $config->refresh();
        $this->assertEquals('new-access-token-val', $config->access_token);
        $this->assertEquals('new-refresh-token-val', $config->refresh_token);
        $this->assertEquals(Carbon::now()->addSeconds(86400)->toDateTimeString(), $config->expires_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * Verifies that webhook requests require signature authentication and returns 401 when invalid.
     */
    public function test_webhook_returns_401_for_invalid_signature(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
            'webhook_secret' => 'super-secret-key',
        ]);

        $payload = json_encode([
            'event' => 'order.status.updated',
            'merchant' => 'merchant-123',
            'data' => []
        ]);

        $response = $this->postJson('/api/v1/webhooks/salla', json_decode($payload, true), [
            'X-Salla-Signature' => 'invalid-computed-signature-hash'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Verifies that the webhook controller accepts valid signed requests, dispatches the processing job,
     * and returns a 200 HTTP status response.
     */
    public function test_webhook_accepts_valid_signature_and_dispatches_job(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Merchant A']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
            'webhook_secret' => 'super-secret-key',
        ]);

        $payloadData = [
            'event' => 'order.status.updated',
            'merchant' => 'merchant-123',
            'data' => [
                'id' => 9999,
                'status' => ['name' => 'delivered'],
            ]
        ];

        $payloadStr = json_encode($payloadData);
        $signature = hash_hmac('sha256', $payloadStr, 'super-secret-key');

        $response = $this->postJson('/api/v1/webhooks/salla', $payloadData, [
            'X-Salla-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'accepted']);

        Queue::assertPushed(ProcessSallaWebhookJob::class);
    }

    /**
     * Verifies that requests for unregistered merchants are ignored with a 200 HTTP response.
     */
    public function test_webhook_ignores_unregistered_merchants(): void
    {
        Queue::fake();

        $payload = [
            'event' => 'order.status.updated',
            'merchant' => 'unregistered-merchant-id',
            'data' => []
        ];

        $response = $this->postJson('/api/v1/webhooks/salla', $payload, [
            'X-Salla-Signature' => 'any-signature-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
        Queue::assertNotPushed(ProcessSallaWebhookJob::class);
    }

    /**
     * Verifies that ProcessSallaWebhookJob executes successfully under isolated tenant context,
     * updates customer and order tables, and dispatches the OrderDelivered integration event.
     */
    public function test_process_webhook_job_creates_records_and_fires_event(): void
    {
        Event::fake([OrderDelivered::class]);

        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        $webhookPayload = [
            'event' => 'order.status.updated',
            'merchant' => 'merchant-123',
            'data' => [
                'id' => '11223344',
                'reference_id' => 'INV-2026-001',
                'status' => [
                    'name' => 'delivered',
                ],
                'amounts' => [
                    'total' => [
                        'amount' => 150.75,
                    ]
                ],
                'customer' => [
                    'id' => 'customer-999',
                    'first_name' => 'Ahmad',
                    'last_name' => 'Al-Malki',
                    'mobile' => '+966500000000',
                    'email' => 'ahmad@example.com',
                ]
            ]
        ];

        $job = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $job->handle();

        // Enforce temporary tenant isolation for checking
        app()->bind('current_tenant_id', fn () => $tenant->id);

        $customer = Customer::where('salla_customer_id', 'customer-999')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Ahmad Al-Malki', $customer->name);
        $this->assertEquals('+966500000000', $customer->phone);
        $this->assertEquals($tenant->id, $customer->tenant_id);

        $order = Order::where('salla_order_id', '11223344')->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertEquals('INV-2026-001', $order->invoice_number);
        $this->assertEquals(150.75, $order->total);
        $this->assertEquals('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals($tenant->id, $order->tenant_id);

        Event::assertDispatched(OrderDelivered::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    /**
     * Verifies that SallaDriver correctly dispatches the sync background jobs.
     */
    public function test_salla_driver_dispatches_sync_jobs(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Merchant A']);
        $driver = new SallaDriver();

        $driver->syncProducts($tenant);
        Queue::assertPushed(SyncSallaProductsJob::class);

        $driver->syncOrders($tenant);
        Queue::assertPushed(SyncSallaOrdersJob::class);
    }

    /**
     * Verifies that SallaDriver injects the custom script loader using the Salla API successfully.
     */
    public function test_salla_driver_injects_widget_successfully(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        Http::fake([
            'https://api.salla.dev/admin/v2/merchants/scripts' => Http::response(['status' => 'success'], 201)
        ]);

        $driver = new SallaDriver();
        $result = $driver->injectWidget($tenant, 'https://mvp.test/widget.js');

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.salla.dev/admin/v2/merchants/scripts'
                && $request->hasHeader('Authorization', 'Bearer valid-access-token')
                && $request['name'] === 'Conversion Trust Widget Loader'
                && $request['src'] === 'https://mvp.test/widget.js'
                && $request['type'] === 'footer';
        });
    }

    /**
     * Verifies that SyncSallaProductsJob fetches and synchronizes products page by page.
     */
    public function test_sync_salla_products_job_fetches_and_persists_data(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'token-abc',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        Http::fake([
            'https://api.salla.dev/admin/v2/products*' => Http::sequence()
                ->push([
                    'data' => [
                        [
                            'id' => 'prod-001',
                            'name' => 'Fancy Shirt',
                            'image' => 'https://image.com/shirt.jpg',
                            'urls' => ['customer' => 'https://store.com/shirt']
                        ]
                    ],
                    'pagination' => [
                        'links' => [
                            'next' => 'https://api.salla.dev/admin/v2/products?page=2'
                        ]
                    ]
                ])
                ->push([
                    'data' => [
                        [
                            'id' => 'prod-002',
                            'name' => 'Nice Pants',
                            'image' => 'https://image.com/pants.jpg',
                            'urls' => ['customer' => 'https://store.com/pants']
                        ]
                    ],
                    'pagination' => [
                        'links' => []
                    ]
                ])
        ]);

        $job = new SyncSallaProductsJob($tenant);
        $job->handle();

        app()->bind('current_tenant_id', fn () => $tenant->id);

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'salla_product_id' => 'prod-001',
            'name' => 'Fancy Shirt',
            'image_url' => 'https://image.com/shirt.jpg',
            'product_url' => 'https://store.com/shirt',
        ]);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'salla_product_id' => 'prod-002',
            'name' => 'Nice Pants',
            'image_url' => 'https://image.com/pants.jpg',
            'product_url' => 'https://store.com/pants',
        ]);
    }

    /**
     * Verifies that SyncSallaOrdersJob fetches, maps, and persists customers and orders,
     * and dispatches OrderDelivered event for delivered status.
     */
    public function test_sync_salla_orders_job_fetches_persists_data_and_triggers_events(): void
    {
        Event::fake([OrderDelivered::class]);

        $tenant = Tenant::create(['name' => 'Merchant A']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'token-abc',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        Http::fake([
            'https://api.salla.dev/admin/v2/orders*' => Http::response([
                'data' => [
                    [
                        'id' => 'ord-1001',
                        'reference_id' => 'INV-1001',
                        'status' => ['name' => 'delivered'],
                        'amounts' => ['total' => ['amount' => 299.99]],
                        'customer' => [
                            'id' => 'cust-101',
                            'first_name' => 'Khalid',
                            'last_name' => 'Ali',
                            'mobile' => '+966501111111',
                            'email' => 'khalid@example.com'
                        ]
                    ],
                    [
                        'id' => 'ord-1002',
                        'reference_id' => 'INV-1002',
                        'status' => ['name' => 'under_preparing'],
                        'amounts' => ['total' => ['amount' => 99.50]],
                        'customer' => [
                            'id' => 'cust-102',
                            'first_name' => 'Sara',
                            'last_name' => 'Ahmed',
                            'mobile' => '+966502222222',
                            'email' => 'sara@example.com'
                        ]
                    ]
                ],
                'pagination' => ['links' => []]
            ])
        ]);

        $job = new SyncSallaOrdersJob($tenant);
        $job->handle();

        app()->bind('current_tenant_id', fn () => $tenant->id);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-101',
            'name' => 'Khalid Ali',
            'phone' => '+966501111111',
        ]);
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-102',
            'name' => 'Sara Ahmed',
            'phone' => '+966502222222',
        ]);

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'ord-1001',
            'invoice_number' => 'INV-1001',
            'total' => 299.99,
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'ord-1002',
            'invoice_number' => 'INV-1002',
            'total' => 99.50,
            'status' => 'under_preparing',
        ]);

        $deliveredOrder = Order::where('salla_order_id', 'ord-1001')->first();
        $this->assertNotNull($deliveredOrder->delivered_at);

        $pendingOrder = Order::where('salla_order_id', 'ord-1002')->first();
        $this->assertNull($pendingOrder->delivered_at);

        Event::assertNotDispatched(OrderDelivered::class);
    }

    /**
     * Verifies that ProcessSallaWebhookJob handles product.created and product.updated events.
     */
    public function test_process_webhook_job_creates_and_updates_products(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        $webhookPayload = [
            'event' => 'product.created',
            'merchant' => 'merchant-123',
            'data' => [
                'id' => '998877',
                'name' => 'Cool Gadget',
                'image' => 'https://image.com/gadget.jpg',
                'urls' => [
                    'customer' => 'https://store.com/gadget'
                ]
            ]
        ];

        // 1. Test creation
        $job = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $job->handle();

        app()->bind('current_tenant_id', fn () => $tenant->id);

        $product = Product::where('salla_product_id', '998877')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Cool Gadget', $product->name);
        $this->assertEquals('https://image.com/gadget.jpg', $product->image_url);
        $this->assertEquals('https://store.com/gadget', $product->product_url);
        $this->assertEquals($tenant->id, $product->tenant_id);

        // 2. Test update
        $webhookPayload['event'] = 'product.updated';
        $webhookPayload['data']['name'] = 'Super Cool Gadget';

        $job = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $job->handle();

        $product->refresh();
        $this->assertEquals('Super Cool Gadget', $product->name);
    }

    /**
     * Verifies that ProcessSallaWebhookJob handles product.deleted events.
     */
    public function test_process_webhook_job_deletes_products(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        app()->bind('current_tenant_id', fn () => $tenant->id);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'salla_product_id' => '998877',
            'name' => 'Cool Gadget',
            'image_url' => 'https://image.com/gadget.jpg',
            'product_url' => 'https://store.com/gadget',
        ]);

        $webhookPayload = [
            'event' => 'product.deleted',
            'merchant' => 'merchant-123',
            'data' => [
                'id' => '998877'
            ]
        ];

        $job = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $job->handle();

        $this->assertNull(Product::where('salla_product_id', '998877')->first());
    }

    /**
     * Verifies that the disconnect route deletes all Salla integration configurations
     * and associated store data for the authenticated merchant user.
     */
    public function test_auth_disconnect_deletes_all_tenant_associated_data(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        Product::create([
            'tenant_id' => $tenant->id,
            'salla_product_id' => 'prod-1',
            'name' => 'Fancy Shirt',
        ]);

        $response = $this->actingAs($user)
            ->post('/auth/salla/disconnect');

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        // Verify data was deleted
        $this->assertDatabaseMissing('salla_configs', ['tenant_id' => $tenant->id]);
        
        app()->bind('current_tenant_id', fn () => $tenant->id);
        $this->assertDatabaseMissing('products', ['tenant_id' => $tenant->id]);
    }

    /**
     * Verifies that ProcessSallaWebhookJob handles app.uninstalled events and wipes all store data.
     */
    public function test_process_webhook_job_wipes_data_on_app_uninstalled(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        Product::create([
            'tenant_id' => $tenant->id,
            'salla_product_id' => 'prod-1',
            'name' => 'Fancy Shirt',
        ]);

        $webhookPayload = [
            'event' => 'app.uninstalled',
            'merchant' => 'merchant-123',
            'data' => []
        ];

        $job = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $job->handle();

        // Verify data was deleted
        $this->assertDatabaseMissing('salla_configs', ['tenant_id' => $tenant->id]);

        app()->bind('current_tenant_id', fn () => $tenant->id);
        $this->assertDatabaseMissing('products', ['tenant_id' => $tenant->id]);
    }
}
