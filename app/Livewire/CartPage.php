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
    public $city = 'Bogotá'; // Default to Bogotá
    public $is_bogota = true; // Kept for backward compatibility logic
    public $selected_delivery_index = 0;
    public $location;
    public $cities = [];

    public function getProgressData()
    {
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

            if ($product) {
                // Check for generic 'fresh' definition requested by user: "Hongos Gourmet" or "Medicina Ancestral"
                // Using slug or name match. Assuming slugs for robustness but fallback to name match if needed.
                // Assuming standard slugs: 'hongos-gourmet', 'medicina-ancestral'
                // Or checking category name directly as requested.

                $categoryName = $product->category ? strtolower($product->category->name) : '';

                // Also support the generic 'fresco' check just in case, or solely rely on categories?
                // User said: "Si tiene una de esas 2 categorias entonces se trata de un hongo fresco"
                // This implies strict category check.

                $isFresh = false;
                if ($product->category) {
                    $slug = $product->category->slug;
                    // Check slugs or names
                    if (
                        in_array($slug, ['hongos-gourmet', 'medicina-ancestral']) ||
                        str_contains($categoryName, 'hongos gurmet') || // User used typo "gurmet" in prompt, handle it?
                        str_contains($categoryName, 'gourmet') ||
                        str_contains($categoryName, 'medicina ancestral')
                    ) {
                        $isFresh = true;
                    }
                }

                // Fallback: Name still contains 'fresco'?
                if (!$isFresh && str_contains(strtolower($product->name), 'fresco')) {
                    $isFresh = true;
                }

                if ($isFresh) {
                    return true;
                }
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

        // 1. Calculate max harvest date from preorders
        foreach ($this->cart_items as $item) {
            if (!empty($item['is_preorder'])) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $batch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);
                    if ($batch && $batch->estimated_harvest_date) {
                        $harvestDate = Carbon::parse($batch->estimated_harvest_date);
                        if ($harvestDate->gt($date)) {
                            $date = $harvestDate;
                        }
                    }
                }
            }
        }

        // 2. Find next valid delivery slots
        while (count($options) < 2) {
            // Logic: Thursday or Friday AND Even Day
            if (($date->isThursday() || $date->isFriday()) && ($date->day % 2 === 0)) {
                // If it is today, check cutoff (10 AM)
                // If date was bumped to harvest date (future), isToday is false, so condition passes.
                // If harvest date is TODAY, we still check cutoff.
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

    /**
     * Propiedad Computada: Costo de envío dinámico
     */
    #[Computed]
    public function shippingCost()
    {
        $subtotal = CartManagement::calculateGrandTotal($this->cart_items);

        // Determinar destino para el helper
        // Si is_bogota es true (manejado por el updatedCity), usamos 'Bogotá'
        // Si no, asumimos Nacional.
        $destino = $this->is_bogota ? 'Bogotá' : 'Nacional';

        return CartManagement::getShippingCost($subtotal, $destino, $this->location);
    }

    #[Computed]
    public function discountAmount()
    {
        $discount = 0;
        foreach ($this->cart_items as $item) {
            if (!empty($item['is_preorder'])) {
                // Determine original price. 
                // Current price is 90% of original.
                // Original = Current / 0.9
                // Discount = Original - Current
                $original_unit = $item['unit_amount'] / 0.9;
                $item_discount = ($original_unit - $item['unit_amount']) * $item['quantity'];
                $discount += $item_discount;
            }
        }
        return $discount;
    }

    #[Computed]
    public function recentBatches()
    {
        // Return recent batches for empty state
        // Assuming Batch model exists and has images. 
        // If not using Batch model directly in view for images, we'll need to check the model structure.
        return \App\Models\Batch::with('strain')->latest()->take(3)->get();
    }

    #[Computed]
    public function cartItemsWithMetadata()
    {
        $items = $this->cart_items;
        foreach ($items as &$item) {
            $item['delivery_date_label'] = null;
            if (!empty($item['is_preorder'])) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $batch = app(\App\Services\InventoryService::class)->getPreorderBatch($product);
                    if ($batch && $batch->estimated_harvest_date) {
                        $date = Carbon::parse($batch->estimated_harvest_date);
                        $item['delivery_date_label'] = $date->translatedFormat('d \d\e F');
                    }
                }
            }
        }
        return $items;
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
        $this->cities = CartManagement::getColombiaCities();
        sort($this->cities);

        // Recueprar estado de la sesión si existe (Persistencia)
        if (session()->has('checkout_shipping')) {
            $shipping = session('checkout_shipping');
            // Asegurar que exista la key 'city', si no, inferir de is_bogota
            if (isset($shipping['city'])) {
                $this->city = $shipping['city'];
            } else {
                // Fallback para sesiones viejas
                $this->city = ($shipping['is_bogota'] ?? true) ? 'Bogotá' : 'Medellín'; // Default fallback
            }

            $this->is_bogota = ($this->city === 'Bogotá');
            $this->location = $shipping['location'] ?? null;

            // Fuzzy Match for City Selection (e.g. "Medellin" vs "Medellín")
            if (!in_array($this->city, $this->cities)) {
                $closest = null;
                $minDist = 3; // Tolerance
                foreach ($this->cities as $c) {
                    $dist = levenshtein(strtolower(\Illuminate\Support\Str::ascii($this->city)), strtolower(\Illuminate\Support\Str::ascii($c)));
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closest = $c;
                    }
                }
                if ($closest) {
                    $this->city = $closest;
                }
            }
        }
    }

    // Update is_bogota when city changes
    public function updatedCity($value)
    {
        $this->is_bogota = ($value === 'Bogotá');
        if (!$this->is_bogota) {
            $this->location = null;
        }
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
        $selectedDate = $this->deliveryOptions[$this->selected_delivery_index]['date'] ?? null;

        session([
            'checkout_shipping' => [
                'is_bogota' => (bool) $this->is_bogota,
                'city' => $this->city, // Added city
                'location' => $this->location,
                'cost' => $this->shippingCost,
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