<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $config = \App\Models\SallaConfig::first();
    if (!$config) {
        echo "No Salla config found.\n";
        exit(1);
    }

    $response = \Illuminate\Support\Facades\Http::withToken($config->access_token)
        ->get('https://api.salla.dev/admin/v2/products');

    if ($response->failed()) {
        echo "API Request failed. Status: " . $response->status() . "\n";
        echo "Body: " . $response->body() . "\n";
        exit(1);
    }

    $data = $response->json();
    $sallaProducts = $data['data'] ?? [];
    echo "Salla API Product Count: " . count($sallaProducts) . "\n";
    foreach ($sallaProducts as $p) {
        echo " - ID: " . $p['id'] . " | Name: " . $p['name'] . "\n";
    }

    $dbProducts = \App\Models\Product::all();
    echo "Local DB Product Count: " . $dbProducts->count() . "\n";
    foreach ($dbProducts as $p) {
        echo " - Salla ID: " . $p->salla_product_id . " | Name: " . $p->name . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
