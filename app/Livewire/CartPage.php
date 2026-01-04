<?php

namespace App\Livewire;

use Livewire\Component;
use App\Helpers\CartManagement;
use App\Livewire\Partials\Navbar;
use Carbon\Carbon;
use Livewire\Attributes\Computed;

class CartPage extends Component
{
    public $cart_items = [];
    public $grand_total;
    public $is_bogota = false;
    public $selected_delivery_index = 0;

    /**
     * Centralizamos la actualización del carrito
     */
    private function refreshCart($items)
    {
        $this->cart_items = $items;
        $this->grand_total = CartManagement::calculateGrandTotal($this->cart_items);
    }

    /**
     * Propiedad Computada: Genera las opciones de entrega (Jueves/Viernes pares)
     */
    #[Computed]
    public function deliveryOptions()
    {
        $options = [];
        $date = Carbon::now();
        
        while (count($options) < 2) {
            if (($date->isThursday() || $date->isFriday()) && ($date->day % 2 === 0)) {
                // Si es hoy, validar hora de corte (10 AM)
                if (!$date->isToday() || $date->hour < 10) {
                    $options[] = [
                        'date'  => $date->format('Y-m-d'),
                        'label' => $date->translatedFormat('l d \d\e F')
                    ];
                }
            }
            $date->addDay();
        }
        return $options;
    }

    /**
     * Propiedad Computada: Costo de envío dinámico
     */
    #[Computed]
    public function shippingCost()
    {
        return $this->is_bogota ? 15000 : 0;
    }

    /**
     * Propiedad Computada: Total final con envío incluido
     */
    #[Computed]
    public function finalTotal()
    {
        return $this->grand_total + $this->shippingCost;
    }

    public function mount()
    {
        $this->refreshCart(CartManagement::getCartItemsFromCookie());
    }

    public function decrementQuantity($product_id)
    {
        $this->refreshCart(CartManagement::decrementQuantityToCartItem($product_id));
    }

    public function incrementQuantity($product_id)
    {
        $this->refreshCart(CartManagement::incrementQuantityToCartItem($product_id));
    }

    public function removeItem($product_id)
    {
        CartManagement::removeCartItem($product_id);
        $this->refreshCart(CartManagement::getCartItemsFromCookie());
        
        $this->dispatch('update-cart-count', total_count: count($this->cart_items))->to(Navbar::class);
    }

    public function checkout()
    {
        // Usamos la opción seleccionada por el usuario de la lista de deliveryOptions
        $selectedDate = $this->deliveryOptions[$this->selected_delivery_index]['label'] ?? null;

        session([
            'checkout_shipping' => [
                'is_bogota'     => (bool)$this->is_bogota,
                'cost'          => $this->shippingCost,
                'delivery_date' => $selectedDate
            ]
        ]);

        return redirect()->to('/checkout');
    }
    
    public function render()
    {
        return view('livewire.cart-page');
    }
}