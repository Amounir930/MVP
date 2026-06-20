<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin overview dashboard.
     */
    public function index(Request $request): Response
    {
        $tenants = Tenant::with(['users', 'sallaConfig', 'whatsappConfig'])
            ->withCount(['reviews', 'orders', 'products'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate global metrics
        $totalStores = $tenants->count();
        
        $activeStores = $tenants->filter(function ($t) {
            return $t->sallaConfig !== null && $t->status === 'active';
        })->count();

        $totalMessagesConsumed = WhatsappMessageLog::withoutGlobalScopes()->count();

        // Format stores directory list
        $stores = $tenants->map(function ($tenant) {
            $owner = $tenant->users->first(); // Get first registered user as default owner
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at ? $tenant->created_at->format('Y-m-d') : 'N/A',
                'owner_name' => $owner ? $owner->name : 'N/A',
                'owner_email' => $owner ? $owner->email : 'N/A',
                'salla_connected' => $tenant->sallaConfig !== null,
                'whatsapp_status' => $tenant->whatsappConfig ? $tenant->whatsappConfig->status : 'disconnected',
                'reviews_count' => $tenant->reviews_count,
                'orders_count' => $tenant->orders_count,
                'products_count' => $tenant->products_count,
            ];
        });

        return Inertia::render('Admin/Overview', [
            'stats' => [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'total_messages_consumed' => $totalMessagesConsumed,
            ],
            'stores' => $stores,
        ]);
    }

    /**
     * Toggle the active status of a store/tenant.
     */
    public function toggleStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $newStatus = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة المتجر بنجاح.',
            'status' => $newStatus,
        ]);
    }
}
