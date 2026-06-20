<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Salla merchant integration webhook receiver endpoint
Route::post('/v1/webhooks/salla', [\App\Http\Controllers\Api\V1\SallaWebhookController::class, 'handle']);

// WhatsApp integration webhook verification and receiver endpoints
Route::get('/v1/webhooks/whatsapp', [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'verify']);
Route::post('/v1/webhooks/whatsapp', [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'handle']);

// Widget details API endpoint for storefront integration
Route::get('/v1/widget/data', [\App\Http\Controllers\Api\V1\WidgetController::class, 'getData']);

