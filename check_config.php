<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$configs = \App\Models\WhatsappConfig::all();
echo "Total configs: " . $configs->count() . "\n";
foreach ($configs as $c) {
    echo "ID: {$c->id}, Tenant: {$c->tenant_id}, Status: {$c->status}, Name: {$c->instance_name}, ApiKey: " . ($c->instance_apikey ?? 'NULL') . "\n";
}
