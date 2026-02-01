<?php

namespace App\Livewire;

use App\Livewire\Traits\AddsToCart;
use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\Title;


class ProductDetailPage extends Component
{
    public $slug;
    public $quantity = 1;

    use AddsToCart;

    public function decrementQuantity()
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function incrementQuantity()
    {
        $this->quantity++;
    }

    public function getPreorderBatchProperty()
    {
        // We can lazy load the product's preorder batch
        // assuming $this->slug gets us the product, or we loaded it.
        // The render method loads product by slug every time. Optimally we should have it in property.
        // But for Livewire computed property style:
        $product = Product::where('slug', $this->slug)->first();
        if (!$product)
            return null;

        return app(\App\Services\InventoryService::class)->getPreorderBatch($product);
    }

    public function addToCart($productId)
    {
        $product = Product::find($productId);
        $isPreorder = false;
        $quantity = $this->quantity;

        // Check availability
        if (!$product->in_stock) {
            $batch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);
            if ($batch) {
                // Validate capacity
                $hasCapacity = app(\App\Services\InventoryService::class)->validatePreorderStock($product, $quantity);
                if (!$hasCapacity) {
                    session()->flash('error', 'Lo sentimos, no hay suficiente capacidad proyectada en este lote para tu pedido.');
                    return;
                }
                $isPreorder = true;
            } else {
                session()->flash('error', 'Producto agotado.');
                return;
            }
        }

        $count = \App\Helpers\CartManagement::addItemsToCart($productId, $quantity, $isPreorder);

        $this->dispatch('cart-updated', count: $count); // Update cart counter

        if ($isPreorder) {
            session()->flash('success', 'Cosecha apartada con éxito!');
        } else {
            session()->flash('success', 'Producto agregado al carrito!');
        }
    }

    public function render()
    {
        $product = Product::where('slug', $this->slug)->firstOrFail();
        $preorderBatch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);

        $images = [];
        if (!empty($product->images)) {
            foreach ($product->images as $image) {
                $images[] = asset('storage/' . $image);
            }
        } else {
            $images[] = asset('images/og-default.jpg');
        }

        $schema = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $product->name,
            "image" => $images,
            "description" => \Illuminate\Support\Str::limit(strip_tags($product->description), 160),
            "brand" => [
                "@type" => "Brand",
                "name" => "Ignia Fungi"
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => request()->url(),
                "priceCurrency" => "COP",
                "price" => (!$product->in_stock && isset($preorderBatch) && $preorderBatch) ? $product->price * 0.9 : $product->price,
                "availability" => $product->in_stock ? 'https://schema.org/InStock' : (($preorderBatch) ? 'https://schema.org/PreOrder' : 'https://schema.org/OutOfStock'),
                "itemCondition" => "https://schema.org/NewCondition"
            ]
        ];

        return view('livewire.product-detail-page', [
            'product' => $product,
            'preorderBatch' => $preorderBatch,
            'schemaJson' => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        ]);
    }
}
