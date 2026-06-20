<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
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
    Route::get('/auth/whatsapp/status', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'status'])->name('whatsapp.status');
    Route::post('/auth/whatsapp/disconnect', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'disconnect'])->name('whatsapp.disconnect');
    Route::post('/auth/whatsapp/settings', [\App\Http\Controllers\Api\V1\WhatsAppConfigController::class, 'updateSettings'])->name('whatsapp.settings.update');

    // Sandbox / Simulation route
    Route::post('/sandbox/simulate-order', [\App\Http\Controllers\Api\V1\SandboxController::class, 'simulateOrder'])->name('sandbox.simulate-order');

    // Reviews moderation and utility routes
    Route::get('/reviews/export', [\App\Http\Controllers\Api\V1\ReviewController::class, 'export'])->name('reviews.export');
    Route::get('/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/reply', [\App\Http\Controllers\Api\V1\ReviewController::class, 'reply'])->name('reviews.reply');
    Route::put('/reviews/{review}/status', [\App\Http\Controllers\Api\V1\ReviewController::class, 'updateStatus'])->name('reviews.update-status');
    Route::delete('/reviews/{review}', [\App\Http\Controllers\Api\V1\ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Super Admin platform routes
    Route::middleware([\App\Http\Middleware\EnsureUserIsAdmin::class])->group(function () {
        Route::get('/admin/overview', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.overview');
        Route::post('/admin/stores/{tenant}/toggle', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'toggleStatus'])->name('admin.stores.toggle');
    });
});

require __DIR__.'/auth.php';
