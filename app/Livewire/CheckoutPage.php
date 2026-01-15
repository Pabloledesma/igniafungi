<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Address;
use Livewire\Component;
use Livewire\Attributes\Title;
use App\Helpers\CartManagement;
use Illuminate\Support\Facades\Session;


use App\Models\Coupon;
use Jantinnerezo\LivewireAlert\LivewireAlert;

#[Title('Checkout Page')]
class CheckoutPage extends Component
{
    // use LivewireAlert; // Disabled due to trait error

    public $coupon_code_input = '';
    public $applied_coupon_code = null;
    public $discount_amount = 0;

    public $cart_items = [];
    public $grand_total;
    public $first_name;
    public $last_name;
    public $document_number;
    public $document_type;
    public $phone;
    public $email;
    public $street_address;
    public $payment_method;
    public $order_id = null;
    public $hash_integridad = null;
    public $total_amount_bold = 0;
    public $shipping_method;
    public $shipping_cost = 0;
    public $data_customer_data;
    public $delivery_date = null;
    public $city;
    public $state;
    public $zip_code;
    public $departmet;
    public $free_shipping_if = CartManagement::FREE_SHIPPING_THRESHOLD;
    public $location; // Para la localidad
    public $cities = ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena'];
    public $localidadPrecios = [
        // ANILLO 1: Vecinos inmediatos o misma localidad
        'Engativa' => 9000,
        'Fontibon' => 9500,
        'Barrios Unidos' => 10000,
        'Teusaquillo' => 10500,
        'Suba' => 11000,

        // ANILLO 2: Centro y Occidente cercano
        'Puente Aranda' => 12000,
        'Chapinero' => 12500,
        'Martires' => 13000,
        'Usaquen' => 13500,
        'Kennedy' => 14000,

        // ANILLO 3: Centro-Sur
        'Santa Fe' => 14500,
        'Candelaria' => 15000,
        'Antonio Nariño' => 15500,
        'Rafael Uribe Uribe' => 16000,
        'Tunjuelito' => 16500,

        // ANILLO 4: Periferia Sur (Mayor distancia)
        'San Cristobal' => 17500,
        'Bosa' => 18000,
        'Ciudad Bolivar' => 18500,
        'Usme' => 19500,
        'Sumapaz' => 20000,
    ];

    public function applyCoupon()
    {
        $this->coupon_code_input = trim($this->coupon_code_input);

        if (empty($this->coupon_code_input)) {
            session()->flash('warning', 'Ingresa un código de cupón');
            return;
        }

        $coupon = Coupon::where('code', $this->coupon_code_input)->first();

        if (!$coupon || !$coupon->isValid()) {
            session()->flash('error', 'El cupón no es válido o ha expirado');
            $this->coupon_code_input = '';
            return;
        }

        if ($this->applied_coupon_code === $coupon->code) {
            session()->flash('info', 'Este cupón ya está aplicado');
            return;
        }

        $this->applied_coupon_code = $coupon->code;
        $this->calculateShipping();
        session()->flash('success', 'Cupón aplicado con éxito!');
    }

    public function removeCoupon()
    {
        $this->applied_coupon_code = null;
        $this->coupon_code_input = '';
        $this->calculateShipping();
        session()->flash('info', 'Cupón removido');
    }

    public function updatedShippingMethod()
    {
        $this->calculateShipping();
    }

