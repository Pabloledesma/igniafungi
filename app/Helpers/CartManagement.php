<?php
namespace App\Helpers;

use App\Models\Product;
use Illuminate\Support\Facades\Cookie;


class CartManagement
{
    public const FREE_SHIPPING_THRESHOLD = 200000;
    public const DEFAULT_NATIONAL_SHIPPING = 45000;
    public const LOCALIDAD_PRECIOS = [
        'Engativa' => 9000,
        'Fontibon' => 9500,
        'Barrios Unidos' => 10000,
        'Teusaquillo' => 10500,
        'Suba' => 11000,
        'Puente Aranda' => 12000,
        'Chapinero' => 12500,
        'Martires' => 13000,
        'Usaquen' => 13500,
        'Kennedy' => 14000,
        'Santa Fe' => 14500,
        'Candelaria' => 15000,
        'Antonio Nariño' => 15500,
        'Rafael Uribe Uribe' => 16000,
        'Tunjuelito' => 16500,
        'San Cristobal' => 17500,
        'Bosa' => 18000,
        'Ciudad Bolivar' => 18500,
        'Usme' => 19500,
        'Sumapaz' => 20000,
    ];

    /**
     * Centraliza la lógica de cobro de envío.
     */
    public static function getShippingCost($subtotal, $city, $location = null)
    {
        // 1. Lógica específica para BOGOTÁ
        if ($city === 'Bogotá') {
            // Solo aquí validamos el umbral de envío gratis
            if ($subtotal >= self::FREE_SHIPPING_THRESHOLD) {
                return 0; // Envío gratis solo para Bogotá
            }

            // Si no alcanza el monto, se cobra según la localidad
            return self::LOCALIDAD_PRECIOS[$location] ?? 9000;
        }

        // 2. Lógica para el RESTO DEL PAÍS (Nacional)
        // Sin importar el subtotal, se cobra la tarifa estándar
        return self::DEFAULT_NATIONAL_SHIPPING; // Retorna 15000
    }

    public static function getColombiaCities()
    {
        return [
            'Leticia',
            'Medellín',
            'Arauca',
            'Barranquilla',
            'Cartagena',
            'Tunja',
            'Manizales',
            'Florencia',
            'Yopal',
            'Popayán',
            'Valledupar',
            'Quibdó',
            'Montería',
            'Bogotá',
            'Inírida',
            'San José del Guaviare',
            'Neiva',
            'Riohacha',
            'Santa Marta',
            'Villavicencio',
            'Pasto',
            'Cúcuta',
            'Mocoa',
            'Armenia',
            'Pereira',
            'San Andrés',
            'Bucaramanga',
            'Sincelejo',
            'Ibagué',
            'Cali',
            'Mitú',
            'Puerto Carreño',
            'Apartadó',
            'Bello',
            'Buenaventura',
            'Dosquebradas',
            'Envigado',
            'Floridablanca',
            'Girón',
            'Itagüí',
            'Magangué',
            'Maicao',
            'Palmira',
            'Piedecuesta',
            'Popayán',
            'Riohacha',
            'Soledad',
            'Tulua',
            'Tumaco',
            'Tunja',
            'Turbo',
            'Uribia',
            'Valledupar',
            'Villavicencio',
            'Yumbo'
        ];
    }

    /**
     * Retorna la lista de los 32 departamentos de Colombia.
     */
    public static function getColombiaDepartments()
    {
        return [
            'Amazonas',
            'Antioquia',
            'Arauca',
            'Atlántico',
            'Bolívar',
            'Boyacá',
            'Caldas',
            'Caquetá',
            'Casanare',
            'Cauca',
            'Cesar',
            'Chocó',
            'Córdoba',
            'Cundinamarca',
            'Guainía',
            'Guaviare',
            'Huila',
            'La Guajira',
            'Magdalena',
            'Meta',
            'Nariño',
            'Norte de Santander',
            'Putumayo',
            'Quindío',
            'Risaralda',
            'San Andrés y Providencia',
            'Santander',
            'Sucre',
            'Tolima',
            'Valle del Cauca',
            'Vaupés',
            'Vichada'
        ];
    }

