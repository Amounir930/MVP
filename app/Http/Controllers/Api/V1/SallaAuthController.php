<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SallaConfig;
use App\Services\SallaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SallaAuthController extends Controller
{
    protected SallaOAuthClient $oauthClient;

    /**
     * Initializes the controller with the Salla OAuth client service.
     *
     * @param  \App\Services\SallaOAuthClient  $oauthClient
     */
    public function __construct(SallaOAuthClient $oauthClient)
    {
        $this->oauthClient = $oauthClient;
    }

    /**
     * Redirects the merchant to the Salla authorization gateway.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            $state = Str::random(40);
            $request->session()->put('salla_oauth_state', $state);

            $authUrl = $this->oauthClient->getAuthorizationUrl($state);

            return redirect()->away($authUrl);
        } catch (\Exception $exception) {
            Log::error('Salla OAuth redirect execution failure', [
                'message' => $exception->getMessage(),
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to initiate integration redirection.');
        }
    }

    /**
     * Handles the callback from Salla, exchanging authorization code for credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $code = $request->query('code');
            $state = $request->query('state');

            // Input Validation
            if (empty($code) || empty($state)) {
                throw new InvalidArgumentException('Missing authorization code or state parameter.');
            }

            // CSRF State Token Validation
            $sessionState = $request->session()->pull('salla_oauth_state');
            if (empty($sessionState) || $state !== $sessionState) {
                Log::warning('Salla OAuth CSRF state verification failure', [
                    'received' => $state,
                    'expected' => $sessionState,
                ]);
                throw new RuntimeException('Security verification state token mismatch.');
            }

            // Exchanging authorization code for API tokens
            $tokens = $this->oauthClient->exchangeCodeForTokens($code);
            $merchant = $this->oauthClient->getMerchantDetails($tokens['access_token']);

            $merchantId = $merchant['data']['merchant']['id'] ?? $merchant['data']['id'] ?? $merchant['data']['merchant_id'] ?? null;
            if (empty($merchantId)) {
                throw new RuntimeException('Failed to extract merchant ID from profile metadata.');
            }

            // Storing the credentials associated with the authenticated user tenant context
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new RuntimeException('User is not associated with any active tenant context.');
            }

            SallaConfig::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'merchant_id' => (string) $merchantId,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_at' => now()->addSeconds((int) $tokens['expires_in']),
                    'webhook_secret' => config('services.salla.webhook_secret'),
                ]
            );

            // Start background synchronization
            $driver = new \App\Integration\Drivers\SallaDriver();
            $driver->syncProducts($tenant);
            $driver->syncOrders($tenant);

            // Automatically inject the storefront widget loader script into Salla store
            $scriptUrl = url('js/widget-loader.js');
            $driver->injectWidget($tenant, $scriptUrl);

            return redirect()->route('dashboard')->with('success', 'Salla integration established successfully.');
        } catch (\Exception $exception) {
            Log::error('Salla OAuth callback processing failure', [
                'message' => $exception->getMessage(),
                'query_params' => request()->query(),
            ]);
            return redirect()->route('dashboard')->with('error', 'Integration authentication failed: ' . $exception->getMessage());
        }
    }

    /**
     * Disconnects the Salla integration for the authenticated user tenant
     * and deletes all associated store data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function disconnect(Request $request): RedirectResponse
    {
        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new RuntimeException('User is not associated with any active tenant context.');
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($tenant) {
                // Delete config
                $tenant->sallaConfig()->delete();
                
                // Delete all data associated with this tenant
                $tenant->products()->delete();
                $tenant->orders()->delete();
                $tenant->customers()->delete();
            });

            return redirect()->route('dashboard')->with('success', 'Salla integration disconnected and all associated store data deleted successfully.');
        } catch (\Exception $exception) {
            Log::error('Salla disconnect execution failure', [
                'message' => $exception->getMessage(),
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to disconnect integration: ' . $exception->getMessage());
        }
    }

    /**
     * Triggers manual synchronization of Salla products and orders in the background.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sync(Request $request): RedirectResponse
    {
        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new RuntimeException('User is not associated with any active tenant context.');
            }

            $sallaConfig = $tenant->sallaConfig;
            if (empty($sallaConfig) || empty($sallaConfig->access_token)) {
                throw new RuntimeException('Salla integration is not established.');
            }

            // Dispatch background synchronization jobs
            $driver = new \App\Integration\Drivers\SallaDriver();
            $driver->syncProducts($tenant);
            $driver->syncOrders($tenant);

            // Update scan run timestamp cache
            \Illuminate\Support\Facades\Cache::put('last_salla_scanner_run_' . $tenant->id, now()->toDateTimeString());

            return redirect()->route('dashboard')->with('success', 'Manual synchronization queued successfully.');
        } catch (\Exception $exception) {
            Log::error('Salla manual sync initiation failure', [
                'message' => $exception->getMessage(),
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to initiate synchronization: ' . $exception->getMessage());
        }
    }
}

