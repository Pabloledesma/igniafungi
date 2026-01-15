<?php
use App\Models\Product;
use App\Services\InventoryService;

$product = Product::where('name', 'Eryngii')->first(); // Assuming name
if (!$product)
    $product = Product::where('strain_id', 6)->first();

if ($product) {
    echo "Found Product: {$product->name}\n";
    $service = new InventoryService();
    $batch = $service->getPreorderBatch($product);
    if ($batch) {
        echo "SUCCESS: Found Preorder Batch {$batch->code}\n";
        echo "Yield: {$batch->expected_yield}\n";
    } else {
        echo "FAIL: No batch found.\n";
    }
} else {
    echo "Product not found.\n";
}