    //add item to cart
    public static function addItemsToCart($product_id, $quantity = null, $is_preorder = false)
    {
        $cart_items = self::getCartItemsFromCookie();
        $existing_item_key = null;

        // Buscar si el producto ya existe en el carrito
        foreach ($cart_items as $key => $item) {
            // Check if product AND preorder status match
            $item_is_preorder = $item['is_preorder'] ?? false;

            if ($item['product_id'] == $product_id && $item_is_preorder === $is_preorder) {
                $existing_item_key = $key;
                break;
            }
        }

        if ($existing_item_key !== null) {
            // Lógica unificada: Incremento (+1) o Asignación directa
            if ($quantity === null) {
                $cart_items[$existing_item_key]['quantity']++;
            } else {
                $cart_items[$existing_item_key]['quantity'] = $quantity;
            }

            $cart_items[$existing_item_key]['total_amount'] =
                $cart_items[$existing_item_key]['quantity'] * $cart_items[$existing_item_key]['unit_amount'];

        } else {
            // Nuevo ítem: si $quantity es null, empezamos en 1
            $initial_qty = $quantity ?? 1;
            $product = Product::find($product_id, ['id', 'name', 'price', 'images']);

            if ($product) {
                // Apply 10% discount if preorder
                $unit_price = $product->price;
                if ($is_preorder) {
                    $unit_price = $unit_price * 0.9;
                }

                $cart_items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'image' => $product->getFirstImageAttribute(),
                    'unit_amount' => $unit_price,
                    'quantity' => $initial_qty,
                    'is_preorder' => $is_preorder,
                    'total_amount' => $unit_price * $initial_qty
                ];
            }
        }
        self::addCartItemsToCookie($cart_items);
        return count($cart_items);
    }

    //add cart items to cookie
    public static function addCartItemsToCookie($cart_items)
    {
        Cookie::queue('cart_items', json_encode($cart_items), 60 * 24 * 30);
    }

    public static function clearCartItems()
    {
        Cookie::expire('cart_items');
    }

    public static function getCartItemsFromCookie()
    {
        // 1. Buscamos primero si hay algo en la cola de cookies (vital para los tests)
        if (Cookie::hasQueued('cart_items')) {
            $cart_items = json_decode(Cookie::queued('cart_items')->getValue(), true);
        } else {
            // 2. Si no, buscamos en la cookie que viene de la petición
            $cart_items = json_decode(Cookie::get('cart_items'), true);
        }

        return $cart_items ?: [];
    }

    // Eliminar un ítem
    public static function removeCartItem($product_id)
    {
        $cart_items = self::getCartItemsFromCookie();

        $cart_items = array_filter($cart_items, function ($item) use ($product_id) {
            return $item['product_id'] != $product_id;
        });

        $cart_items = array_values($cart_items); // Reindexar
        self::addCartItemsToCookie($cart_items);

        return count($cart_items);
    }

    // Incrementar cantidad
    public static function incrementQuantityToCartItem($product_id)
    {
        $cart_items = self::getCartItemsFromCookie();

        foreach ($cart_items as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity']++;
                $item['total_amount'] = $item['quantity'] * $item['unit_amount'];
                break;
            }
        }

        self::addCartItemsToCookie($cart_items);
        return $cart_items;
    }

    public static function decrementQuantityToCartItem($product_id)
    {
        $cart_items = self::getCartItemsFromCookie();
        foreach ($cart_items as &$item) {
            if ($item['product_id'] == $product_id && $item['quantity'] > 1) {
                $item['quantity']--;
                $item['total_amount'] = $item['quantity'] * $item['unit_amount'];
                break; // Terminamos el bucle una vez encontrado
            }
        }
        self::addCartItemsToCookie($cart_items);
        return $cart_items;
    }

    public static function calculateGrandTotal($items)
    {
        return array_sum(array_column($items, 'total_amount'));
    }

}