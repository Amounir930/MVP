<?php

namespace App\Integration\Drivers;

use App\Integration\Contracts\MessagingServiceInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionAPIDriver implements MessagingServiceInterface
{
    /**
     * Create an isolated instance for the tenant in Evolution API.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return array|null
     */
    public function createInstance(Tenant $tenant): ?array
    {
        $instanceName = 'ct_' . $tenant->id;
        $apiUrl = config('services.evolution.url');
        $globalKey = config('services.evolution.api_key');

        if (empty($apiUrl) || empty($globalKey)) {
            Log::error('Evolution API configurations are missing from .env.', ['tenant_id' => $tenant->id]);
            return null;
        }

        try {
            $response = Http::withHeaders(['apikey' => $globalKey])
                ->timeout(10)
                ->post("{$apiUrl}/instance/create", [
                    'instanceName' => $instanceName,
                    'integration' => 'WHATSAPP-BAILEYS',
                    'qrcode' => true,
                ]);

            // Handle zombie instances that exist on Evolution API but not in our database
            if ($response->status() === 403 && str_contains($response->body(), 'already in use')) {
                Log::warning('Evolution API instance already exists on remote server. Deleting and recreating.', [
                    'instance' => $instanceName,
                ]);

                // Try to log out first to cleanly close any active sockets
                try {
                    Http::withHeaders(['apikey' => $globalKey])
                        ->timeout(5)
                        ->delete("{$apiUrl}/instance/logout/{$instanceName}");
                } catch (\Exception $e) {
                    // Ignore logout errors for zombie instances
                }

                // Delete the instance
                try {
                    Http::withHeaders(['apikey' => $globalKey])
                        ->timeout(10)
                        ->delete("{$apiUrl}/instance/delete/{$instanceName}");
                } catch (\Exception $e) {
                    Log::error('Failed to delete zombie instance.', [
                        'instance' => $instanceName,
                        'message' => $e->getMessage(),
                    ]);
                }

                // Wait 2 seconds for Evolution API to release database and resource locks
                sleep(2);

                // Retry instance creation
                $response = Http::withHeaders(['apikey' => $globalKey])
                    ->timeout(10)
                    ->post("{$apiUrl}/instance/create", [
                        'instanceName' => $instanceName,
                        'integration' => 'WHATSAPP-BAILEYS',
                        'qrcode' => true,
                    ]);
            }

            if ($response->failed()) {
                Log::error('Evolution API createInstance failed.', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return null;
            }

            $data = $response->json();
            $qrcode = $data['qrcode']['base64'] ?? null;
            // The instance apiKey returned by Evolution API to control this instance specifically
            $instanceToken = is_array($data['hash'] ?? null)
                ? ($data['hash']['apikey'] ?? null)
                : ($data['hash'] ?? null);

            // Store instance name, token, and set status to disconnected
            \App\Models\WhatsappConfig::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'instance_name' => $instanceName,
                    'instance_apikey' => $instanceToken,
                    'status' => 'disconnected',
                ]
            );

            // Set up webhooks for this instance to receive events
            if ($instanceToken) {
                $this->setWebhook($tenant, $instanceToken);
            }

            return [
                'qrcode' => $qrcode,
                'status' => 'disconnected',
            ];
        } catch (\Exception $e) {
            Log::error('Exception during Evolution API createInstance.', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Configure Evolution API webhook for the specific instance.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $instanceToken
     * @return bool
     */
    public function setWebhook(Tenant $tenant, string $instanceToken): bool
    {
        $instanceName = 'ct_' . $tenant->id;
        $apiUrl = config('services.evolution.url');

        try {
            // Determine internal or public webhook URL.
            // If running in docker and EVOLUTION_API_URL is using a docker hostname like 'evolution',
            // we can route the webhook to the 'app' service directly over the docker bridge network to maximize speed.
            if (str_contains($apiUrl, 'evolution')) {
                $webhookUrl = 'http://app:8082/api/v1/webhooks/whatsapp';
            } else {
                $webhookUrl = config('app.url') . '/api/v1/webhooks/whatsapp';
            }

            $response = Http::withHeaders(['apikey' => $instanceToken])
                ->timeout(5)
                ->post("{$apiUrl}/webhook/set/{$instanceName}", [
                    'webhook' => [
                        'enabled' => true,
                        'url' => $webhookUrl,
                        'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Evolution API setWebhook failed.', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during Evolution API setWebhook.', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve the connection state of the instance.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return string
     */
    public function getConnectionState(Tenant $tenant): string
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->instance_name)) {
            return 'disconnected';
        }

        $apiUrl = config('services.evolution.url');
        $globalKey = config('services.evolution.api_key');

        try {
            $response = Http::withHeaders(['apikey' => $globalKey])
                ->timeout(3)
                ->get("{$apiUrl}/instance/connectionState/{$config->instance_name}");

            if ($response->failed()) {
                return 'disconnected';
            }

            $state = $response->json('instance.state') ?? 'disconnected';
            $mappedStatus = ($state === 'open' || $state === 'connected') ? 'connected' : 'disconnected';

            if ($config->status !== $mappedStatus) {
                $config->update(['status' => $mappedStatus]);
            }

            return $mappedStatus;
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    /**
     * Logout and delete the instance.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return bool
     */
    public function disconnectInstance(Tenant $tenant): bool
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->instance_name)) {
            return true;
        }

        $apiUrl = config('services.evolution.url');
        $globalKey = config('services.evolution.api_key');

        // 1. Try to log out the WhatsApp session first, so the phone unlinks the device
        try {
            Http::withHeaders(['apikey' => $globalKey])
                ->timeout(5)
                ->delete("{$apiUrl}/instance/logout/{$config->instance_name}");
        } catch (\Exception $e) {
            Log::warning('Evolution API instance logout failed, proceeding to delete.', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
        }

        // 2. Delete the instance from the server to clean up resources
        try {
            Http::withHeaders(['apikey' => $globalKey])
                ->timeout(5)
                ->delete("{$apiUrl}/instance/delete/{$config->instance_name}");
        } catch (\Exception $e) {
            Log::error('Exception during Evolution API disconnectInstance request, continuing with local config deletion.', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
        }

        $config->delete();
        return true;
    }

    /**
     * Send template message mapped to simple text using parameters.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $templateName
     * @param  array  $parameters
     * @return bool
     */
    public function sendTemplateMessage(Tenant $tenant, string $to, string $templateName, array $parameters): bool
    {
        // For Evolution API, since it allows sending arbitrary text directly, we can
        // map template name to actual arabic text for the merchant.
        $text = "مرحباً، شكراً لتعاملك معنا! يرجى التقييم.";
        if ($templateName === 'rating_message' && !empty($parameters)) {
            $text = "مرحباً " . ($parameters[0] ?? '') . "، شكراً لتعاملك مع متجرنا! يسعدنا جداً تقييمك لطلبك رقم " . ($parameters[1] ?? '') . ". كيف تقيم تجربتك معنا؟";
        }

        return $this->sendTextMessage($tenant, $to, $text);
    }

    /**
     * Send simple text message.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $bodyText
     * @return bool
     */
    public function sendTextMessage(Tenant $tenant, string $to, string $bodyText): bool
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->instance_name) || empty($config->instance_apikey)) {
            Log::error('Evolution API config missing for sending message.', ['tenant_id' => $tenant->id]);
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);
        if (str_starts_with($cleanPhone, '00')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $apiUrl = config('services.evolution.url');

        try {
            $response = Http::withHeaders(['apikey' => $config->instance_apikey])
                ->timeout(15)
                ->post("{$apiUrl}/message/sendText/{$config->instance_name}", [
                    'number' => $cleanPhone,
                    'text' => $bodyText,
                ]);

            if ($response->failed()) {
                Log::error('Evolution API sendText failed.', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during Evolution API sendText.', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send quick-reply button messages.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $bodyText
     * @param  array  $buttons
     * @return bool
     */
    public function sendInteractiveButtons(Tenant $tenant, string $to, string $bodyText, array $buttons): bool
    {
        $textRepresentation = $bodyText . "\n";
        foreach ($buttons as $idx => $button) {
            $textRepresentation .= "\n" . ($idx + 1) . " - " . ($button['title'] ?? $button);
        }

        return $this->sendTextMessage($tenant, $to, $textRepresentation);
    }

    /**
     * Send a select list message.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $bodyText
     * @param  string  $buttonLabel
     * @param  array  $rows
     * @return bool
     */
    public function sendInteractiveList(Tenant $tenant, string $to, string $bodyText, string $buttonLabel, array $rows): bool
    {
        $textRepresentation = $bodyText . "\n";
        foreach ($rows as $row) {
            $textRepresentation .= "\n" . ($row['id']) . " - " . ($row['title']);
        }

        return $this->sendTextMessage($tenant, $to, $textRepresentation);
    }

    /**
     * Retrieve and download media from Evolution API.
     * In Evolution API, we send a request to get base64 and save it to public storage.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $mediaId
     * @return string|null
     */
    public function getMediaUrl(Tenant $tenant, string $mediaId): ?string
    {
        // Evolution API provides media files by sending a Base64 request of the message key
        $config = $tenant->whatsappConfig ?? \App\Models\WhatsappConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        if (empty($config) || empty($config->instance_name) || empty($config->instance_apikey)) {
            return null;
        }

        $apiUrl = config('services.evolution.url');

        try {
            // Retrieve base64 representation of the media message
            $response = Http::withHeaders(['apikey' => $config->instance_apikey])
                ->timeout(15)
                ->post("{$apiUrl}/chat/getBase64FromMediaMessage/{$config->instance_name}", [
                    'message' => [
                        'key' => [
                            'id' => $mediaId,
                        ]
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Evolution API getBase64FromMediaMessage failed.', [
                    'tenant_id' => $tenant->id,
                    'media_id' => $mediaId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $base64Data = $response->json('base64');
            if (empty($base64Data)) {
                return null;
            }

            // Detect extension from data URL if formatted, or assume jpeg
            $extension = 'jpg';
            if (str_contains($base64Data, ';base64,')) {
                $parts = explode(';base64,', $base64Data);
                $header = $parts[0];
                $base64Data = $parts[1];

                if (str_contains($header, 'png')) {
                    $extension = 'png';
                } elseif (str_contains($header, 'gif')) {
                    $extension = 'gif';
                } elseif (str_contains($header, 'webp')) {
                    $extension = 'webp';
                } elseif (str_contains($header, 'mp4')) {
                    $extension = 'mp4';
                }
            }

            $binaryData = base64_decode($base64Data);
            $filename = $mediaId . '.' . $extension;

            $relativeDir = "uploads/reviews/{$tenant->id}";
            $destinationDir = public_path($relativeDir);
            if (!file_exists($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            $filePath = $destinationDir . '/' . $filename;
            file_put_contents($filePath, $binaryData);

            return '/' . $relativeDir . '/' . $filename;
        } catch (\Exception $e) {
            Log::error('Exception during Evolution API media download.', [
                'tenant_id' => $tenant->id,
                'media_id' => $mediaId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