    public function calculateShipping()
    {
        $subtotal = (int) CartManagement::calculateGrandTotal($this->cart_items);

        // 1. Calculate discount if coupon looks present
        $this->discount_amount = 0;
        if ($this->applied_coupon_code) {
            $coupon = Coupon::where('code', $this->applied_coupon_code)->first();
            if ($coupon && $coupon->isValid()) {
                if ($coupon->discount_type === 'fixed') {
                    $this->discount_amount = $coupon->discount_value;
                } else {
                    $this->discount_amount = ($subtotal * $coupon->discount_value) / 100;
                }
            } else {
                $this->applied_coupon_code = null;
            }
        }

        // Ensure discount doesn't exceed subtotal
        if ($this->discount_amount > $subtotal) {
            $this->discount_amount = $subtotal;
        }

        // 2. Shipping Cost
        // Methods:
        // - 'pickup': 0
        // - 'interrapidisimo': Base 15000 + 5000 per kg (or fraction).
        // - 'bogota': 10000 (Días Pares) - Only valid for Bogota.

        if ($this->shipping_method === 'pickup') {
            $this->shipping_cost = 0;
        } elseif ($this->shipping_method === 'interrapidisimo') {
            // Calculate weight. 
            // Assume standard weight if not set? 
            // We need total weight of cart.
            $totalWeight = 0;
            foreach ($this->cart_items as $item) {
                // Assuming product loaded or we can fetch.
                // Ideally cart item should have weight.
                // For now, fetch product or default 0.5kg
                if (isset($item['product_id'])) {
                    $prod = \App\Models\Product::find($item['product_id']);
                    $weight = $prod->weight > 0 ? $prod->weight : 500; // grams
                    $totalWeight += ($weight * $item['quantity']);
                }
            }

            // Convert to KG
            $weightKg = ceil($totalWeight / 1000);
            if ($weightKg < 1)
                $weightKg = 1;

            $this->shipping_cost = 15000 + (($weightKg - 1) * 5000);

        } elseif ($this->shipping_method === 'bogota') {
            // 1. Logic: Free Shipping Threshold (Only for Bogota)
            if ($subtotal >= CartManagement::FREE_SHIPPING_THRESHOLD) {
                $this->shipping_cost = 0;
            } else {
                // 2. Specific neighborhood pricing
                if ($this->location && isset($this->localidadPrecios[$this->location])) {
                    $this->shipping_cost = $this->localidadPrecios[$this->location];
                } else {
                    $this->shipping_cost = 10000;
                }
            }

            // Logic: Deliveries grouped on Even Days.
            // Find earliest harvest availability date from cart.
            // If no harvest date (normal products), use tomorrow/today.

            // Simplification: Base date = NOW. Refinement: Base date = MAX(harvest_date) of cart.
            // Currently Cart items don't store harvest date, but we can look it up.

            $baseDate = \Carbon\Carbon::now();

            // Check cart items for preorder batches
            foreach ($this->cart_items as $item) {
                if (!empty($item['is_preorder'])) {
                    $product = \App\Models\Product::find($item['product_id']);
                    $batch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);
                    if ($batch && $batch->estimated_harvest_date) {
                        $harvestDate = \Carbon\Carbon::parse($batch->estimated_harvest_date);
                        if ($harvestDate->gt($baseDate)) {
                            $baseDate = $harvestDate;
                        }
                    }
                }
            }

            // Validate existing delivery_date if set
            $shouldRecalculate = true;
            if ($this->delivery_date) {
                $currentDate = \Carbon\Carbon::parse($this->delivery_date);
                // Check if current date is >= baseDate AND is Even
                if ($currentDate->gte($baseDate->startOfDay()) && $currentDate->day % 2 === 0) {
                    $shouldRecalculate = false;
                }
            }

            if ($shouldRecalculate) {
                // Find next even day from baseDate.
                $nextDate = $baseDate->copy();

                if ($nextDate->day % 2 != 0) {
                    $nextDate->addDay(); // Make it even
                } else {
                    // If it is today and late? (Optional refinement)
                    if ($nextDate->isToday() && $nextDate->hour >= 10) {
                        $nextDate->addDays(2); // Next even day
                    }
                }
                $this->delivery_date = $nextDate->format('Y-m-d');
            }

        } else {
            // Default fallback
            if ($this->city === 'Bogotá') {
                $this->shipping_method = 'bogota';
                $this->calculateShipping();
                return;
            } else {
                if ($this->departmet || $this->city) {
                    $this->shipping_method = 'interrapidisimo';
                    // Avoid recursion logic here, just set cost
                    $this->shipping_cost = 15000;
                } else {
                    $this->shipping_cost = 0;
                }
            }
        }

