<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('login', function () {
    return redirect('/');
});

Route::get('/dashboard', function (Illuminate\Http\Request $request) {
    if ($request->user() && $request->user()->is_admin) {
        return redirect()->route('admin.overview');
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Salla merchant portal integration authentication routes
    Route::get('/auth/salla/redirect', [\App\Http\Controllers\Api\V1\SallaAuthController::class, 'redirect'])->name('salla.auth.redirect');
    Route::get('/auth/salla/callback', [\App\Http\Controllers\Api\V1\SallaAuthController::class, 'callback'])->name('salla.auth.callback');
    Route::post('/auth/salla/disconnect', [\App\Http\Controllers\Api\V1\SallaAuthController::class, 'disconnect'])->name('salla.auth.disconnect');
    Route::post('/auth/salla/sync', [\App\Http\Controllers\Api\V1\SallaAuthController::class, 'sync'])->name('salla.auth.sync');

    // WhatsApp integration configuration routes (Evolution API)
    Route::get('/auth/whatsapp/connect', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'connect'])->name('whatsapp.connect');
    Route::post('/auth/whatsapp/disconnect', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'disconnect'])->name('whatsapp.disconnect');
    Route::post('/auth/whatsapp/settings', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'updateSettings'])->name('whatsapp.settings.update');

    // Sandbox / Simulation route
    Route::post('/sandbox/simulate-order', [\App\Http\Controllers\Api\V1\SandboxController::class, 'simulateOrder'])->name('sandbox.simulate-order');

    // Billing / Subscription routes
    Route::post('/billing/upgrade', [\App\Http\Controllers\Api\V1\BillingController::class, 'upgrade'])->name('billing.upgrade');

    // Reviews moderation and utility routes
    Route::get('/reviews/export', [\App\Http\Controllers\Api\V1\ReviewController::class, 'export'])->name('reviews.export');
    Route::get('/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/reply', [\App\Http\Controllers\Api\V1\ReviewController::class, 'reply'])->name('reviews.reply');
    Route::put('/reviews/{review}/status', [\App\Http\Controllers\Api\V1\ReviewController::class, 'updateStatus'])->name('reviews.update-status');
    Route::delete('/reviews/{review}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// Dedicated Super Admin Authentication routes (Independent for security separation)
Route::middleware('guest')->group(function () {
    Route::get('/superadmin/login', [\App\Http\Controllers\Admin\AdminLoginController::class, 'create'])->name('admin.login');
    Route::post('/superadmin/login', [\App\Http\Controllers\Admin\AdminLoginController::class, 'store']);
});

// Super Admin platform routes (Protected by EnsureUserIsAdmin)
Route::middleware([\App\Http\Middleware\EnsureUserIsAdmin::class])->group(function () {
    Route::get('/superadmin', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.overview');
    Route::post('/superadmin/stores/{tenant}/toggle', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'toggleStatus'])->name('admin.stores.toggle');
    Route::post('/superadmin/simulator/update-subscription', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'updateSubscription'])->name('admin.simulator.update-subscription');
    Route::post('/superadmin/simulator/trigger-webhook', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'triggerWebhook'])->name('admin.simulator.trigger-webhook');
    Route::post('/superadmin/logout', [\App\Http\Controllers\Admin\AdminLoginController::class, 'destroy'])->name('admin.logout');
});

// WhatsApp status polling route made guest-accessible to prevent authentication redirect loops on session expiry
Route::get('/auth/whatsapp/status', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'status'])->name('whatsapp.status');

require __DIR__.'/auth.php';
