<?php

namespace App\Integration\Contracts;

use App\Models\Tenant;

interface PlatformIntegrationInterface
{
    /**
     * Synchronize products from the merchant platform.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function syncProducts(Tenant $tenant): void;

    /**
     * Synchronize orders from the merchant platform.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function syncOrders(Tenant $tenant): void;

    /**
     * Inject custom widget script into the merchant platform pages.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $scriptUrl
     * @return bool
     */
    public function injectWidget(Tenant $tenant, string $scriptUrl): bool;
}