        // 3. Grand Total
        $this->grand_total = ($subtotal - $this->discount_amount) + $this->shipping_cost;
    }

    public function mount()
    {
        $this->first_name = auth()->user()->name;
        $this->email = auth()->user()->email;

        $this->cart_items = CartManagement::getCartItemsFromCookie();

        // Sync from Cart Session
        $shipping_data = session('checkout_shipping');

        if ($shipping_data) {
            $this->is_bogota = $shipping_data['is_bogota'] ?? false;
            $this->city = $this->is_bogota ? 'Bogotá' : null;
            $this->location = $shipping_data['location'] ?? null;
            $this->delivery_date = $shipping_data['delivery_date'] ?? null;

            // Set Shipping Method based on session context
            if ($this->is_bogota) {
                $this->shipping_method = 'bogota';
            } else {
                $this->shipping_method = 'interrapidisimo'; // Default for National
            }

            // We usually don't need to force cost from session if we recalculate, 
            // but to be safe we can, though calculateShipping will override.
        }

        $this->calculateShipping();
    }

    /**
     * Propiedad Computada: Genera las opciones de entrega (Jueves/Viernes pares)
     * Replicated from CartPage to ensure consistency in Checkout.
     */
    #[\Livewire\Attributes\Computed]
    public function deliveryOptions()
    {
        $options = [];
        $date = \Carbon\Carbon::now();

        // 1. Calculate max harvest date from preorders
        foreach ($this->cart_items as $item) {
            if (!empty($item['is_preorder'])) {
                $product = \App\Models\Product::find($item['product_id']);
                if ($product) {
                    $batch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);
                    if ($batch && $batch->estimated_harvest_date) {
                        $harvestDate = \Carbon\Carbon::parse($batch->estimated_harvest_date);
                        if ($harvestDate->gt($date)) {
                            $date = $harvestDate;
                        }
                    }
                }
            }
        }

        // 2. Find next valid delivery slots
        while (count($options) < 4) { // Show more options in checkout (e.g. 4) for better UX
            // Logic: Thursday or Friday AND Even Day
            if (($date->isThursday() || $date->isFriday()) && ($date->day % 2 === 0)) {
                if (!$date->isToday() || $date->hour < 10) {
                    $options[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->translatedFormat('l d \d\e F')
                    ];
                }
            }
            $date->addDay();
        }
        return $options;
    }

    public function placeOrder()
    {
        $cart_items = CartManagement::getCartItemsFromCookie();
        // Validación manual del carrito para evitar el error de "No property found"
        if (empty($cart_items)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cart' => 'No puedes realizar un pedido con el carrito vacío.',
            ]);
        }
        $this->validate([
            'first_name' => 'required|min:3',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'document_number' => 'required|numeric',
            'delivery_date' => 'required',
            'payment_method' => 'required',
            'city' => 'required',
        ]);

        $subtotal = (int) CartManagement::calculateGrandTotal($cart_items);

        foreach ($cart_items as $item) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'cop',
                    'unit_amount' => $item['unit_amount'] * 100,
                    'product_data' => [
                        'name' => $item['name']
                    ]
                ],
                'quantity' => $item['quantity']
            ];

        }

        $final_total = ($subtotal - $this->discount_amount) + (int) $this->shipping_cost;
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->grand_total = $final_total;
        $order->payment_method = $this->payment_method;
        $order->payment_status = 'pending';
        $order->shipping_amount = $this->shipping_cost;
        $order->status = 'new';
        $order->currency = 'cop';
        $order->notes = 'Order placed by ' . auth()->user()->name;

        if ($this->applied_coupon_code) {
            $order->coupon_code = $this->applied_coupon_code;
            $order->discount_amount = $this->discount_amount;

            // Increment usage count
            Coupon::where('code', $this->applied_coupon_code)->increment('usage_count');
        }

        $order->save();

        $address = new Address();
        $address->order_id = $order->id;
        $address->first_name = $this->first_name;
        $address->last_name = $this->last_name;
        $address->phone = $this->phone;
        $address->street_address = $this->street_address;
        $address->city = $this->city;
        $address->location = $this->location;
        $address->save();

        foreach ($cart_items as $item) {
            $item_data = $item;
            // Ensure unit_amount is correct for the item being saved (it's already calculated in cart)
            // Add is_preorder to the creation array
            $item_data['is_preorder'] = $item['is_preorder'] ?? false;

            // We can pass the array directly to createMany if keys matchDB columns
            // But Cart items keys might not match exactly OrderItem columns
            // Cart: product_id, name, unit_amount, quantity, total_amount, is_preorder
            // OrderItem: order_id, product_id, quantity, unit_amount, total_amount, is_preorder, batch_id
            // We need to map or clean it. createMany uses attributes.

            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_amount' => $item['unit_amount'],
                'total_amount' => $item['total_amount'],
                'is_preorder' => $item['is_preorder'] ?? false
            ]);
        }

        $order->delivery()->create([
            'status' => 'pending',
            'scheduled_at' => $this->delivery_date,
        ]);

        if ($this->payment_method == 'BOLD') {
            $this->order_id = $order->id;
            $this->total_amount_bold = (int) $order->grand_total;
            $moneda = "COP";

            $config = [
                'orderId' => (string) $this->order_id,
                'currency' => 'COP',
                'amount' => (string) $this->total_amount_bold,
                'apiKey' => config('services.bold.key'),
                'integritySignature' => hash('sha256', $this->order_id . $this->total_amount_bold . $moneda . config('services.bold.secret')),
                'description' => "Pedido #{$this->order_id} en Ignia Fungi",
                'renderMode' => 'embedded',
                'redirectionUrl' => str_replace('http://localhost:8000', 'https://dev.igniafungi.com', route('order.thanks', ['reference' => $order->reference])),
                'customerData' => [
                    'email' => $this->email,
                    'fullName' => $this->first_name . ' ' . $this->last_name,
                    'phone' => $this->phone,
                    'documentNumber' => (string) $this->document_number,
                    'documentType' => $this->document_type,
                ],
                'billingAddress' => [
                    'address' => $this->street_address,
                    'city' => $this->city,
                    'location' => $this->location,
                    'country' => 'CO'
                ]
            ];
            // Limpiar carrito y emitir evento al navegador
            CartManagement::clearCartItems();
            $this->dispatch('open-bold-checkout', config: $config);
        } else {
            // Pago contra entrega: Redirigir directamente
            CartManagement::clearCartItems();
            return redirect()->route('order.thanks', [
                'reference' => $order->reference,
                'payment' => 'cod'
            ]);
        }

        CartManagement::clearCartItems();
    }


}
