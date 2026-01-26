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

        return view('livewire.product-detail-page', [
            'product' => $product,
            'preorderBatch' => $preorderBatch
        ]);
    }
}
