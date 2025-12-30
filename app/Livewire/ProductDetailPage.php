<?php

namespace App\Livewire;

use App\Livewire\Traits\AddsToCart;
use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Product Detail Page')]
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

    public function mount($slug)
    {
        $this->slug = $slug;
    }

    public function render()
    {
        return view('livewire.product-detail-page', [
            'product' => Product::where('slug', $this->slug)->firstOrFail(),
        ]);
    }
}
