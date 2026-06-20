<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiUrl = 'http://evolution:8080';
$globalKey = 'global-key-abc';
$instanceName = 'test_inspect_instance_' . rand(100, 999);

$response = Http::withHeaders(['apikey' => $globalKey])
    ->post("{$apiUrl}/instance/create", [
        'instanceName' => $instanceName,
        'integration' => 'WHATSAPP-BAILEYS',
        'qrcode' => true,
    ]);

echo "Status: " . $response->status() . "\n";
echo "Body:\n";
echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";

// Clean it up
Http::withHeaders(['apikey' => $globalKey])
    ->delete("{$apiUrl}/instance/delete/{$instanceName}");
