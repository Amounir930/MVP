<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that non-admin users cannot access the admin dashboard.
     */
    public function test_non_admin_users_are_forbidden_from_admin_overview(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Tenant', 'status' => 'active']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)->get('/superadmin');

        $response->assertStatus(403);
    }

    /**
     * Test that admin users can access the admin dashboard.
     */
    public function test_admin_users_can_access_admin_overview(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Tenant', 'status' => 'active']);
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->get('/superadmin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Overview')
            ->has('stats')
            ->has('stores')
        );
    }

    /**
     * Test that admin users can toggle the status of a store.
     */
    public function test_admin_users_can_toggle_store_status(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant Tenant', 'status' => 'active']);
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        // Verify initial status is active (default is active in migrations)
        $this->assertEquals('active', $tenant->status);

        // Toggle to suspended
        $response = $this->actingAs($admin)->postJson("/superadmin/stores/{$tenant->id}/toggle");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'suspended',
        ]);

        $tenant->refresh();
        $this->assertEquals('suspended', $tenant->status);

        // Toggle back to active
        $response = $this->actingAs($admin)->postJson("/superadmin/stores/{$tenant->id}/toggle");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'active',
        ]);

        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);
    }
}
