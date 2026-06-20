<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies that the Tenant model automatically generates a UUID key on creation.
     */
    public function test_tenant_creation_generates_uuid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Merchant A',
        ]);

        $this->assertNotNull($tenant->id);
        $this->assertTrue(Str::isUuid($tenant->id));
        $this->assertEquals('Test Merchant A', $tenant->name);
    }

    /**
     * Verifies that authenticated users are only allowed to see user entities within their own tenant.
     */
    public function test_query_filtering_by_authenticated_user_tenant_scope(): void
    {
        // Set up Tenant A and corresponding user
        $tenantA = Tenant::create(['name' => 'Merchant A']);
        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        // Set up Tenant B and corresponding user
        $tenantB = Tenant::create(['name' => 'Merchant B']);
        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        // Act as User A and verify database query scope isolation
        $this->actingAs($userA);
        $usersForA = User::all();

        $this->assertCount(1, $usersForA);
        $this->assertTrue($usersForA->contains($userA));
        $this->assertFalse($usersForA->contains($userB));

        // Act as User B and verify database query scope isolation
        $this->actingAs($userB);
        $usersForB = User::all();

        $this->assertCount(1, $usersForB);
        $this->assertTrue($usersForB->contains($userB));
        $this->assertFalse($usersForB->contains($userA));
    }

    /**
     * Verifies that the scope filters queries correctly when a tenant context is bound to the application container.
     */
    public function test_query_filtering_by_explicit_container_tenant_binding(): void
    {
        $tenantA = Tenant::create(['name' => 'Merchant A']);
        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => bcrypt('password'),
        ]);

        $tenantB = Tenant::create(['name' => 'Merchant B']);
        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
        ]);

        // Explicitly bind Tenant A UUID to the application container
        app()->bind('current_tenant_id', fn () => $tenantA->id);

        $results = User::all();
        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($userA));
        $this->assertFalse($results->contains($userB));

        // Update container binding to Tenant B UUID
        app()->bind('current_tenant_id', fn () => $tenantB->id);

        $results = User::all();
        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($userB));
        $this->assertFalse($results->contains($userA));
    }

    /**
     * Verifies that creating a model under active tenant context automatically assigns the tenant ID.
     */
    public function test_automatic_tenant_id_assignment_during_model_creation(): void
    {
        $tenant = Tenant::create(['name' => 'Merchant A']);
        $userA = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($userA);

        // Create a new user without declaring tenant_id parameter
        $newUser = User::create([
            'name' => 'Automated User',
            'email' => 'auto@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertNotNull($newUser->tenant_id);
        $this->assertEquals($tenant->id, $newUser->tenant_id);
    }
}
