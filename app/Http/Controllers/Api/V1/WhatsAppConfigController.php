<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Integration\Drivers\EvolutionAPIDriver;
use App\Models\WhatsappConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WhatsAppConfigController extends Controller
{
    /**
     * Start the WhatsApp connection process (create instance and get QR code).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function connect(Request $request): JsonResponse
    {
        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                return response()->json(['error' => 'Tenant context missing.'], 400);
            }

            $driver = new EvolutionAPIDriver();
            $result = $driver->createInstance($tenant);

            if (empty($result)) {
                return response()->json(['error' => 'Failed to initialize WhatsApp connection. Check Evolution API logs.'], 500);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('WhatsApp connect exception.', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check the connection state of the tenant WhatsApp instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || empty($user->tenant)) {
                return response()->json(['status' => 'disconnected'], 200);
            }
            $tenant = $user->tenant;

            // Quick check: If database status is already connected, bypass the external API query to optimize UI speed.
            $config = WhatsappConfig::where('tenant_id', $tenant->id)->first();
            if ($config && $config->status === 'connected') {
                return response()->json(['status' => 'connected'], 200);
            }

            $driver = new EvolutionAPIDriver();
            $state = $driver->getConnectionState($tenant);

            return response()->json(['status' => $state]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'disconnected', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Disconnect and delete the WhatsApp instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function disconnect(Request $request): RedirectResponse
    {
        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new \RuntimeException('Tenant context missing.');
            }

            $driver = new EvolutionAPIDriver();
            $driver->disconnectInstance($tenant);

            return redirect()->route('dashboard')->with('success', 'تم فك ربط حساب واتساب بنجاح.');
        } catch (\Exception $e) {
            Log::error('WhatsApp disconnect exception.', ['message' => $e->getMessage()]);
            return redirect()->route('dashboard')->with('error', 'فشل فك ربط واتساب: ' . $e->getMessage());
        }
    }

    /**
     * Save the delay settings for sending rating messages.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'delay_hours' => 'required|integer|min:0|max:720',
            'custom_questions' => 'nullable|array',
        ]);

        try {
            $tenant = Auth::user()->tenant;
            if (empty($tenant)) {
                throw new \RuntimeException('Tenant context missing.');
            }

            WhatsappConfig::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'delay_hours' => (int) $validated['delay_hours'],
                    'custom_questions' => $validated['custom_questions'] ?? null,
                ]
            );

            return redirect()->route('dashboard')->with('success', 'تم تحديث إعدادات واتساب بنجاح.');
        } catch (\Exception $e) {
            Log::error('WhatsApp update settings exception.', ['message' => $e->getMessage()]);
            return redirect()->route('dashboard')->with('error', 'فشل حفظ الإعدادات: ' . $e->getMessage());
        }
    }
}
