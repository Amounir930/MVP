<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Integration\Contracts\PlatformIntegrationInterface;
use App\Integration\Contracts\MessagingServiceInterface;
use App\Events\CartUpdated;
use App\Events\ReviewReceived;

class ArchitectureSetupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifies that the required modular interface contracts are declared and loadable.
     */
    public function test_modular_integration_interfaces_exist(): void
    {
        $this->assertTrue(interface_exists(PlatformIntegrationInterface::class));
        $this->assertTrue(interface_exists(MessagingServiceInterface::class));
    }

    /**
     * Verifies that the required event dispatchers for future modular extensions exist.
     */
    public function test_future_extension_events_exist(): void
    {
        $this->assertTrue(class_exists(CartUpdated::class));
        $this->assertTrue(class_exists(ReviewReceived::class));
    }

    /**
     * Verifies that database relationships between Tenants and Users are mapped correctly.
     */
    public function test_tenant_user_database_relationships(): void
    {
        $tenant = Tenant::create([
            'name' => 'Merchant Architecture Test Store',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Architecture Test User',
            'email' => 'archtest@example.com',
            'password' => bcrypt('password'),
            'role' => 'merchant',
        ]);

        $this->assertTrue($tenant->users->contains($user));
        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    /**
     * Verifies that the frontend Vite compilation artifacts are generated and present.
     */
    public function test_frontend_build_artifacts_present(): void
    {
        $manifestPath = base_path('public/build/manifest.json');
        $tailwindConfigPath = base_path('tailwind.config.js');

        $this->assertFileExists($tailwindConfigPath);
        $this->assertFileExists($manifestPath);
    }
}
