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
        return $this->isAffirmation($content);
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

        return $this->processOrder($context);
    }

    protected function isAffirmation(string $content): bool
    {
        $normalized = Str::lower(preg_replace('/[^\w\s]/u', '', $content));
        $normalized = trim($normalized);

        $affirmatives = [
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
            'generar orden',
            'generemos',
            'generar',
            'proceder',
            'confirmar',
            'comprar',
            'hagámosle'
        ];

        foreach ($affirmatives as $word) {
            if ($normalized === $word || str_starts_with($normalized, $word . ' ')) {
                return true;
            }
        }

        if (Str::contains($normalized, ['los quiero', 'quiero comprar', 'carrito', 'agregala', 'lo quiero'])) {
            return true;
        }

        return false;
    }

    protected function processOrder(ConversationContext $context): array
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

        // Calculate Cost
        $cost = 0;
        if ($city) {
            $zone = ShippingZone::where('city', $city);
            if ($locality) {
                $zone->where('locality', 'like', "%{$locality}%");
            }
            $cost = $zone->first()->price ?? 0;
        }

        session()->put('checkout_shipping', [
            'is_bogota' => $isBogota,
            'city' => $city,
            'location' => $locality,
            'cost' => $cost,
            'delivery_date' => null
        ]);

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
