<?php

namespace Tests\Feature;

use App\Jobs\ProcessSallaWebhookJob;
use App\Jobs\SyncSallaOrdersJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SallaConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReviewsManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that order products are correctly parsed and synced to order_product table.
     */
    public function test_salla_orders_sync_populates_order_product_relationship(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Test']);
        SallaConfig::create([
            'tenant_id' => $tenant->id,
            'merchant_id' => 'merch-999',
            'access_token' => 'access-token-999',
            'refresh_token' => 'refresh-token-999',
            'expires_at' => now()->addDays(7),
        ]);

        App::bind('current_tenant_id', fn () => $tenant->id);

        // Mock Salla orders API response containing items/products
        Http::fake([
            'https://api.salla.dev/admin/v2/orders*' => Http::response([
                'data' => [
                    [
                        'id' => 'salla-ord-500',
                        'reference_id' => 'INV-500',
                        'status' => ['name' => 'delivered'],
                        'amounts' => ['total' => ['amount' => 199.99]],
                        'customer' => [
                            'id' => 'cust-500',
                            'first_name' => 'Adel',
                            'last_name' => 'Moniem',
                            'mobile' => '+966505050505',
                            'email' => 'adel@example.com',
                        ],
                        'items' => [
                            [
                                'id' => 'item-1',
                                'product' => [
                                    'id' => 'salla-prod-100',
                                    'name' => 'T-Shirt Green',
                                    'image' => 'https://example.com/green.png',
                                    'urls' => ['customer' => 'https://example.com/green'],
                                ]
                            ],
                            [
                                'id' => 'item-2',
                                'product' => [
                                    'id' => 'salla-prod-200',
                                    'name' => 'Jeans Blue',
                                    'image' => 'https://example.com/jeans.png',
                                    'urls' => ['customer' => 'https://example.com/jeans'],
                                ]
                            ]
                        ]
                    ]
                ],
                'pagination' => ['links' => []]
            ])
        ]);

        // 1. Test Sync Job Mappings
        $job = new SyncSallaOrdersJob($tenant);
        $job->handle();

        $order = Order::where('salla_order_id', 'salla-ord-500')->first();
        $this->assertNotNull($order);

        // Verify products are synced on order
        $this->assertCount(2, $order->products);
        $this->assertTrue($order->products->contains('salla_product_id', 'salla-prod-100'));
        $this->assertTrue($order->products->contains('salla_product_id', 'salla-prod-200'));

        // 2. Test Webhook synchronization mappings
        $webhookPayload = [
            'event' => 'order.status.updated',
            'merchant' => 'merch-999',
            'data' => [
                'id' => 'salla-ord-600',
                'reference_id' => 'INV-600',
                'status' => ['name' => 'delivered'],
                'amounts' => ['total' => ['amount' => 88.00]],
                'customer' => [
                    'id' => 'cust-600',
                    'first_name' => 'Sami',
                    'last_name' => 'Ali',
                    'mobile' => '+966506060606',
                    'email' => 'sami@example.com',
                ],
                'items' => [
                    [
                        'id' => 'item-3',
                        'product' => [
                            'id' => 'salla-prod-300',
                            'name' => 'Shoes Black',
                            'image' => 'https://example.com/shoes.png',
                            'urls' => ['customer' => 'https://example.com/shoes'],
                        ]
                    ]
                ]
            ]
        ];

        $webhookJob = new ProcessSallaWebhookJob($tenant->id, $webhookPayload);
        $webhookJob->handle();

        $webhookOrder = Order::where('salla_order_id', 'salla-ord-600')->first();
        $this->assertNotNull($webhookOrder);
        $this->assertCount(1, $webhookOrder->products);
        $this->assertEquals('salla-prod-300', $webhookOrder->products->first()->salla_product_id);
    }

    /**
     * Test widget API returns only approved reviews for a specific product.
     */
    public function test_widget_endpoint_returns_approved_reviews_only(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Test']);
        App::bind('current_tenant_id', fn () => $tenant->id);

        $customer = Customer::create([
            'salla_customer_id' => 'cust-widget',
            'name' => 'Ahmed Ali',
            'phone' => '+966555555555',
        ]);

        $product = Product::create([
            'salla_product_id' => 'salla-prod-widget',
            'name' => 'Special Cream',
            'image_url' => 'https://example.com/cream.png',
            'product_url' => 'https://example.com/cream',
        ]);

        $order = Order::create([
            'salla_order_id' => 'ord-widget-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-W-1',
            'total' => 150.00,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $order->products()->sync([$product->id]);

        // Review 1: Approved (Should be returned)
        Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Fantastic cream!',
            'status' => 'approved',
            'source' => 'whatsapp',
        ]);

        // Review 2: Pending (Should NOT be returned)
        Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 1,
            'comment' => 'Bad customer support',
            'status' => 'pending',
            'source' => 'whatsapp',
        ]);

        // Release binding to simulate public access
        App::offsetUnset('current_tenant_id');

        // Request Widget data
        $response = $this->getJson('/api/v1/widget/data?product_id=salla-prod-widget');

        $response->assertStatus(200);
        $response->assertJsonPath('product.salla_product_id', 'salla-prod-widget');
        $response->assertJsonPath('rating_stats.count', 1);
        $response->assertJsonPath('rating_stats.average', 5);
        $response->assertJsonCount(1, 'reviews');
        $response->assertJsonPath('reviews.0.comment', 'Fantastic cream!');
    }

    /**
     * Test dashboard reviews management endpoints and tenant isolation.
     */
    public function test_dashboard_reviews_endpoints_and_tenant_isolation(): void
    {
        // 1. Setup Tenant A
        $tenantA = Tenant::create(['name' => 'Merchant A']);
        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'User A',
            'email' => 'userA@example.com',
            'password' => bcrypt('password'),
        ]);

        App::bind('current_tenant_id', fn () => $tenantA->id);
        $customerA = Customer::create([
            'salla_customer_id' => 'cust-a',
            'name' => 'Customer A',
            'phone' => '+966500000001',
        ]);
        $orderA = Order::create([
            'salla_order_id' => 'ord-a',
            'customer_id' => $customerA->id,
            'invoice_number' => 'INV-A',
            'total' => 100.0,
            'status' => 'delivered',
        ]);
        $reviewA = Review::create([
            'order_id' => $orderA->id,
            'customer_id' => $customerA->id,
            'rating' => 4,
            'comment' => 'Review Tenant A',
            'status' => 'pending',
        ]);

        // 2. Setup Tenant B
        $tenantB = Tenant::create(['name' => 'Merchant B']);
        App::bind('current_tenant_id', fn () => $tenantB->id);
        $customerB = Customer::create([
            'salla_customer_id' => 'cust-b',
            'name' => 'Customer B',
            'phone' => '+966500000002',
        ]);
        $orderB = Order::create([
            'salla_order_id' => 'ord-b',
            'customer_id' => $customerB->id,
            'invoice_number' => 'INV-B',
            'total' => 200.0,
            'status' => 'delivered',
        ]);
        $reviewB = Review::create([
            'order_id' => $orderB->id,
            'customer_id' => $customerB->id,
            'rating' => 5,
            'comment' => 'Review Tenant B',
            'status' => 'pending',
        ]);

        // Clean bindings before web calls
        App::offsetUnset('current_tenant_id');

        // Login as User A
        $this->actingAs($userA);

        // Fetch dashboard reviews
        $response = $this->getJson('/reviews');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'reviews');
        $response->assertJsonPath('reviews.0.comment', 'Review Tenant A');

        // Update status of Review A
        $updateResponse = $this->putJson("/reviews/{$reviewA->id}/status", [
            'status' => 'approved',
        ]);
        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('reviews', [
            'id' => $reviewA->id,
            'status' => 'approved',
        ]);

        // Attempting to update Review B (Tenant B) from User A session should fail (404 due to tenant scoping)
        $unauthorizedResponse = $this->putJson("/reviews/{$reviewB->id}/status", [
            'status' => 'approved',
        ]);
        $unauthorizedResponse->assertStatus(404);

        // Delete Review A
        $deleteResponse = $this->deleteJson("/reviews/{$reviewA->id}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('reviews', ['id' => $reviewA->id]);
    }

    /**
     * Test merchant can add and delete replies.
     */
    public function test_merchant_can_reply_and_delete_reply_to_review(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Test']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        App::bind('current_tenant_id', fn () => $tenant->id);
        $customer = Customer::create([
            'salla_customer_id' => 'cust-1',
            'name' => 'Customer',
            'phone' => '+966500000001',
        ]);
        $order = Order::create([
            'salla_order_id' => 'ord-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-1',
            'total' => 100.0,
            'status' => 'delivered',
        ]);
        $review = Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'comment' => 'Great product',
            'status' => 'approved',
        ]);
        App::offsetUnset('current_tenant_id');

        $this->actingAs($user);

        // Submit Reply
        $replyText = 'Thank you for your feedback!';
        $response = $this->postJson("/reviews/{$review->id}/reply", [
            'reply' => $replyText,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('review.reply', $replyText);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'reply' => $replyText,
        ]);

        // Delete Reply
        $deleteResponse = $this->postJson("/reviews/{$review->id}/reply", [
            'reply' => null,
        ]);

        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJsonPath('review.reply', null);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'reply' => null,
        ]);
    }

    /**
     * Test merchant cannot reply to other merchant's review.
     */
    public function test_merchant_cannot_reply_to_other_merchants_review(): void
    {
        // Merchant A
        $tenantA = Tenant::create(['name' => 'Merchant A']);
        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'User A',
            'email' => 'userA@example.com',
            'password' => bcrypt('password'),
        ]);

        // Merchant B
        $tenantB = Tenant::create(['name' => 'Merchant B']);
        App::bind('current_tenant_id', fn () => $tenantB->id);
        $customerB = Customer::create([
            'salla_customer_id' => 'cust-b',
            'name' => 'Customer B',
            'phone' => '+966500000002',
        ]);
        $orderB = Order::create([
            'salla_order_id' => 'ord-b',
            'customer_id' => $customerB->id,
            'invoice_number' => 'INV-B',
            'total' => 200.0,
            'status' => 'delivered',
        ]);
        $reviewB = Review::create([
            'order_id' => $orderB->id,
            'customer_id' => $customerB->id,
            'rating' => 5,
            'comment' => 'Review Tenant B',
            'status' => 'approved',
        ]);
        App::offsetUnset('current_tenant_id');

        // Acting as User A
        $this->actingAs($userA);

        $response = $this->postJson("/reviews/{$reviewB->id}/reply", [
            'reply' => 'Unauthorized reply',
        ]);

        // Should return 404 because of TenantScope
        $response->assertStatus(404);
    }

    /**
     * Test merchant can export reviews as CSV.
     */
    public function test_merchant_can_export_reviews_csv(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Test']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        App::bind('current_tenant_id', fn () => $tenant->id);
        $customer = Customer::create([
            'salla_customer_id' => 'cust-1',
            'name' => 'Ahmed Mohamed',
            'phone' => '+966500000001',
        ]);
        $order = Order::create([
            'salla_order_id' => 'ord-1',
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-1234',
            'total' => 100.0,
            'status' => 'delivered',
        ]);
        $review = Review::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'comment' => 'Excellent quality product',
            'status' => 'approved',
        ]);
        App::offsetUnset('current_tenant_id');

        $this->actingAs($user);

        $response = $this->get("/reviews/export");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        
        // Assert CSV headers and contents are present
        $this->assertStringContainsString('الاسم', $content);
        $this->assertStringContainsString('التقييم', $content);
        $this->assertStringContainsString('التعليق', $content);
        $this->assertStringContainsString('Ahmed Mohamed', $content);
        $this->assertStringContainsString('Excellent quality product', $content);
    }
}

