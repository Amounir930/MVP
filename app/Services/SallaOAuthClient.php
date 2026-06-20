<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SallaOAuthClient
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected string $authUrl = 'https://accounts.salla.sa/oauth2/auth';
    protected string $tokenUrl = 'https://accounts.salla.sa/oauth2/token';
    protected string $userInfoUrl = 'https://accounts.salla.sa/oauth2/user/info';

    /**
     * Initializes the Salla OAuth client using configuration references.
     */
    public function __construct()
    {
        $this->clientId = config('services.salla.client_id') ?? '';
        $this->clientSecret = config('services.salla.client_secret') ?? '';
        $this->redirectUri = config('services.salla.redirect_uri') ?? '';
    }

    /**
     * Constructs the redirect URL to authorize the merchant.
     *
     * @param  string  $state
     * @return string
     */
    public function getAuthorizationUrl(string $state): string
    {
        $queries = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => 'offline_access',
        ]);

        return "{$this->authUrl}?{$queries}";
    }

    /**
     * Exchanges the authorization code for access and refresh tokens.
     *
     * @param  string  $code
     * @return array
     * @throws \RuntimeException
     */
    public function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->failed()) {
                Log::error('Salla OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Failed to exchange Salla authorization code.');
            }

            return $response->json();
        } catch (\Exception $exception) {
            Log::error('Exception during Salla token exchange', [
                'message' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Error executing token exchange: ' . $exception->getMessage());
        }
    }

    /**
     * Refreshes an expired access token using the refresh token.
     *
     * @param  string  $refreshToken
     * @return array
     * @throws \RuntimeException
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                Log::error('Salla OAuth token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Failed to refresh Salla access token.');
            }

            return $response->json();
        } catch (\Exception $exception) {
            Log::error('Exception during Salla token refresh', [
                'message' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Error executing token refresh: ' . $exception->getMessage());
        }
    }

    /**
     * Resolves the merchant resource details using an access token.
     *
     * @param  string  $accessToken
     * @return array
     * @throws \RuntimeException
     */
    public function getMerchantDetails(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->get($this->userInfoUrl);

            if ($response->failed()) {
                Log::error('Failed to retrieve Salla merchant details', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Failed to retrieve Salla merchant metadata.');
            }

            return $response->json();
        } catch (\Exception $exception) {
            Log::error('Exception during Salla merchant retrieval', [
                'message' => $exception->getMessage(),
            ]);
            throw new RuntimeException('Error retrieving merchant information: ' . $exception->getMessage());
        }
    }
}
