<?php

namespace App\Integration\Drivers;

use App\Integration\Contracts\PlatformIntegrationInterface;
use App\Jobs\SyncSallaProductsJob;
use App\Jobs\SyncSallaOrdersJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SallaDriver implements PlatformIntegrationInterface
{
    /**
     * Dispatches the asynchronous background job to synchronize products from Salla.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function syncProducts(Tenant $tenant): void
    {
        SyncSallaProductsJob::dispatch($tenant);
    }

    /**
     * Dispatches the asynchronous background job to synchronize orders from Salla.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function syncOrders(Tenant $tenant): void
    {
        SyncSallaOrdersJob::dispatch($tenant);
    }

    /**
     * Injects the custom widget script loader directly into Salla storefront pages.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $scriptUrl
     * @return bool
     * @throws \RuntimeException
     */
    public function injectWidget(Tenant $tenant, string $scriptUrl): bool
    {
        $config = $tenant->sallaConfig;
        if (empty($config)) {
            Log::error('Salla configuration missing for tenant script injection', ['tenant_id' => $tenant->id]);
            return false;
        }

        try {
            $response = Http::withToken($config->access_token)->post('https://api.salla.dev/admin/v2/merchants/scripts', [
                'name' => 'Conversion Trust Widget Loader',
                'src' => $scriptUrl,
                'type' => 'footer',
                'active' => true,
            ]);

            if ($response->failed()) {
                Log::error('Failed to inject custom widget script into Salla store', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $exception) {
            Log::error('Exception during Salla widget injection execution', [
                'tenant_id' => $tenant->id,
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
