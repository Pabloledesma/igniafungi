<?php
namespace App\Livewire\Traits;

use App\Helpers\CartManagement;
use App\Livewire\Partials\Navbar;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

trait AddsToCart
{
    public function addToCart($product_id, $quantity = null)
    {
        $count_items = CartManagement::addItemsToCart($product_id, $quantity);
        if($count_items > 0){
            $this->dispatch('update-cart-count', total_count: $count_items)->to(Navbar::class);
        }

        LivewireAlert::title('El producto se agrego correctamente!')
            ->success()
            ->show();

    }
}