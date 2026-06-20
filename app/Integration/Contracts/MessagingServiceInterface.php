<?php

namespace App\Integration\Contracts;

use App\Models\Tenant;

interface MessagingServiceInterface
{
    /**
     * Send automated template message via messaging provider client.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $templateName
     * @param  array  $parameters
     * @return bool
     */
    public function sendTemplateMessage(Tenant $tenant, string $to, string $templateName, array $parameters): bool;

    /**
     * Retrieve and download media resource location from messaging provider.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $mediaId
     * @return string|null
     */
    public function getMediaUrl(Tenant $tenant, string $mediaId): ?string;
}
