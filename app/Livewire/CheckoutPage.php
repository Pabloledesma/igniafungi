<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Address;
use Livewire\Component;
use Livewire\Attributes\Title;
use App\Helpers\CartManagement;
use Illuminate\Support\Facades\Session;


#[Title('Checkout Page')]
class CheckoutPage extends Component
{
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
    public $delivery_date_label = null;
    public $city;
    public $state;
    public $zip_code;
    public $departmet;
    public $free_shipping_if = CartManagement::FREE_SHIPPING_THRESHOLD;
    public $location; // Para la localidad
    public $cities = ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena'];
    public $localidadPrecios = [
        // ANILLO 1: Vecinos inmediatos o misma localidad
        'Engativa'          => 9000,
        'Fontibon'          => 9500,
        'Barrios Unidos'    => 10000,
        'Teusaquillo'       => 10500,
        'Suba'              => 11000,

        // ANILLO 2: Centro y Occidente cercano
        'Puente Aranda'     => 12000,
        'Chapinero'         => 12500,
        'Martires'          => 13000,
        'Usaquen'           => 13500,
        'Kennedy'           => 14000,

        // ANILLO 3: Centro-Sur
        'Santa Fe'          => 14500,
        'Candelaria'        => 15000,
        'Antonio Nariño'    => 15500,
        'Rafael Uribe Uribe'=> 16000,
        'Tunjuelito'        => 16500,

        // ANILLO 4: Periferia Sur (Mayor distancia)
        'San Cristobal'     => 17500,
        'Bosa'              => 18000,
        'Ciudad Bolivar'    => 18500,
        'Usme'              => 19500,
        'Sumapaz'           => 20000,
    ];

    public function updatedLocation($value) 
    {
        $this->calculateShipping();
    }

    public function updatedCity($value) 
    {
        if ($value !== 'Bogotá') {
            $this->location = null;
        }
        $this->calculateShipping();
    }

    protected function calculateShipping() 
    {
        $subtotal = (int) CartManagement::calculateGrandTotal($this->cart_items);

        // Invocamos al Helper centralizado
        $this->shipping_cost = CartManagement::getShippingCost(
            $subtotal, 
            $this->city, 
            $this->location, 
            $this->localidadPrecios
        );

        $this->grand_total = $subtotal + $this->shipping_cost;
    }

    public function mount()
    {
        $this->first_name = auth()->user()->name;
        $this->email = auth()->user()->email;

        $this->cart_items = CartManagement::getCartItemsFromCookie();
        // Inicializamos el envío según lo que venga de la sesión del carrito
        $shipping_data = session('checkout_shipping');
        
        if ($shipping_data) {
            $this->city = ($shipping_data['is_bogota'] ?? false ) ? 'Bogotá' : null;
            $this->delivery_date_label = $shipping_data['delivery_date'] ?? null;
            $this->location = $shipping_data['location'] ?? null;
            $this->shipping_cost = $shipping_data['cost'] ?? 0;
        }

        $this->calculateShipping();
    }

    public function placeOrder()
    {
        $this->validate([
            'first_name' => 'required|min:3|max:255',
            'last_name' => 'required|min:3|max:255',
            'document_number' => 'required|numeric',
            'document_type' => 'required|in:CC,CE,NIT,PP',
            'phone' => 'required',
            'email' => 'required|email',
            'street_address' => 'required|min:3|max:255',
            'city' => 'required|min:3|max:255',
            'payment_method' => 'required|in:BOLD,COD'
        ]);
        
        $cart_items = CartManagement::getCartItemsFromCookie();
        $subtotal = (int) CartManagement::calculateGrandTotal($cart_items);

        foreach($cart_items as $item)
        {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'cop',
                    'unit_amount' => $item['unit_amount']*100,
                    'product_data' => [
                        'name' => $item['name']
                    ]
                ],
                'quantity' => $item['quantity'] 
            ];
            
        }

        $final_total = $subtotal + (int) $this->shipping_cost;
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->grand_total = $final_total;
        $order->payment_method = $this->payment_method;
        $order->payment_status = 'pending';
        $order->shipping_amount = $this->shipping_cost;
        $order->status = 'new';
        $order->currency = 'cop';
        $order->notes = 'Order placed by ' . auth()->user()->name;
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

        $order->items()->createMany($cart_items);

        if($this->payment_method == 'BOLD')
        {
            $this->order_id = $order->id;
            $this->total_amount_bold = (int) $order->grand_total;
            $moneda = "COP";
            
            $config = [
                'orderId'            => (string) $this->order_id,
                'currency'           => 'COP',
                'amount'             => (string) $this->total_amount_bold,
                'apiKey'             => config('services.bold.key'),
                'integritySignature' => hash('sha256', $this->order_id . $this->total_amount_bold . $moneda . config('services.bold.secret')),
                'description'        => "Pedido #{$this->order_id} en Ignia Fungi",
                'renderMode'         => 'embedded',
                'redirectionUrl'     => str_replace('http://localhost:8000', 'https://dev.igniafungi.com', route('order.thanks', ['reference' => $order->reference])),
                'customerData'       => [
                    'email'          => $this->email,
                    'fullName'       => $this->first_name . ' ' . $this->last_name,
                    'phone'          => $this->phone,
                    'documentNumber' => (string) $this->document_number,
                    'documentType'   => $this->document_type,
                ],
                'billingAddress'     => [
                    'address'        => $this->street_address,
                    'city'           => $this->city,
                    'location'          => $this->location,
                    'country'        => 'CO' 
                ]
            ];
            // Limpiar carrito y emitir evento al navegador
            CartManagement::clearCartItems();
            $this->dispatch('open-bold-checkout', config: $config);
        } else {
            // Pago contra entrega: Redirigir directamente
            return redirect()->route('order.thanks', ['reference' => $order->reference]);
        }

        CartManagement::clearCartItems();
    }

   
}
