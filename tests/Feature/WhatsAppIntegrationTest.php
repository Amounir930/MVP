<?php

namespace Tests\Feature;

use App\Events\OrderDelivered;
use App\Jobs\SendWhatsAppRatingMessageJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappChatSession;
use App\Models\WhatsappConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.verify_token' => 'my-verify-token-123',
            'services.evolution.url' => 'http://evolution-api.test',
            'services.evolution.api_key' => 'global-key-abc',
        ]);
    }

    /**
     * Test GET webhook verification success.
     */
    public function test_whatsapp_webhook_verification_success(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'challenge_code_999',
            'hub_verify_token' => 'my-verify-token-123',
        ]));

        $response->assertStatus(200);
        $response->assertSee('challenge_code_999');
    }

    /**
     * Test GET webhook verification failure.
     */
    public function test_whatsapp_webhook_verification_failure(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_challenge' => 'challenge_code_999',
            'hub_verify_token' => 'wrong-token',
        ]));

        $response->assertStatus(403);
    }

    /**
     * Test POST webhook ignores unregistered Evolution API instance.
     */
    public function test_whatsapp_webhook_ignores_unregistered_instance(): void
    {
        $payload = [
            'event' => 'messages.upsert',
            'instance' => 'unregistered-instance',
            'data' => [
                'key' => [
                    'remoteJid' => '966500000000@s.whatsapp.net',
                    'fromMe' => false,
                ],
                'message' => [
                    'conversation' => 'hello',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
    }

    /**
     * Test POST webhook processes connection.update event.
     */
    public function test_whatsapp_webhook_processes_connection_update(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        $config = WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'disconnected',
        ]);

        $payload = [
            'event' => 'connection.update',
            'instance' => $instanceName,
            'data' => [
                'state' => 'open',
                'statusReason' => 200,
            ]
        ];

        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $config->refresh();
        $this->assertEquals('connected', $config->status);
    }

    /**
     * Test OrderDelivered event schedules rating message.
     */
    public function test_order_delivered_schedules_whatsapp_rating_message(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Merchant A']);
        
        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => 'ct_' . $tenant->id,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 5,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-1',
            'name' => 'John Doe',
            'phone' => '+966500000000',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-100',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        event(new OrderDelivered($order));

        Queue::assertPushed(SendWhatsAppRatingMessageJob::class, function ($job) {
            return $job->delay !== null;
        });
    }

    /**
     * Test SendWhatsAppRatingMessageJob sends interactive list and creates session.
     */
    public function test_send_whatsapp_rating_message_job_sends_list_message_and_creates_session(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);
        
        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-1',
            'name' => 'John Doe',
            'phone' => '966500000000',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-100',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        $job = new SendWhatsAppRatingMessageJob($order);
        $job->handle();

        app()->bind('current_tenant_id', fn () => $tenant->id);

        $this->assertDatabaseHas('whatsapp_chat_sessions', [
            'tenant_id' => $tenant->id,
            'phone' => '966500000000',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966500000000'
                && str_contains($request['text'], '5 - ⭐⭐⭐⭐⭐ ممتاز');
        });
    }

    /**
     * Test chatbot state machine flow step-by-step.
     */
    public function test_whatsapp_chatbot_full_state_machine_flow(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);
        
        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-1',
            'name' => 'John Doe',
            'phone' => '966500000000',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-100',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966500000000',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        // 1. Send rating 5
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'list_reply', '5');
        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        $session->refresh();
        $this->assertEquals(5, $session->rating);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(0, $session->answers['current_index']);

        // 2. Send Question 1 reply (yes)
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'button_reply', 'yes');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $session->refresh();
        $this->assertEquals('نعم مطابق 👍', $session->answers['responses'][0]['response']);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(1, $session->answers['current_index']);

        // 3. Send Question 2 reply (excellent)
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'button_reply', 'excellent');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $session->refresh();
        $this->assertEquals('ممتازة ⭐', $session->answers['responses'][1]['response']);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(2, $session->answers['current_index']);

        // 4. Send Question 3 reply (yes)
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'button_reply', 'yes');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $session->refresh();
        $this->assertEquals('نعم مناسب', $session->answers['responses'][2]['response']);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(3, $session->answers['current_index']);

        // 5. Send text comment
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'text', 'المنتج رائع وتوصيل سريع جداً!');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $session->refresh();
        $this->assertEquals('المنتج رائع وتوصيل سريع جداً!', $session->answers['responses'][3]['response']);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(4, $session->answers['current_index']);

        // 6. Skip media upload and finish review
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'button_reply', 'skip');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        // Session should be deleted
        $this->assertNull(WhatsappChatSession::find($session->id));

        // Review record should be created
        app()->bind('current_tenant_id', fn () => $tenant->id);
        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'comment' => 'المنتج رائع وتوصيل سريع جداً!',
            'status' => 'pending',
            'source' => 'whatsapp',
        ]);
    }

    /**
     * Test chatbot finishing on media upload.
     */
    public function test_whatsapp_chatbot_finishes_on_media_upload(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        // Mock Evolution API responses
        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/chat/getBase64FromMediaMessage/{$instanceName}" => Http::response([
                'base64' => 'data:image/png;base64,' . base64_encode('fakebinarycontenthere'),
            ], 200),
        ]);
        
        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-1',
            'name' => 'John Doe',
            'phone' => '966500000000',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-100',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966500000000',
            'order_id' => $order->id,
            'step' => 'awaiting_question',
            'rating' => 4,
            'answers' => [
                'current_index' => 4,
                'responses' => [
                    ['type' => 'buttons', 'text' => 'هل المنتج مطابق للوصف والصور؟', 'response' => 'نعم مطابق 👍'],
                    ['type' => 'buttons', 'text' => 'كيف كانت جودة المنتج؟', 'response' => 'ممتازة ⭐'],
                    ['type' => 'buttons', 'text' => 'هل المقاس مناسب؟', 'response' => 'نعم مناسب'],
                    ['type' => 'text', 'text' => 'يسعدنا معرفة رأيك بالتفصيل. يرجى كتابة تعليقك هنا في رسالة واحدة.', 'response' => 'Great product!'],
                ]
            ],
            'expires_at' => now()->addHours(2),
        ]);

        // Send photo payload
        $payload = $this->createWebhookPayload($instanceName, '966500000000@s.whatsapp.net', 'image', 'media-id-xyz');

        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        // Session should be deleted
        $this->assertNull(WhatsappChatSession::find($session->id));

        // Review record should be created with media URL pointing to the local storage path
        app()->bind('current_tenant_id', fn () => $tenant->id);
        $review = Review::where('order_id', $order->id)->first();
        $this->assertNotNull($review);
        $this->assertEquals('/uploads/reviews/' . $tenant->id . '/media-id-xyz.png', $review->media_url);
        $this->assertEquals('image', $review->media_type);
        $this->assertEquals(4, $review->rating);
        $this->assertEquals('Great product!', $review->comment);

        // Clean up mock downloaded file
        $downloadedFile = public_path('uploads/reviews/' . $tenant->id . '/media-id-xyz.png');
        if (file_exists($downloadedFile)) {
            unlink($downloadedFile);
        }
    }

    /**
     * Test starting the connection process (creating instance on Evolution API).
     */
    public function test_whatsapp_connect_creates_instance_on_evolution_api(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        Http::fake([
            '*/instance/create' => Http::response([
                'qrcode' => ['base64' => 'fake-base-64-string'],
                'hash' => ['apikey' => 'fake-instance-apikey']
            ], 201),
            "*/webhook/set/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/auth/whatsapp/connect');
        
        $response->assertStatus(200);
        $response->assertJson([
            'qrcode' => 'fake-base-64-string',
            'status' => 'disconnected',
        ]);

        $this->assertDatabaseHas('whatsapp_configs', [
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'status' => 'disconnected',
        ]);
    }

    /**
     * Test starting the connection process deletes zombie instance if already in use.
     */
    public function test_whatsapp_connect_recreates_on_zombie_403(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        Http::fake([
            '*/instance/create' => Http::sequence()
                ->push(['status' => 403, 'response' => ['message' => ["This name \"{$instanceName}\" is already in use."]]], 403)
                ->push([
                    'qrcode' => ['base64' => 'fresh-base-64-string'],
                    'hash' => ['apikey' => 'fresh-instance-apikey']
                ], 201),
            "*/instance/delete/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/webhook/set/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/auth/whatsapp/connect');
        
        $response->assertStatus(200);
        $response->assertJson([
            'qrcode' => 'fresh-base-64-string',
            'status' => 'disconnected',
        ]);

        $this->assertDatabaseHas('whatsapp_configs', [
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'status' => 'disconnected',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/instance/delete/{$instanceName}");
        });
    }

    /**
     * Test checking the connection state from Evolution API.
     */
    public function test_whatsapp_status_returns_state_from_evolution_api(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'disconnected',
        ]);

        Http::fake([
            "*/instance/connectionState/{$instanceName}" => Http::response([
                'instance' => ['state' => 'open']
            ], 200)
        ]);

        $response = $this->actingAs($user)->getJson('/auth/whatsapp/status');
        
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'connected',
        ]);

        $this->assertDatabaseHas('whatsapp_configs', [
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'status' => 'connected',
        ]);
    }

    /**
     * Test disconnecting and deleting the WhatsApp instance.
     */
    public function test_whatsapp_disconnect_deletes_instance(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
        ]);

        Http::fake([
            "*/instance/logout/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/instance/delete/{$instanceName}" => Http::response(['status' => 'success'], 200)
        ]);

        $response = $this->actingAs($user)->post('/auth/whatsapp/disconnect');
        
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('whatsapp_configs', [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Test updating delay settings for WhatsApp config.
     */
    public function test_whatsapp_update_settings_saves_delay(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $response = $this->actingAs($user)->post('/auth/whatsapp/settings', [
            'delay_hours' => 12,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_configs', [
            'tenant_id' => $tenant->id,
            'delay_hours' => 12,
        ]);
    }

    /**
     * Test Salla sandbox simulation creates customer/order and fires events.
     */
    public function test_salla_sandbox_simulation(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 24,
        ]);

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $response = $this->actingAs($user)->post('/sandbox/simulate-order', [
            'customer_name' => 'Test Sandbox Customer',
            'customer_phone' => '966500000000',
            'order_reference' => 'SALLA-SANDBOX-TEST',
            'order_total' => 250.50,
            'force_immediate' => 1,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        app()->bind('current_tenant_id', fn () => $tenant->id);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Test Sandbox Customer',
            'phone' => '966500000000',
        ]);

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'invoice_number' => 'SALLA-SANDBOX-TEST',
            'total' => 250.50,
            'status' => 'delivered',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}");
        });
    }

    /**
     * Test custom questions persistence and chatbot logic.
     */
    public function test_custom_questions_handling(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        $config = WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
        ]);

        // 1. Save settings with custom questions
        $customQuestionsPayload = [
            'enable_questions' => true,
            'rating_message' => 'مرحباً {name}، نود تقييم طلبك {order_number}!',
            'questions' => [
                [
                    'id' => 'q_1',
                    'type' => 'buttons',
                    'text' => 'هل كان الأكل ساخن ولذيذ؟',
                    'options' => ['نعم حار ولذيذ 🔥', 'كان بارد قليلاً ❄️', 'سيء جداً 🤮'],
                ],
                [
                    'id' => 'q_2',
                    'type' => 'buttons',
                    'text' => 'كيف تقيم النظافة والتغليف؟',
                    'options' => ['نظيف ومغلق بإحكام', 'مقبول', 'سيء ومفتوح'],
                ],
                [
                    'id' => 'q_3',
                    'type' => 'buttons',
                    'text' => 'هل وصل المندوب بسرعة؟',
                    'options' => ['سريع جداً ⚡', 'تأخر قليلاً', 'تأخر كثيراً 🐢'],
                ],
                [
                    'id' => 'q_4',
                    'type' => 'text',
                    'text' => 'اكتب تعليقك هنا:',
                ],
                [
                    'id' => 'q_5',
                    'type' => 'media',
                    'text' => 'شاركنا صورة للطلب:',
                ],
            ]
        ];

        $response = $this->actingAs($user)->post('/auth/whatsapp/settings', [
            'delay_hours' => 12,
            'custom_questions' => $customQuestionsPayload,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $config->refresh();
        $this->assertEquals(12, $config->delay_hours);
        $this->assertEquals('هل كان الأكل ساخن ولذيذ؟', $config->custom_questions['questions'][0]['text']);
        $this->assertEquals('نعم حار ولذيذ 🔥', $config->custom_questions['questions'][0]['options'][0]);

        // 2. Test SendWhatsAppRatingMessageJob replaces variables and loads custom template
        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-99',
            'name' => 'سعد العمري',
            'phone' => '966512345678',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-99',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-9999',
            'total' => 120.00,
            'status' => 'delivered',
        ]);

        $job = new SendWhatsAppRatingMessageJob($order);
        $job->handle();

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345678'
                && str_contains($request['text'], 'مرحباً سعد العمري، نود تقييم طلبك INV-9999!');
        });

        // 3. Test Webhook state machine uses custom questions and custom options
        $session = WhatsappChatSession::where('phone', '966512345678')->firstOrFail();

        // Client answers rating 5 -> chatbot should send Question 1 text and custom options
        $payload = $this->createWebhookPayload($instanceName, '966512345678@s.whatsapp.net', 'list_reply', '5');
        $res = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $res->assertStatus(200);

        $session->refresh();
        $this->assertEquals(5, $session->rating);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(0, $session->answers['current_index']);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345678'
                && str_contains($request['text'], "هل كان الأكل ساخن ولذيذ؟")
                && str_contains($request['text'], "1 - نعم حار ولذيذ 🔥");
        });

        // Client clicks custom button opt_1 -> chatbot should map it and save text in DB, then send custom Q2
        $payload = $this->createWebhookPayload($instanceName, '966512345678@s.whatsapp.net', 'button_reply', 'opt_1');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $session->refresh();
        $this->assertEquals('نعم حار ولذيذ 🔥', $session->answers['responses'][0]['response']);
        $this->assertEquals('awaiting_question', $session->step);
        $this->assertEquals(1, $session->answers['current_index']);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345678'
                && str_contains($request['text'], "كيف تقيم النظافة والتغليف؟")
                && str_contains($request['text'], "1 - نظيف ومغلق بإحكام");
        });
    }

    /**
     * Test chatbot behavior when enable_questions is false (rating-only review).
     */
    public function test_whatsapp_chatbot_rating_only_when_questions_disabled(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
            'custom_questions' => [
                'enable_questions' => false,
                'rating_message' => 'مرحباً {name}، نود تقييم طلبك {order_number}!',
                'success_message' => 'شكراً جزيلاً لثقتك بمتجرنا، تم استلام تقييمك.',
                'questions' => []
            ]
        ]);

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-100',
            'name' => 'أحمد العتيبي',
            'phone' => '966512345670',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-100',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-1000',
            'total' => 150.00,
            'status' => 'delivered',
        ]);

        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345670',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        // Send rating 4 -> chatbot should immediately finalize the review
        $payload = $this->createWebhookPayload($instanceName, '966512345670@s.whatsapp.net', 'list_reply', '4');
        $response = $this->postJson('/api/v1/webhooks/whatsapp', $payload);
        $response->assertStatus(200);

        // Session should be deleted immediately
        $this->assertNull(WhatsappChatSession::find($session->id));

        // Review record should be created
        app()->bind('current_tenant_id', fn () => $tenant->id);
        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'comment' => null,
            'media_url' => null,
            'media_type' => null,
            'status' => 'pending',
            'source' => 'whatsapp',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345670'
                && $request['text'] === 'شكراً جزيلاً لثقتك بمتجرنا، تم استلام تقييمك.';
        });
    }

    /**
     * Test chatbot behavior with custom invalid rating replies.
     */
    public function test_whatsapp_chatbot_invalid_rating_handling(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        // --- Scenario 1: custom invalid rating message IS configured ---
        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
            'custom_questions' => [
                'enable_questions' => true,
                'invalid_rating_message' => 'سيتم التواصل معكم حالاً من خلال خدمة العملاء.',
                'questions' => []
            ]
        ]);

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-101',
            'name' => 'أحمد العتيبي',
            'phone' => '966512345671',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-101',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-1001',
            'total' => 150.00,
            'status' => 'delivered',
        ]);

        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345671',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        // Sending numeric typo "6" -> chatbot should directly send the custom message and terminate session immediately
        $payload = $this->createWebhookPayload($instanceName, '966512345671@s.whatsapp.net', 'text', '6');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        $this->assertNull(WhatsappChatSession::find($session->id)); // Session deleted immediately!

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'rating' => null,
            'comment' => '6',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345671'
                && $request['text'] === 'سيتم التواصل معكم حالاً من خلال خدمة العملاء.';
        });

        // --- Scenario 2: custom invalid rating message IS NOT configured ---
        // Clean database configurations
        WhatsappConfig::truncate();
        WhatsappChatSession::truncate();
        Review::truncate();

        WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
            'custom_questions' => [
                'enable_questions' => true,
                'invalid_rating_message' => null,
                'questions' => []
            ]
        ]);

        $session2 = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345671',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        // Sending numeric typo "6" -> chatbot should directly send the fallback message and terminate session immediately
        $payload2 = $this->createWebhookPayload($instanceName, '966512345671@s.whatsapp.net', 'text', '6');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload2)->assertStatus(200);

        $this->assertNull(WhatsappChatSession::find($session2->id)); // Session deleted immediately!

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'rating' => null,
            'comment' => '6',
        ]);

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345671'
                && $request['text'] === 'سيتم تحويلك لخدمة العملاء الآن لمساعدتك.';
        });
    }

    /**
     * Test saving all customizable fields and verifying they are used in the outgoing job and webhook resend.
     */
    public function test_whatsapp_settings_saves_fully_customized_text_fields(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $instanceName = 'ct_' . $tenant->id;

        $customQuestionsPayload = [
            'enable_questions' => true,
            'rating_message' => 'مرحباً {name}، كيف كان طلبك {order_number}؟',
            'rating_button_label' => 'اضغط هنا للتقييم',
            'rating_label_5' => 'خمس نجوم رائع',
            'rating_label_4' => 'اربع نجوم ممتاز',
            'rating_label_3' => 'ثلاث نجوم عادي',
            'rating_label_2' => 'نجمتين سيء',
            'rating_label_1' => 'نجمة واحدة سيء جداً',
            'rating_invalid_warning' => 'يا عميلنا العزيز اختر من فضلك من القائمة المخصصة:',
            'invalid_rating_message' => 'ردك خاطئ وسيتم تحويلك لدعم العملاء.',
            'success_message' => 'شكراً لك، تم التقييم!',
            'questions' => []
        ];

        $response = $this->actingAs($user)->post('/auth/whatsapp/settings', [
            'delay_hours' => 24,
            'custom_questions' => $customQuestionsPayload,
        ]);

        $response->assertRedirect(route('dashboard'));
        
        $this->assertDatabaseHas('whatsapp_configs', [
            'tenant_id' => $tenant->id,
            'delay_hours' => 24,
        ]);

        $config = WhatsappConfig::where('tenant_id', $tenant->id)->first();
        $this->assertEquals('خمس نجوم رائع', $config->custom_questions['rating_label_5']);
        $this->assertEquals('يا عميلنا العزيز اختر من فضلك من القائمة المخصصة:', $config->custom_questions['rating_invalid_warning']);

        // Test SendWhatsAppRatingMessageJob uses custom titles
        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendButtons/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'cust-202',
            'name' => 'فهد الحربي',
            'phone' => '966512345672',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'order-202',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2002',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        $config->update(['instance_name' => $instanceName, 'instance_apikey' => 'key', 'status' => 'connected']);

        $job = new SendWhatsAppRatingMessageJob($order);
        $job->handle();

        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345672'
                && str_contains($request['text'], '5 - خمس نجوم رائع');
        });
    }

    /**
     * Test SyncSallaOrdersJob schedules a rating message for a missed webhook order.
     */
    public function test_sync_salla_orders_job_schedules_rating_message_for_missed_webhook(): void
    {
        Queue::fake();

        $tenant = Tenant::create(['name' => 'Merchant A']);
        $whatsappConfig = WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => 'ct_test',
            'instance_apikey' => 'key',
            'status' => 'connected',
            'delay_hours' => 2,
            'custom_questions' => [
                'enable_salla_scanner' => true,
                'salla_scanner_interval_minutes' => 120,
                'salla_scanner_lookback_hours' => 2,
            ]
        ]);

        \App\Models\SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merchant-123',
            'access_token' => 'token-abc',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addDays(14),
        ]);

        // Mock Salla orders API response. Let's return two orders:
        // 1. One delivered 1 hour ago (60 minutes) - within the 120 minutes limit.
        // 2. One delivered 3 hours ago (180 minutes) - outside the 120 minutes limit.
        $deliveredWithinLimit = now()->subHour();
        $deliveredOutsideLimit = now()->subHours(3);

        Http::fake([
            'https://api.salla.dev/admin/v2/orders*' => Http::response([
                'data' => [
                    [
                        'id' => 'ord-9999',
                        'reference_id' => 'INV-9999',
                        'status' => ['name' => 'delivered'],
                        'updated_at' => $deliveredWithinLimit->toDateTimeString(),
                        'amounts' => ['total' => ['amount' => 299.99]],
                        'customer' => [
                            'id' => 'cust-999',
                            'first_name' => 'Fahad',
                            'last_name' => 'Al-Harbi',
                            'mobile' => '+966512345672',
                            'email' => 'fahad@example.com'
                        ]
                    ],
                    [
                        'id' => 'ord-8888',
                        'reference_id' => 'INV-8888',
                        'status' => ['name' => 'delivered'],
                        'updated_at' => $deliveredOutsideLimit->toDateTimeString(),
                        'amounts' => ['total' => ['amount' => 199.99]],
                        'customer' => [
                            'id' => 'cust-888',
                            'first_name' => 'Sara',
                            'last_name' => 'Ahmed',
                            'mobile' => '+966512345673',
                            'email' => 'sara@example.com'
                        ]
                    ]
                ],
                'pagination' => ['links' => []]
            ])
        ]);

        $job = new \App\Jobs\SyncSallaOrdersJob($tenant);
        $job->handle();

        // Bind tenant ID to access scoped DB models
        app()->bind('current_tenant_id', fn () => $tenant->id);

        // Within limit order: should be created and scheduled
        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'ord-9999',
            'rating_message_scheduled' => true,
        ]);

        // Outside limit order: should be created but NOT scheduled
        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'ord-8888',
            'rating_message_scheduled' => false,
        ]);

        // Verify that only the order within limit was scheduled
        Queue::assertPushed(SendWhatsAppRatingMessageJob::class, function ($job) use ($deliveredWithinLimit) {
            return $job->order->salla_order_id === 'ord-9999' 
                && $job->delay !== null 
                && $job->delay > 0 
                && abs($job->delay - 3600) < 10;
        });

        Queue::assertNotPushed(SendWhatsAppRatingMessageJob::class, function ($job) {
            return $job->order->salla_order_id === 'ord-8888';
        });
    }

    /**
     * Test chatbot behavior when Eastern Arabic numerals (٠-٩) are received.
     */
    public function test_whatsapp_chatbot_eastern_arabic_numerals_handling(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        $config = WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
            'custom_questions' => [
                'enable_questions' => true,
                'questions' => [
                    [
                        'id' => 'q_1',
                        'type' => 'text',
                        'text' => 'ما رأيك في المنتج؟',
                    ]
                ]
            ]
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'c_12',
            'name' => 'فهد',
            'phone' => '966512345672',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'o_12',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-12',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        // Scenario 1: Sending Eastern Arabic numeral 5 (٥) -> should successfully extract as 5 and transition to questions
        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345672',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        $payload = $this->createWebhookPayload($instanceName, '966512345672@s.whatsapp.net', 'text', '٥');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        // Session should update rating to 5 and change step to awaiting_question
        $session->refresh();
        $this->assertEquals(5, $session->rating);
        $this->assertEquals('awaiting_question', $session->step);

        // Scenario 2: Sending Eastern Arabic numeral ٧ (7) -> should directly terminate and save null rating
        $session->step = 'awaiting_rating';
        $session->rating = null;
        $session->save();

        $payload2 = $this->createWebhookPayload($instanceName, '966512345672@s.whatsapp.net', 'text', '٧');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload2)->assertStatus(200);

        // Session should be terminated/deleted
        $this->assertNull(WhatsappChatSession::find($session->id));

        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => null,
            'comment' => '7',
        ]);
        
        Http::assertSent(function ($request) use ($instanceName) {
            return str_contains($request->url(), "/message/sendText/{$instanceName}")
                && $request['number'] === '966512345672'
                && $request['text'] === 'سيتم تحويلك لخدمة العملاء الآن لمساعدتك.';
        });
    }

    /**
     * Test chatbot behavior when unexpected text replies or out-of-range numeric ratings (like 7) are received.
     */
    public function test_whatsapp_chatbot_unexpected_replies_are_persisted_as_reviews(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $instanceName = 'ct_' . $tenant->id;

        $config = WhatsappConfig::create([
            'tenant_id' => $tenant->id,
            'instance_name' => $instanceName,
            'instance_apikey' => 'inst-api-key',
            'status' => 'connected',
            'delay_hours' => 0,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'salla_customer_id' => 'c_99',
            'name' => 'فهد',
            'phone' => '966512345672',
        ]);

        $order = Order::create([
            'tenant_id' => $tenant->id,
            'salla_order_id' => 'o_99',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-99',
            'total' => 100.00,
            'status' => 'delivered',
        ]);

        Http::fake([
            "*/message/sendText/{$instanceName}" => Http::response(['status' => 'success'], 200),
            "*/message/sendList/{$instanceName}" => Http::response(['status' => 'success'], 200),
        ]);

        // Scenario 1: Customer replies with non-numeric text directly on first step
        $session = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345672',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        $payload = $this->createWebhookPayload($instanceName, '966512345672@s.whatsapp.net', 'text', 'المتجر ممتاز جداً والتوصيل سريع');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload)->assertStatus(200);

        // Session should be terminated/deleted
        $this->assertNull(WhatsappChatSession::find($session->id));

        // Review should be created in DB with null rating
        app()->bind('current_tenant_id', fn () => $tenant->id);
        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => null,
            'comment' => 'المتجر ممتاز جداً والتوصيل سريع',
        ]);

        // Scenario 2: Customer replies with out-of-range numeric value (like 7)
        // Reset databases
        WhatsappChatSession::truncate();
        Review::truncate();

        $session2 = WhatsappChatSession::create([
            'tenant_id' => $tenant->id,
            'phone' => '966512345672',
            'order_id' => $order->id,
            'step' => 'awaiting_rating',
            'expires_at' => now()->addHours(2),
        ]);

        $payload2 = $this->createWebhookPayload($instanceName, '966512345672@s.whatsapp.net', 'text', '٧');
        $this->postJson('/api/v1/webhooks/whatsapp', $payload2)->assertStatus(200);

        // Session should be terminated/deleted immediately
        $this->assertNull(WhatsappChatSession::find($session2->id));

        // Review should be saved in DB immediately with null rating
        $this->assertDatabaseHas('reviews', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => null,
            'comment' => '7',
        ]);
    }

    /**
     * Helper to create mock Evolution API Webhook payloads.
     */
    protected function createWebhookPayload(string $instanceName, string $senderJid, string $type, string $value): array
    {
        $messageData = [
            'key' => [
                'remoteJid' => $senderJid,
                'fromMe' => false,
                'id' => 'EVO-' . uniqid(),
            ],
            'messageTimestamp' => time(),
        ];

        if ($type === 'text') {
            $messageData['message'] = [
                'conversation' => $value,
            ];
        } elseif ($type === 'list_reply') {
            $messageData['message'] = [
                'listResponseMessage' => [
                    'singleSelectReply' => [
                        'selectedRowId' => $value,
                    ]
                ]
            ];
        } elseif ($type === 'button_reply') {
            $messageData['message'] = [
                'buttonsResponseMessage' => [
                    'selectedButtonId' => $value,
                ]
            ];
        } elseif ($type === 'image') {
            $messageData['message'] = [
                'imageMessage' => [
                    'mimetype' => 'image/png',
                ]
            ];
            $messageData['key']['id'] = $value; // Use the image message ID as the media ID
        }

        return [
            'event' => 'messages.upsert',
            'instance' => $instanceName,
            'data' => $messageData,
        ];
    }
}
