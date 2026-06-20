<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('salla:refresh-tokens')->daily();

        // Background synchronization fallback executing every 10 minutes with tenant-specific interval filtering
        $schedule->call(function () {
            $tenants = \App\Models\Tenant::whereHas('sallaConfig')->get();
            $driver = new \App\Integration\Drivers\SallaDriver();
            foreach ($tenants as $tenant) {
                $whatsappConfig = \App\Models\WhatsappConfig::where('tenant_id', $tenant->id)->first();
                $isScannerEnabled = $whatsappConfig && (!isset($whatsappConfig->custom_questions['enable_salla_scanner']) || $whatsappConfig->custom_questions['enable_salla_scanner'] !== false);
                
                if ($isScannerEnabled) {
                    $interval = (int) ($whatsappConfig->custom_questions['salla_scanner_interval_minutes'] ?? 60);
                    $cacheKey = 'last_salla_scanner_run_' . $tenant->id;
                    $lastRun = \Illuminate\Support\Facades\Cache::get($cacheKey);
                    
                    if (empty($lastRun) || now()->diffInMinutes(\Carbon\Carbon::parse($lastRun)) >= $interval) {
                        $driver->syncProducts($tenant);
                        $driver->syncOrders($tenant);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, now()->toDateTimeString());
                    }
                } else {
                    // Fallback to default hourly sync if scanner is disabled to keep records updated
                    $cacheKey = 'last_default_sync_run_' . $tenant->id;
                    $lastRun = \Illuminate\Support\Facades\Cache::get($cacheKey);
                    if (empty($lastRun) || now()->diffInMinutes(\Carbon\Carbon::parse($lastRun)) >= 60) {
                        $driver->syncProducts($tenant);
                        $driver->syncOrders($tenant);
                        \Illuminate\Support\Facades\Cache::put($cacheKey, now()->toDateTimeString());
                    }
                }
            }
        })->everyTenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->report(function (\Throwable $e) {
            file_put_contents(
                base_path('error.txt'),
                "[" . date('Y-m-d H:i:s') . "] " . get_class($e) . ": " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
        });
    })->create();
