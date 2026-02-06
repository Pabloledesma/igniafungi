<?php

namespace App\Services\Ai\Handlers;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Models\Product;
use App\Models\ShippingZone;
use Illuminate\Support\Str;
use App\Services\Ai\Traits\FuzzyMatcher;

class OrderHandler implements IntentHandler
{
    use FuzzyMatcher;

    public function canHandle(string $content, ConversationContext $context): bool
    {
        $normalized = Str::lower(preg_replace('/[^\w\s]/u', '', $content));
        $normalized = trim($normalized);

        // 1. Strong Intent (Always Handle, even if cart empty to give feedback)
        // e.g., "Generar orden", "Quiero comprar", "Confirmar"
        if ($this->isStrongOrderIntent($normalized)) {
            return true;
        }

        // 2. Weak Affirmation (Only handle if we have products pending)
        // e.g., "Si", "De una", "Ok"
        // If user says "Si" in response to "Do you want to see catalog?", we should NOT handle it here.
        if ($this->isWeakAffirmation($normalized)) {
            $hasProducts = !empty($context->getConfirmedProductIds());
            if ($hasProducts) {
                return true;
            }
            // If no products, let it fall through to Gemini/CatalogHandler
        }

        return false;
    }

    public function handle(string $content, ConversationContext $context): array
    {
        // 1. Check if we have pending products
        $confirmedProducts = $context->getConfirmedProductIds();
        \Illuminate\Support\Facades\Log::info("OrderHandler Debug: Confirmed=" . json_encode($confirmedProducts) . ", City=" . $context->get('city'));

        if (empty($confirmedProducts)) {
            // Nothing to order? Fallback to Gemini or generic message?
            // Original logic might have handled this differently.
            // For now, if affirmation but empty cart, allow fallback or specific message.
            return [
                'type' => 'system',
                'message' => 'Entiendo, pero no sé qué producto deseas confirmar. Por favor indícame qué te gustaría ordenar.'
            ];
        }

        // 2. Check Auth for Lazy Registration
        if (!auth()->check()) {
            session(['ai_waiting_for_user_data' => true]);
            return [
                'type' => 'question',
                'message' => '¡Excelente! Tengo todo listo para tu pedido. Para enviarte el resumen y generar la orden... dime tu nombre y correo.'
            ];
        }

        return $this->processOrder($context);
    }

    protected function isStrongOrderIntent(string $normalized): bool
    {
        $strongKeywords = [
            'generar orden',
            'generemos',
            'generar',
            'proceder',
            'confirmar',
            'comprar',
            'hagámosle',
            'los quiero',
            'quiero comprar',
            'carrito',
            'agregala',
            'lo quiero',
            'cobrame',
            'facturar'
        ];

        foreach ($strongKeywords as $word) {
            if (Str::contains($normalized, $word)) {
                return true;
            }
        }
        return false;
    }

    protected function isWeakAffirmation(string $normalized): bool
    {
        $weakAffirmatives = [
            'si',
            'sí',
            'dale',
            'acepto',
            'bueno',
            'ok',
            'está bien',
            'claro',
            'de una',
            'perfecto',
            'listo',
            'hágale',
            'asi es',
            'así es',
            'correcto'
        ];

        foreach ($weakAffirmatives as $word) {
            if ($normalized === $word || str_starts_with($normalized, $word . ' ')) {
                return true;
            }
        }
        return false;
    }

    public function processOrder(ConversationContext $context): array
    {
        // Logic extracted from handleOrderConfirmation
        $city = $context->get('city');
        $locality = $context->get('locality');

        // Check Fresh Restrictions
        $productsAdded = [];
        $ignoredFresh = false;
        $isBogota = $city && Str::slug($city) === 'bogota';

        $confirmedIds = $context->getConfirmedProductIds();
        $products = Product::whereIn('id', $confirmedIds)->get();

        foreach ($products as $product) {
            $isFresh = $this->isFreshProduct($product);

            if ($isFresh && !$isBogota && $city) {
                $ignoredFresh = true; // Cannot ship fresh outside Bogota
            } else {
                // Add to "cart" (cookie handled by helper later? or just link generation)
                // New logic: Use CartManagement helper here or just generate link with params?
                // The original logic used CartManagement::addItemToCartWithCookie.
                // For this refactor, we assume we just generate the link or delegate to helper.

                // CRITICAL: Original service modified cookie/session.
                // We will rely on the endpoint /cart to handle it via query params OR session persistence 'checkout_shipping'.

                $productsAdded[] = $product->name;
            }
        }

        // Calculate Cost (Pre-calculation for persistence)
        $cost = 0;
        if ($city) {
            $zone = ShippingZone::where('city', $city);
            if ($locality) {
                $zone->where('locality', 'like', "%{$locality}%");
            }
            $cost = $zone->first()->price ?? 0;
        }

        // Persist Session (Even if we block later)
        session()->put('checkout_shipping', [
            'is_bogota' => $isBogota,
            'city' => $city,
            'location' => $locality,
            'cost' => $cost,
            'delivery_date' => null
        ]);

        // If failure due to fresh products
        if ($ignoredFresh && empty($productsAdded)) {
            return [
                'type' => 'suggestion',
                'message' => "❌ **No pude agregar los productos.**\n\nLos hongos frescos no están disponibles para envío a **{$city}** (solo Bogotá). ¿Te gustaría ver las versiones deshidratadas?",
                'payload' => $this->findDryProducts($products)->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price
                ])->toArray()
            ];
        }

        // Success - Generate Link
        $cartUrl = route('cart');

        // Add confirmed products to Cart Cookie via Helper execution context? 
        // Actually, in the Service, it called `CartManagement::addItemToCartWithCookie($product->id, 1);`
        // We do that here:
        foreach ($products as $p) {
            if (!$this->isFreshProduct($p) || $isBogota) {
                \App\Helpers\CartManagement::addItemsToCart($p->id, 1);
            }
        }

        // Clear confirmed context
        $context->forget('confirmed_products');

        return [
            'type' => 'system',
            'message' => "¡Listo! He añadido los productos a tu carrito. 🛒<br><br>Hemos configurado el envío para <strong>{$city}" . ($locality ? " ({$locality})" : "") . "</strong>.<br><br>👉 <a href='{$cartUrl}' class='text-green-600 font-bold underline'>Haz clic aquí para finalizar tu compra</a>",
            'actions' => [
                ['label' => '💳 Ir a Pagar', 'type' => 'link', 'url' => $cartUrl]
            ]
        ];
    }

    protected function isFreshProduct(Product $p): bool
    {
        // Simple logic based on category slug or name
        $bySlug = $p->category && in_array($p->category->slug, ['hongos-gourmet', 'medicina-ancestral']);
        $byName = str_contains(strtolower($p->name), 'fresc');

        return $bySlug || $byName;
    }

    protected function findDryProducts($sourceProducts)
    {
        // Simplified Logic: Return all dry products
        return Product::whereHas('category', function ($q) {
            $q->where('slug', 'like', '%deshidratad%')->orWhere('slug', 'like', '%sec%');
        })->limit(3)->get();
    }
}
