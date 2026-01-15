<?php
// debug_batches.php
use App\Models\Batch;
use App\Models\Product;

echo "--- Debug Batch Info ---\n";
$batches = Batch::where('status', 'incubation')->get();
echo "Found " . $batches->count() . " batches in incubation.\n";

foreach ($batches as $batch) {
    echo "Batch ID: {$batch->id} | Strain ID: {$batch->strain_id} | Yield: {$batch->expected_yield} | Date: {$batch->estimated_harvest_date}\n";
}

echo "\n--- Product Info ---\n";
$products = Product::all();
foreach ($products as $product) {
    echo "Product: {$product->name} | Strain ID: {$product->strain_id} | In Stock: {$product->in_stock}\n";
    if ($product->strain_id) {
        $found = Batch::where('strain_id', $product->strain_id)
            ->where('status', 'incubation')
            ->where('expected_yield', '>', 0)
            ->first();
        echo " -> Matching Batch Found: " . ($found ? "YES (ID: {$found->id})" : "NO") . "\n";
    }
}
