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
    public $city;
    public $state;
    public $zip_code;
    public $payment_method;
    public $order_id = null;
    public $hash_integridad = null;
    public $total_amount_bold = 0;
    public $shipping_method; 
    public $shipping_cost = 0;
    public $data_customer_data;
    public $delivery_date_label = null;

    public function mount()
    {
        $this->cart_items = CartManagement::getCartItemsFromCookie();
        $this->grand_total = CartManagement::calculateGrandTotal($this->cart_items);

            // 1. Pre-llenar con datos del usuario autenticado
        if (auth()->check()) {
            $user = auth()->user();
            $this->first_name = $user->name; // O separa si tienes campos distintos
            $this->email = $user->email;
        }

        // 2. Recuperar la información de envío de la sesión (Bogotá/Fecha)
        $shipping_data = session('checkout_shipping');
        
        if ($shipping_data && $shipping_data['is_bogota']) {
            $this->city = 'Bogotá';
            $this->state = 'Cundinamarca';
            $this->shipping_cost = 15000;
            $this->shipping_method = 'domicilio';

            // Puedes asignar la fecha de entrega a una propiedad pública para mostrarla
            $this->delivery_date_label = $shipping_data['delivery_date'];
        }
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
            'state' => 'required|min:3|max:255',
            'zip_code' => 'required|min:5|max:255',
            'payment_method' => 'required|in:BOLD,COD'
        ]);
        
        $cart_items = CartManagement::getCartItemsFromCookie();
        $total = (int) CartManagement::calculateGrandTotal($cart_items);

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
        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->grand_total = CartManagement::calculateGrandTotal($cart_items) + $this->shipping_cost;
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
        $address->state = $this->state;
        $address->zip_code = $this->zip_code;
        $address->save();

        $order->items()->createMany($cart_items);

        if($this->payment_method == 'BOLD')
        {
            $this->order_id = $order->id;
            $this->total_amount_bold = (int) $order->grand_total;
            $moneda = "COP";
            
            // Preparar configuración para JS
            // En tu controlador
            $config = [
                'orderId'            => (string) $this->order_id,
                'currency'           => 'COP',
                'amount'             => (string) $this->total_amount_bold,
                'apiKey'             => env('BOLD_INTEGRATION_KEY'),
                'integritySignature' => hash('sha256', $this->order_id . $this->total_amount_bold . $moneda . env('BOLD_SECRET_KEY')),
                'description'        => "Pedido #{$this->order_id} en DCodeMania",
                'renderMode'         => 'embedded',
                // IMPORTANTE: Enviar como array para que JS lo convierta a string después
                'customerData'       => [
                    'email'          => $this->email,
                    'fullName'       => $this->first_name . ' ' . $this->last_name,
                    'phone'          => $this->phone,
                    'documentNumber' => (string) $this->document_number,
                    'documentType'   => $this->document_type,
                ],
                'billingAddress'     => [
                    'address'        => $this->street_address,
                    'zipCode'        => $this->zip_code,
                    'city'           => $this->city,
                    'state'          => $this->state,
                    'country'        => 'CO' // Código de país de 2 dígitos
                ]
            ];
            // Limpiar carrito y emitir evento al navegador
            CartManagement::clearCartItems();
            $this->dispatch('open-bold-checkout', config: $config);
        } else {
            // Pago contra entrega: Redirigir directamente
            return redirect()->route('success');
        }

        CartManagement::clearCartItems();
    }

   
}
