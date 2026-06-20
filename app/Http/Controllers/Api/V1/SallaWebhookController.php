<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSallaWebhookJob;
use App\Models\SallaConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SallaWebhookController extends Controller
{
    /**
     * Handles incoming Salla webhooks, validates payloads, and dispatches processing jobs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Salla-Signature');
        $payload = $request->getContent();

        if (empty($signature) || empty($payload)) {
            Log::warning('Salla webhook received with missing signature or payload.');
            return response()->json(['error' => 'Missing signature header or request body.'], 400);
        }

        try {
            $eventData = json_decode($payload, true);
            $merchantId = $eventData['merchant'] ?? null;

            if (empty($merchantId)) {
                Log::warning('Salla webhook payload missing merchant reference.', [
                    'payload' => substr($payload, 0, 200),
                ]);
                return response()->json(['error' => 'Invalid webhook payload structure.'], 400);
            }

            // Retrieve merchant configuration bypassing global query filters
            $config = SallaConfig::withoutGlobalScopes()
                ->where('merchant_id', (string) $merchantId)
                ->first();

            if (empty($config)) {
                Log::warning('Salla webhook received for unregistered merchant ID', [
                    'merchant_id' => $merchantId,
                ]);
                // Returning 200 to prevent Salla from retrying obsolete payloads repeatedly
                return response()->json(['status' => 'ignored'], 200);
            }

            // Verify webhook signature if secret key has been configured
            if (!empty($config->webhook_secret)) {
                $calculatedSignature = hash_hmac('sha256', $payload, $config->webhook_secret);
                if (!hash_equals($calculatedSignature, $signature)) {
                    Log::error('Salla webhook signature verification mismatch', [
                        'merchant_id' => $merchantId,
                        'received' => $signature,
                    ]);
                    return response()->json(['error' => 'Signature verification failure.'], 401);
                }
            }

            // Dispatching background job to execute asynchronously and free up request flow
            ProcessSallaWebhookJob::dispatch($config->tenant_id, $eventData);

            return response()->json(['status' => 'accepted'], 200);
        } catch (\Exception $exception) {
            Log::error('Exception triggered during Salla webhook handling', [
                'message' => $exception->getMessage(),
            ]);
            return response()->json(['error' => 'Internal server error processing webhook.'], 500);
        }
    }
}
