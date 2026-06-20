<?php

namespace App\Console\Commands;

use App\Models\SallaConfig;
use App\Services\SallaOAuthClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshSallaTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salla:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refreshes Salla OAuth access tokens that are near expiration';

    protected SallaOAuthClient $oauthClient;

    /**
     * Creates a new command instance and injects the Salla OAuth service.
     *
     * @param  \App\Services\SallaOAuthClient  $oauthClient
     */
    public function __construct(SallaOAuthClient $oauthClient)
    {
        parent::__construct();
        $this->oauthClient = $oauthClient;
    }

    /**
     * Executes the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Initiating Salla OAuth token refresh operation...');

        // Fetch configs expiring in the next 3 days
        $threshold = Carbon::now()->addDays(3);
        $expiringConfigs = SallaConfig::where('expires_at', '<=', $threshold)->get();

        if ($expiringConfigs->isEmpty()) {
            $this->info('No Salla access tokens are expiring within the threshold period.');
            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($expiringConfigs as $config) {
            try {
                $this->info("Refreshing token for merchant ID: {$config->merchant_id}");

                // Refreshing credentials using the stored refresh token
                $tokens = $this->oauthClient->refreshAccessToken($config->refresh_token);

                $config->update([
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_at' => Carbon::now()->addSeconds((int) $tokens['expires_in']),
                ]);

                Log::info('Successfully refreshed Salla token for merchant', [
                    'merchant_id' => $config->merchant_id,
                ]);

                $successCount++;
            } catch (\Exception $exception) {
                Log::error('Failed to refresh Salla token for merchant config', [
                    'merchant_id' => $config->merchant_id,
                    'error' => $exception->getMessage(),
                ]);

                $this->error("Failed to refresh token for merchant ID: {$config->merchant_id}");
                $failureCount++;
            }
        }

        $this->info("Token refresh completed. Successes: {$successCount}, Failures: {$failureCount}.");

        return $failureCount > 0 ? 1 : 0;
    }
}
