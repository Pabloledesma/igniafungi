<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

$apiKey = config('ai.drivers.gemini.api_key'); // Reads from .env
$baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

echo "Using API Key: " . substr($apiKey, 0, 5) . "...\n";

$response = Http::get("{$baseUrl}/models?key={$apiKey}");

if ($response->failed()) {
    echo "Error: " . $response->status() . "\n";
    echo $response->body();
} else {
    echo "Available Models:\n";
    $models = $response->json()['models'] ?? [];
    foreach ($models as $m) {
        if (str_contains($m['name'], 'gemini')) {
            echo "- " . $m['name'] . " (Supported: " . implode(', ', $m['supportedGenerationMethods']) . ")\n";
        }
    }
}
