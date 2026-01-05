<?php
namespace App\Helpers;

use App\Models\Product;
use Illuminate\Support\Facades\Cookie;


class CartManagement
{
    public const FREE_SHIPPING_THRESHOLD = 200000;
    public const DEFAULT_NATIONAL_SHIPPING = 15000;
    public const LOCALIDAD_PRECIOS = [
        'Engativa'          => 9000,
        'Fontibon'          => 9500,
        'Barrios Unidos'    => 10000,
        'Teusaquillo'       => 10500,
        'Suba'              => 11000,
        'Puente Aranda'     => 12000,
        'Chapinero'         => 12500,
        'Martires'          => 13000,
        'Usaquen'           => 13500,
        'Kennedy'           => 14000,
        'Santa Fe'          => 14500,
        'Candelaria'        => 15000,
        'Antonio Nariño'    => 15500,
        'Rafael Uribe Uribe'=> 16000,
        'Tunjuelito'        => 16500,
        'San Cristobal'     => 17500,
        'Bosa'              => 18000,
        'Ciudad Bolivar'    => 18500,
        'Usme'              => 19500,
        'Sumapaz'           => 20000,
    ];
    
    /**
     * Centraliza la lógica de cobro de envío.
     */
    public static function getShippingCost($subtotal, $city, $location = null) 
    {
        // 1. Regla de Envío Gratis
        if ($subtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0;
        }

        // 2. Lógica para Bogotá
        if ($city === 'Bogotá') {
            // Buscamos el precio en la constante propia de la clase
            return self::LOCALIDAD_PRECIOS[$location] ?? self::DEFAULT_NATIONAL_SHIPPING;
        }

        // 3. Tarifa Nacional
        return self::DEFAULT_NATIONAL_SHIPPING;
    }
    
    //add item to cart
    public static function addItemsToCart($product_id, $quantity = null)
    {
        $cart_items = self::getCartItemsFromCookie();
        $existing_item_key = null;

        // Buscar si el producto ya existe en el carrito
        foreach($cart_items as $key => $item) {
            if($item['product_id'] == $product_id) {
                $existing_item_key = $key;
                break;
            }
        }

        if($existing_item_key !== null) {
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
            
            if($product) {
                $cart_items[] = [
                    'product_id'   => $product->id,
                    'name'         => $product->name,
                    'image'        => $product->getFirstImageAttribute(),
                    'unit_amount'  => $product->price,
                    'quantity'     => $initial_qty,
                    'total_amount' => $product->price * $initial_qty
                ];
            }
        }
        self::addCartItemsToCookie($cart_items);
        return count($cart_items);
    }

    //add cart items to cookie
    public static function addCartItemsToCookie($cart_items)
    {
        Cookie::queue('cart_items', json_encode($cart_items), 60*24*30);
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
        foreach($cart_items as &$item) 
        {
            if($item['product_id'] == $product_id && $item['quantity'] > 1){
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