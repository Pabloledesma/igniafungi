<?php

namespace App\Services\Ai\Handlers;

use App\Models\ShippingZone;
use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\Contracts\ToolExecutor;
use App\Services\Ai\ConversationContext;
use App\Services\Ai\Traits\FuzzyMatcher;
use Illuminate\Support\Str;

class ShippingHandler implements IntentHandler, ToolExecutor
{
    use FuzzyMatcher;

    protected array $cities;

    public function __construct()
    {
        $this->cities = ShippingZone::pluck('city')->unique()->values()->toArray();
    }

    public function supportedTools(): array
    {
        return ['GET_SHIPPING_PRICE'];
    }

    public function executeTool(string $toolName, array $params, ConversationContext $context): string
    {
        $city = $params['city'] ?? '';
        $locality = $params['locality'] ?? null;
        $res = $this->getShippingInfo($city, $locality);

        if (isset($res['error'])) {
            return 'Error: '.$res['error'];
        }

        return json_encode($res);
    }

    public function canHandle(string $content, ConversationContext $context): bool
    {
        // Handle explicit questions about shipping
        if (
            Str::contains(strtolower($content), ['cuanto', 'costo', 'valor', 'precio']) &&
            Str::contains(strtolower($content), ['envio', 'domicilio', 'transporte', 'llevar'])
        ) {
            return true;
        }

        // Also handle if user just dropped a known city name (implicit location update)
        // This was logic in AiAgentService::processMessage "Maybe just a city name?"
        if ($this->inferLocationFromContent($content, $this->cities)) {
            return true;
        }

        return false;
    }

    public function handle(string $content, ConversationContext $context): array
    {
        $locationData = $this->inferLocationFromContent($content, $this->cities);

        $city = $locationData['city'] ?? $context->get('city');
        // Logic to extract locality from content if city is known?
        // Original service asked for locality if Bogota.

        if (! $city) {
            // Should not happen if canHandle passes via inferLocation, but safety check.
            $city = $context->get('city');
        }

        if (! $city) {
            return [
                'type' => 'question',
                'message' => '¿Para qué ciudad deseas cotizar el envío?',
            ];
        }

        // Update Context
        $context->set('city', $city);

        // Bogota Logic
        $isBogota = Str::slug($city) === 'bogota';
        $location = null;

        if ($isBogota) {
            // Try to extract locality from content
            // Very basic heuristic for now or use DB match
            $zones = ShippingZone::where('city', 'Bogotá')->get();
            foreach ($zones as $zone) {
                if (Str::contains(strtolower($content), strtolower($zone->locality))) {
                    $location = $zone->locality;
                    break;
                }
            }

            if ($location) {
                $context->set('locality', $location);
            } elseif (! $context->get('locality')) {
                // If we don't know locality yet
                return [
                    'type' => 'question',
                    'message' => 'Para Bogotá, el precio varía según la localidad. ¿En qué localidad te encuentras?',
                ];
            } else {
                $location = $context->get('locality');
            }
        }

        $shippingInfo = $this->getShippingInfo($city, $location);

        if (isset($shippingInfo['error'])) {
            return [
                'type' => 'system',
                'message' => $shippingInfo['error'],
            ];
        }

        // Check Fresh Product Restrictions for non-Bogota
        // This requires peeking at confirmed products or current intention?
        // Probably safe to just return price here. The OrderHandler deals with blocking.
        // BUT user rules say: "Filter Previo al Precio: Si Ciudad != Bogotá Y Producto == Fresco/Fresca".

        // Let's implement that Safe Guard
        if ($this->shouldBlockFresh($context, $city)) {
            return [
                'type' => 'suggestion',
                'message' => "Veo que estás en {$city}. Por la delicadeza del producto (Hongos Frescos), no enviamos frescos allí. ¿Te gustaría ver opciones deshidratadas?",
                'payload' => $this->getDrySuggestions(),
            ];
        }

        $formattedPrice = number_format($shippingInfo['price'], 0, ',', '.');
        $msg = "El envío a {$shippingInfo['city']}";
        if ($shippingInfo['locality']) {
            $msg .= ", {$shippingInfo['locality']}";
        }
        $msg .= " tiene un costo de \${$formattedPrice}.";

        // Append order closure prompt if we have items
        if (! empty($context->getConfirmedProductIds())) {
            $msg .= ' ¿Deseas agregar algún otro producto al pedido o generamos la orden?';
        }

        $response = [
            'type' => 'system',
            'message' => $msg,
        ];

        if (! empty($context->getConfirmedProductIds())) {
            $response['actions'] = [
                ['type' => 'more_products', 'label' => 'Agregar más productos'],
                ['type' => 'link', 'label' => 'Generar Orden', 'url' => route('cart')], // Or just a trigger
            ];
            // Actually the test expects 'more_products' type action.
        }

        return $response;
    }

    public function getShippingInfo($city, $locality = null)
    {
        $query = ShippingZone::where('city', $city);

        if ($locality) {
            // Try exact then fuzzy
            $match = ShippingZone::where('city', $city)
                ->where('locality', $locality)
                ->first();

            if (! $match) {
                $match = ShippingZone::where('city', $city)
                    ->where('locality', 'like', "%{$locality}%")
                    ->first();
            }
            if ($match) {
                return $match->toArray();
            }
        }

        // Default to first match for city if no locality needed (national)
        $msg = $query->first();

        if (! $msg) {
            // Fuzzy City Match Fallback
            $allCities = ShippingZone::distinct()->pluck('city')->toArray();
            $best = $this->findBestMatch($city, $allCities);
            if ($best) {
                return $this->getShippingInfo($best, $locality);
            }

            return ['error' => "Lo siento, no tengo cobertura registrada para {$city}."];
        }

        return $msg->toArray();
    }

    protected function shouldBlockFresh($context, $city): bool
    {
        if (Str::slug($city) === 'bogota') {
            return false;
        }

        $confirmedIds = $context->getConfirmedProductIds();
        if (empty($confirmedIds)) {
            return false;
        }

        // Check if any fresh product is in list
        // This mimics AiAgentService logic but using optimized query handling if possible
        // Ideally we caching product types in context, but querying DB is fine for now.
        $hasFresh = \App\Models\Product::whereIn('id', $confirmedIds)
            ->whereHas('category', fn ($q) => $q->whereIn('slug', ['hongos-gourmet', 'medicina-ancestral']))
            ->exists();

        return $hasFresh;
    }

    protected function getDrySuggestions()
    {
        // Simple dry suggestions
        return \App\Models\Product::whereHas('category', fn ($q) => $q->where('slug', 'like', '%deshidratad%'))
            ->limit(3)
            ->get(['id', 'name', 'price']) // Ensure price included!
            ->toArray();
    }
}
