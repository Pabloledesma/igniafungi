<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$batch = \App\Models\Batch::where('code', 'SUB-130126-1')->first();
if (!$batch) {
    echo "Batch not found\n";
    exit;
}

echo "Batch: {$batch->code}\n";
echo "Dry Weight: {$batch->weigth_dry}\n";
$recipe = $batch->recipe;
echo "Recipe: " . ($recipe->name ?? 'None') . "\n";

if ($recipe) {
    foreach ($recipe->supplies as $supply) {
        $cost = $supply->cost_per_unit;
        $pivot = $supply->pivot;
        echo " - Supply: {$supply->name}\n";
        echo "   Cost: " . ($cost ?? 'NULL') . "\n";
        echo "   Mode: {$pivot->calculation_mode}\n";
        echo "   Value: {$pivot->value}\n";
    }
}
