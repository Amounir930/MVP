<?php

namespace App\Integration\Drivers;

use App\Integration\Contracts\MessagingServiceInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudAPIDriver implements MessagingServiceInterface
{
    /**
     * Send automated template message via WhatsApp Cloud API.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $templateName
     * @param  array  $parameters
     * @return bool
     */
    public function sendTemplateMessage(Tenant $tenant, string $to, string $templateName, array $parameters): bool
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->access_token) || empty($config->phone_number_id)) {
            Log::error('WhatsApp configuration is missing or incomplete for tenant', ['tenant_id' => $tenant->id]);
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($param) => ['type' => 'text', 'text' => (string) $param], $parameters),
            ];
        }

        try {
            $response = Http::withToken($config->access_token)
                ->post("https://graph.facebook.com/v20.0/{$config->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => [
                            'code' => 'ar',
                        ],
                        'components' => $components,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp Cloud API sendTemplateMessage failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during WhatsApp sendTemplateMessage', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sends quick-reply button messages via WhatsApp Cloud API.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $bodyText
     * @param  array  $buttons
     * @return bool
     */
    public function sendInteractiveButtons(Tenant $tenant, string $to, string $bodyText, array $buttons): bool
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->access_token) || empty($config->phone_number_id)) {
            Log::error('WhatsApp configuration is missing or incomplete for tenant', ['tenant_id' => $tenant->id]);
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $formattedButtons = [];
        foreach (array_slice($buttons, 0, 3) as $key => $button) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string) ($button['id'] ?? $key),
                    'title' => (string) ($button['title'] ?? $button),
                ]
            ];
        }

        try {
            $response = Http::withToken($config->access_token)
                ->post("https://graph.facebook.com/v20.0/{$config->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $bodyText,
                        ],
                        'action' => [
                            'buttons' => $formattedButtons,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp Cloud API sendInteractiveButtons failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during WhatsApp sendInteractiveButtons', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sends a list message via WhatsApp Cloud API when more than 3 options are needed.
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
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->access_token) || empty($config->phone_number_id)) {
            Log::error('WhatsApp configuration is missing or incomplete for tenant', ['tenant_id' => $tenant->id]);
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        try {
            $response = Http::withToken($config->access_token)
                ->post("https://graph.facebook.com/v20.0/{$config->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'body' => [
                            'text' => $bodyText,
                        ],
                        'action' => [
                            'button' => $buttonLabel,
                            'sections' => [
                                [
                                    'title' => 'الخيارات',
                                    'rows' => $rows,
                                ]
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp Cloud API sendInteractiveList failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during WhatsApp sendInteractiveList', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sends a simple text message via WhatsApp Cloud API.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $to
     * @param  string  $bodyText
     * @return bool
     */
    public function sendTextMessage(Tenant $tenant, string $to, string $bodyText): bool
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->access_token) || empty($config->phone_number_id)) {
            Log::error('WhatsApp configuration is missing or incomplete for tenant', ['tenant_id' => $tenant->id]);
            return false;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        try {
            $response = Http::withToken($config->access_token)
                ->post("https://graph.facebook.com/v20.0/{$config->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'text',
                    'text' => [
                        'body' => $bodyText,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp Cloud API sendTextMessage failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exception during WhatsApp sendTextMessage', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve and download media resource location from WhatsApp Cloud API.
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  string  $mediaId
     * @return string|null
     */
    public function getMediaUrl(Tenant $tenant, string $mediaId): ?string
    {
        $config = $tenant->whatsappConfig;
        if (empty($config) || empty($config->access_token)) {
            Log::error('WhatsApp configuration access token missing for media download', ['tenant_id' => $tenant->id]);
            return null;
        }

        try {
            $detailsResponse = Http::withToken($config->access_token)
                ->get("https://graph.facebook.com/v20.0/{$mediaId}");

            if ($detailsResponse->failed()) {
                Log::error('Failed to retrieve media details from WhatsApp API', [
                    'tenant_id' => $tenant->id,
                    'media_id' => $mediaId,
                    'status' => $detailsResponse->status(),
                ]);
                return null;
            }

            $url = $detailsResponse->json('url');
            $mimeType = $detailsResponse->json('mime_type');
            
            if (empty($url)) {
                Log::error('WhatsApp API response missing download URL', [
                    'tenant_id' => $tenant->id,
                    'media_id' => $mediaId,
                ]);
                return null;
            }

            $extension = 'jpg';
            if (str_contains($mimeType, 'png')) {
                $extension = 'png';
            } elseif (str_contains($mimeType, 'gif')) {
                $extension = 'gif';
            } elseif (str_contains($mimeType, 'webp')) {
                $extension = 'webp';
            } elseif (str_contains($mimeType, 'mp4')) {
                $extension = 'mp4';
            } elseif (str_contains($mimeType, 'quicktime')) {
                $extension = 'mov';
            }

            $fileResponse = Http::withToken($config->access_token)->get($url);

            if ($fileResponse->failed()) {
                Log::error('Failed to download media binary from WhatsApp API', [
                    'tenant_id' => $tenant->id,
                    'url' => $url,
                ]);
                return null;
            }

            $binaryContent = $fileResponse->body();
            $filename = $mediaId . '.' . $extension;

            $relativeDir = "uploads/reviews/{$tenant->id}";
            $destinationDir = public_path($relativeDir);
            if (!file_exists($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }

            $filePath = $destinationDir . '/' . $filename;
            file_put_contents($filePath, $binaryContent);

            return '/' . $relativeDir . '/' . $filename;
        } catch (\Exception $e) {
            Log::error('Exception during WhatsApp media download', [
                'tenant_id' => $tenant->id,
                'media_id' => $mediaId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
