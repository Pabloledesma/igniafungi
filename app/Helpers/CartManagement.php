<?php
namespace App\Helpers;

use App\Models\Product;
use Illuminate\Support\Facades\Cookie;


class CartManagement
{
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