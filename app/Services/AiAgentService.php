<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AiAgentService
{
    /**
     * Valida y procesa un mensaje del usuario.
     */
    public function processMessage(string $content, string $ip, ?array $context = []): array
    {
        // 0. Context Inference: Check if message is just a City or Locality
        // This solves "Respondi la pregunta de la ciudad y no busco"
        $locationContext = $this->inferLocationFromContent($content);
        if ($locationContext) {
            $context['city'] = $locationContext['city'];
            if (isset($locationContext['locality'])) {
                $context['locality'] = $locationContext['locality'];
            }

            // If we inferred a location, we assume it's a shipping or availability query context.
            // Priority: Shipping > Availability
            // If the user just says "Bogota", we assume they are answering the shipping question.
            return $this->handleShippingQuery($content, $context);
        }

        // 1. Rate Limiting
        $key = 'ai_chat:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return [
                'type' => 'error',
                'message' => 'Has excedido el límite de mensajes. Por favor intenta de nuevo en un minuto.'
            ];
        }
        RateLimiter::hit($key, 60);

        // 2. Spam/Junk Filter
        if ($this->isSpam($content)) {
            return [
                'type' => 'ignore',
                'message' => 'Mensaje ignorado por política de contenido.'
            ];
        }

        // 3. Lead Generation (Simple intent detection)
        if ($this->detectLeadIntent($content)) {
            $user = $this->createOrUpdateLead($content, $context);
            if ($user) {
                return [
                    'type' => 'system',
                    'message' => "¡Gracias {$user->name}! Hemos registrado tus datos para contactarte."
                ];
            }
        }

        // 4. Business Logic: Shipping
        if ($this->isShippingQuery($content)) {
            return $this->handleShippingQuery($content, $context);
        }

        // 5. Business Logic: Availability/Stock (New)
        if ($this->isAvailabilityQuery($content)) {
            return $this->handleAvailabilityQuery();
        }

        // 6. Business Logic: Products/Freshness
        // Simple keyword search for products in the message
        $product = $this->detectProduct($content);
        if ($product) {
            return $this->handleProductQuery($product, $context);
        }

        // En lugar de ir directo a callLlm
        if ($this->isShippingQuery($content) || $this->isAvailabilityQuery($content)) {
            $result = $this->handleShippingQuery($content, $context);

            if (isset($result['type']) && $result['type'] === 'handoff') {
                return [
                    'type' => 'question',
                    'message' => "No estoy seguro de haber entendido la ubicación o el producto. ¿Podrías escribirlo de nuevo? (Ej: 'Envío a Medellín' o '¿Tienen Orellanas?')"
                ];
            }
        }

        // 7. LLM Communication (Stub)
        return $this->callLlm($content);
    }

    protected function isAvailabilityQuery(string $content): bool
    {
        $phrases = ['disponibles', 'que tienes', 'tienen', 'stock', 'variedades', 'cuales hongos', 'que venden'];
        foreach ($phrases as $phrase) {
            if (stripos($content, $phrase) !== false)
                return true;
        }
        return false;
    }

    protected function handleAvailabilityQuery(): array
    {
        $products = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            return [
                'type' => 'answer',
                'message' => "Lo sentimos, en este momento no tenemos stock disponible. Vuelve pronto."
            ];
        }

        $list = $products->map(function ($p) {
            return "• *{$p->name}*: $" . number_format($p->price, 0);
        })->join("<br>");

        return [
            'type' => 'answer',
            'message' => "¡Claro! Estos son los hongos que tenemos disponibles para ti hoy:<br><br>" . $list
        ];
    }

    protected function isSpam(string $content): bool
    {
        if (strlen($content) < 2)
            return true; // Too short
        $spamKeywords = ['casino', 'viagra', 'buy crypto', 'free money'];
        foreach ($spamKeywords as $keyword) {
            if (stripos($content, $keyword) !== false)
                return true;
        }
        return false;
    }

    protected function findBestMatch(string $input, iterable $options): ?string
    {
        $bestMatch = null;
        $shortestDistance = -1;

        foreach ($options as $option) {
            // Normalizamos ambos textos (quitar tildes, minúsculas)
            $normalizedOption = Str::ascii(strtolower($option));
            $normalizedInput = Str::ascii(strtolower($input));

            // Calculamos la diferencia
            $distance = levenshtein($normalizedInput, $normalizedOption);

            if ($distance === 0)
                return $option; // Match exacto

            if ($distance <= 2 && ($shortestDistance === -1 || $distance < $shortestDistance)) {
                $bestMatch = $option;
                $shortestDistance = $distance;
            }
        }

        return $bestMatch;
    }

    protected function detectLeadIntent(string $content): bool
    {
        return stripos($content, 'crear cuenta') !== false ||
            stripos($content, 'registrarme') !== false ||
            stripos($content, 'quiero comprar') !== false;
    }

    protected function createOrUpdateLead(string $content, array $context): ?User
    {
        // Esta es una implementación simplificada para extraer datos
        // En un caso real, el LLM haría esta extracción estructurada
        // Formato esperado: "soy Nombre, email@test.com, Ciudad"

        // Simple regex extraction for email
        preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}/i', $content, $matches);
        $email = $matches[0] ?? null;

        if (!$email)
            return null;

        // Try to get name
        $name = 'Usuario'; // Default
        // Logic to extract name would go here, simplified for now

        $city = $context['city'] ?? 'Bogotá'; // Default fallback

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(16)), // Temp password
                // 'city' => $city // Assuming user has generic fields or profile
            ]
        );
    }

    protected function isShippingQuery(string $content): bool
    {
        return stripos($content, 'envio') !== false ||
            stripos($content, 'domicilio') !== false ||
            stripos($content, 'costo') !== false;
    }

    protected function handleShippingQuery(string $content, array $context): array
    {
        // 1. Check for Free Shipping Threshold
        $cartTotal = $context['cart_total'] ?? 0;
        if ($cartTotal > 200000) {
            return [
                'type' => 'answer',
                'message' => '¡Felicidades! Tu compra supera los $200.000 COP, así que el envío es totalmente GRATIS.'
            ];
        }

        $city = $context['city'] ?? null;
        $locality = $context['locality'] ?? null;

        if (!$city) {
            // New Feature: Try to infer city/locality from content (Reverse Lookup)
            // Get all Bogotá localities
            $bogotaLocalities = ShippingZone::where('city', 'Bogotá')->whereNotNull('locality')->pluck('locality');

            foreach ($bogotaLocalities as $dbLocality) {
                if (stripos($content, $dbLocality) !== false) {
                    $city = 'Bogotá';
                    $locality = $dbLocality;
                    $inferred = true;
                    break;
                }
            }

            if (!$city) {
                return [
                    'type' => 'question',
                    'message' => 'Para calcular el costo del envío, necesito saber ¿en qué ciudad te encuentras?'
                ];
            }
        }

        // 2. Get Shipping Info
        $info = $this->getShippingInfo($city, $locality);

        if (isset($info['error'])) {
            // If error is asking for locality, ask user
            if (str_contains(strtolower($info['error']), 'localidad')) {
                return [
                    'type' => 'question',
                    'message' => $info['error']
                ];
            }
            // Fallback to human if city not found
            return $this->callLlm($content);
        }

        $targetCity = $info['city'];
        $price = number_format($info['price'], 0);
        $matchedLocality = $info['locality'] ?? null;

        // 3. Strict Product Validation for Non-Bogota
        if (Str::slug($targetCity) !== 'bogota') {
            // Check if user is asking for a Fresh product or has one in context
            // We re-detect product in content just in case, or use context
            $product = $this->detectProduct($content); // Use robust detection

            $isFreshRequest = false;
            if ($product) {
                if (
                    ($product->category && str_contains(strtolower($product->category->name), 'fresco')) ||
                    str_contains(strtolower($product->name), 'fresco')
                ) {
                    $isFreshRequest = true;
                }
            }

            // Auto-suggest logic if Fresh requested OR just as a general rule
            // "Si el usuario preguntó por un producto fresco o no especificó uno" -> Default to warning
            if ($isFreshRequest || !$product) {
                $dryProducts = $this->findDryProducts();
                $list = $dryProducts->map(function ($p) {
                    return "• {$p->name} (${$p->price})";
                })->join("<br>");

                return [
                    'type' => 'suggestion',
                    'message' => "El costo de envío a {$targetCity} es de ${price} COP.<br><br>⚠️ <strong>Importante:</strong> En {$targetCity} no podemos entregar productos frescos (solo en Bogotá), pero tenemos disponibles estos productos secos para ti:<br>{$list}"
                ];
            }
        }

        // Bogota or Acceptable Product
        $locSuffix = $matchedLocality ? ", localidad {$matchedLocality}" : "";
        return [
            'type' => 'answer',
            'message' => "El costo de envío a {$targetCity}{$locSuffix} es de ${price} COP."
        ];
    }

    protected function findDryProducts()
    {
        return Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('name', 'like', '%deshidratado%')
                        ->orWhere('name', 'like', '%seco%');
                })
                    ->orWhere('name', 'like', '%deshidratado%')
                    ->orWhere('name', 'like', '%seco%');
            })
            ->limit(5)
            ->get();
    }

    /**
     * Tool para consultar costo de envío.
     * Puede ser usada por el Agente o por lógica interna.
     *
     * @tool getShippingInfo
     * @description Busca el costo de envío para una ciudad y localidad dadas. Utiliza coincidencia aproximada (fuzzy matching) para encontrar la ciudad más cercana en la base de datos si la entrada tiene errores tipográficos.
     * @param string $city Nombre de la ciudad (ej. "Medellín", "Bogotá").
     * @param string|null $locality (Opcional) Localidad específica para Bogotá (ej. "Usaquén", "Suba").
     * @return array Retorna un array con:
     *      - 'price' (int): Costo del envío en COP.
     *      - 'city' (string): Nombre normalizado de la ciudad encontrada.
     *      - 'locality' (string|null): Localidad encontrada (si aplica).
     *      - 'error' (string): Mensaje de error si no se encuentra cobertura o falta información.
     */
    public function getShippingInfo(string $city, ?string $locality = null): array
    {
        // 1. Get all available cities
        $dbCities = ShippingZone::select('city')->distinct()->pluck('city');

        // 2. Fuzzy Search for City
        $matchedCity = $this->findBestMatch($city, $dbCities);

        if (!$matchedCity) {
            return ['error' => "Lo siento, no cubrimos esa ciudad en nuestra base de datos. (No encontré coincidencias cercanas para '{$city}')"];
        }

        // Logic for Bogotá
        if (Str::slug($matchedCity) === 'bogota') {
            if (!$locality) {
                return ['error' => "¿En qué localidad de Bogotá te encuentras? (ej. Usaquén, Chapinero)"];
            }

            // Fuzzy Search for Locality inside Bogotá
            $dbLocalities = ShippingZone::where('city', $matchedCity)->whereNotNull('locality')->pluck('locality');
            $matchedLocality = $this->findBestMatch($locality, $dbLocalities);

            if (!$matchedLocality) {
                return ['error' => "No encontramos cobertura específica para esa localidad ('{$locality}')."];
            }

            $zone = ShippingZone::where('city', $matchedCity)
                ->where('locality', $matchedLocality)
                ->first();

            return ['price' => $zone->price, 'city' => $matchedCity, 'locality' => $matchedLocality];
        }

        // National Shipping (City only)
        $zone = ShippingZone::where('city', $matchedCity)->whereNull('locality')->first();

        if ($zone) {
            return ['price' => $zone->price, 'city' => $matchedCity];
        }

        return ['error' => "No tenemos tarifa registrada para {$matchedCity}."];
    }

    protected function detectProduct(string $content): ?Product
    {
        // 1. Exact/Approximate Name Match using Fuzzy Search
        // Get all active product names
        $allProducts = Product::where('is_active', true)->pluck('name', 'id');

        // Check for best match in the whole content string 
        // (Improving simple explode approach)
        $bestMatchName = $this->findBestMatch($content, $allProducts);

        if ($bestMatchName) {
            // Retrieve by name
            return Product::where('name', $bestMatchName)->first();
        }

        // Fallback: Word by word check (Legacy but useful)
        $words = explode(' ', $content);
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $match = $this->findBestMatch($word, $allProducts);
                if ($match) {
                    return Product::where('name', $match)->first();
                }
            }
        }

        return null;
    }

    protected function handleProductQuery(Product $product, array $context): array
    {
        $city = $context['city'] ?? '';

        $isFresh = false;
        if ($product->category && str_contains(strtolower($product->category->name), 'fresco'))
            $isFresh = true;
        if (str_contains(strtolower($product->name), 'fresco'))
            $isFresh = true;

        if ($isFresh) {
            if (strtolower($city) !== 'bogotá' && strtolower($city) !== 'bogota') {
                // Suggest Dry
                return [
                    'type' => 'suggestion',
                    'message' => "El producto '{$product->name}' es fresco y solo se entrega en Bogotá. Para tu ciudad te recomendamos nuestros hongos deshidratados."
                ];
            }
        }

        return [
            'type' => 'answer',
            'message' => "Tenemos '{$product->name}' disponible. " . $product->short_description
        ];
    }

    protected function callLlm(string $content): array
    {
        // 1. Notify Slack Channel
        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if ($token && $channel) {
            Http::withToken($token)->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => "🚨 *Atención Humana Requerida* \n\n*Usuario:* Guest\n*Mensaje:* {$content}\n*Contexto:* Fallback de Agente IA"
            ]);
        }

        return [
            'type' => 'handoff',
            'message' => "He notificado a un agente humano sobre tu consulta. Te contactaremos por este medio o al correo registrado en breve."
        ];
    }
    protected function inferLocationFromContent(string $content): ?array
    {
        // 1. Check for Bogota Localities
        $bogotaLocalities = ShippingZone::where('city', 'Bogotá')->whereNotNull('locality')->pluck('locality');
        foreach ($bogotaLocalities as $dbLocality) {
            if (stripos($content, $dbLocality) !== false) {
                return ['city' => 'Bogotá', 'locality' => $dbLocality];
            }
        }

        // 2. Check for Cities
        $dbCities = ShippingZone::select('city')->distinct()->pluck('city');
        foreach ($dbCities as $dbCity) {
            // Check exact match or normalized match for single words (e.g. "Cali", "Bogota")
            if (Str::slug($content) === Str::slug($dbCity) || stripos($content, $dbCity) !== false) {
                return ['city' => $dbCity];
            }
        }

        return null;
    }
}
