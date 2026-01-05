<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\Product;
use Livewire\Component;
use App\Helpers\CartManagement;
use App\Livewire\Partials\Navbar;
use Livewire\Attributes\Computed;

class CartPage extends Component
{
    public $cart_items = [];
    public $grand_total;
    public $is_bogota = true;
    public $selected_delivery_index = 0;
    public $location;

    public function getProgressData() {
        $subtotal = CartManagement::calculateGrandTotal($this->cart_items);
        $limit = CartManagement::FREE_SHIPPING_THRESHOLD;
        $missing = $limit - $subtotal;
        $percentage = ($subtotal / $limit) * 100;

        return [
            'percentage' => min($percentage, 100),
            'missing' => max($missing, 0),
            'is_free' => $subtotal >= $limit
        ];
    }
        
    /**
     * Centralizamos la actualización del carrito
     */
    private function refreshCart($items)
    {
        $this->cart_items = $items;
        $this->grand_total = CartManagement::calculateGrandTotal($this->cart_items);
    }

    #[Computed]
    public function hasRestrictedProducts()
    {
        foreach ($this->cart_items as $item) {
            // Asumiendo que en el array del carrito guardas el slug o ID de la categoría
            // o puedes consultar el modelo Product si es necesario.
            $product = Product::find($item['product_id']);
            
            if ($product && $product->category && $product->category->slug === 'hongos-gourmet') {
                return true;
            }
        }
        return false;
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
        $subtotal = CartManagement::calculateGrandTotal($this->cart_items);
        
        // Si es Bogotá, pasamos la localidad seleccionada. 
        // Si no es Bogotá, el helper usará la tarifa nacional automáticamente.
        $destino = $this->is_bogota ? 'Bogotá' : 'Nacional';
        
        return CartManagement::getShippingCost($subtotal, $destino, $this->location);
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
        // Bloqueo de seguridad: Si hay productos restringidos y no es Bogotá
        if (!$this->is_bogota && $this->hasRestrictedProducts) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Elimina los hongos frescos para envíos nacionales.'
            ]);
            return;
        }
        
        // Usamos la opción seleccionada por el usuario de la lista de deliveryOptions
        $selectedDate = $this->deliveryOptions[$this->selected_delivery_index]['label'] ?? null;

        session([
            'checkout_shipping' => [
                'is_bogota'     => (bool)$this->is_bogota,
                'location'      => $this->location,
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