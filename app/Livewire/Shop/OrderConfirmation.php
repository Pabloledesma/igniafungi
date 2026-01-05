<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use Livewire\Component;
use Illuminate\Http\Request;

class OrderConfirmation extends Component
{
    
    public $order;
    /**
     * Maneja la redirección después del pago.
     */
    public function mount(Request $request)
    {
        // 1. Extraemos la referencia del Query String (?reference=REF-OK)
        $reference = $request->query('reference');

        // 2. Buscamos la orden. Usamos with() para cargar los productos y evitar N+1
        $this->order = Order::where('reference', $reference)
                    ->with(['items.product', 'addresses'])
                    ->firstOrFail(); // Si no existe, lanza 404

    }
    
    public function render()
    {
        return view('livewire.shop.order-confirmation');
    }
}
