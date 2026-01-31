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
        // 0. Load Context from Session (Persistence)
        $sessionContext = session('ai_context', []);
        $context = array_merge($sessionContext, $context);

        // 0.1 Context Inference: Check if message is just a City or Locality
        $locationContext = $this->inferLocationFromContent($content);
        if ($locationContext) {
            $context['city'] = $locationContext['city'];
            if (isset($locationContext['locality'])) {
                $context['locality'] = $locationContext['locality'];
            }
            // Update Session
            session(['ai_context' => $context]);

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

        // 1.5 Strict Coherence Validation (Fresh vs City) on Affirmation
        // If user says "Yes/Dale/Acepto", check if what they accepted is valid for their city
        if ($this->isAffirmation($content)) {
            // Check for Pending Suggestions (Order Confirmation)
            if (isset($context['pending_suggestion_products'])) {
                return $this->handleOrderConfirmation($context);
            }

            if (isset($context['city']) && isset($context['last_offered_product_type'])) {
                if ($context['last_offered_product_type'] === 'fresh' && Str::slug($context['city']) !== 'bogota') {
                    return [
                        'type' => 'suggestion',
                        'message' => "Entiendo que quieres continuar, pero recuerda que **en {$context['city']} solo podemos entregar productos secos**. ¿Te gustaría ver nuestras opciones deshidratadas?"
                    ];
                }
            }
        }

        // 2. Spam/Junk Filter (Keep original)
        if ($this->isSpam($content)) {
            return [
                'type' => 'ignore',
                'message' => 'Mensaje ignorado por política de contenido.'
            ];
        }

        // 3. Lead Generation (Keep original)
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
        $product = $this->detectProduct($content);
        if ($product) {
            // Check coherence BEFORE handling
            if (isset($context['city']) && Str::slug($context['city']) !== 'bogota') {
                $isFresh = ($product->category && str_contains(strtolower($product->category->name), 'fresco')) || str_contains(strtolower($product->name), 'fresco');
                if ($isFresh) {
                    return [
                        'type' => 'suggestion',
                        'message' => "Veo que te interesa *{$product->name}* (Fresco), pero en **{$context['city']}** solo podemos enviarte productos secos/deshidratados. ¿Te muestro las opciones disponibles?"
                    ];
                }
            }
            // Store for context
            $isFresh = ($product->category && str_contains(strtolower($product->category->name), 'fresco')) || str_contains(strtolower($product->name), 'fresco');
            $context['last_offered_product_type'] = $isFresh ? 'fresh' : 'dry';
            session(['ai_context' => $context]);

            return $this->handleProductQuery($product, $context);
        }

        // 7. General Shipping/Availability fallback check
        if ($this->isShippingQuery($content) || $this->isAvailabilityQuery($content)) {
            // Already handled above if strict match, but this block was in previous code
            return $this->handleShippingQuery($content, $context);
        }

        // 8. LLM Communication (Stub)
        return $this->callLlm($content);
    }

    protected function isAffirmation(string $content): bool
    {
        // Normalize: remove punctuation, lowercase
        $normalized = Str::lower(preg_replace('/[^\w\s]/u', '', $content));
        $normalized = trim($normalized);

        $affirmatives = ['si', 'sí', 'dale', 'acepto', 'bueno', 'ok', 'está bien', 'claro', 'de una', 'perfecto', 'listo', 'hágale'];

        foreach ($affirmatives as $word) {
            // Exact match "si" OR starts with "si " (e.g. "si gracias")
            if ($normalized === $word || str_starts_with($normalized, $word . ' ')) {
                return true;
            }
        }

        // Also check if content implies confirmation like "los quiero"
        if (str_contains($normalized, 'los quiero') || str_contains($normalized, 'quiero comprar')) {
            return true;
        }

        return false;
    }

    protected function isAvailabilityQuery(string $content): bool
    {
        $content = strtolower($content);

        // 1. Direct Phrases
        $phrases = ['disponibles', 'que tienes', 'tienen', 'stock', 'variedades', 'cuales hongos', 'que venden', 'catalogo', 'catálogo', 'precio', 'lista'];
        foreach ($phrases as $phrase) {
            if (str_contains($content, $phrase))
                return true;
        }

        // 2. Combination Logic (e.g. "que" + "hongos")
        if (str_contains($content, 'que') && str_contains($content, 'hongos')) {
            return true;
        }
        if (str_contains($content, 'cuales') && str_contains($content, 'hongos')) {
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
        $content = strtolower($content);
        return str_contains($content, 'envi') || // envio, enviar, enviame
            str_contains($content, 'domicilio') ||
            str_contains($content, 'costo') ||
            str_contains($content, 'pedir') ||
            str_contains($content, 'mand') || // mandar, mandame
            str_contains($content, 'llev');    // llevar, llevame
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
            // Try to infer city/locality from content using shared helper
            $inferredContext = $this->inferLocationFromContent($content);

            if ($inferredContext) {
                $city = $inferredContext['city'];
                $locality = $inferredContext['locality'] ?? null;
                $inferred = true;

                // Persist inferred location
                $context['city'] = $city;
                if ($locality)
                    $context['locality'] = $locality;
                session(['ai_context' => $context]);
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

        // Persist validated city/locality
        if ($targetCity) {
            $context['city'] = $targetCity;
            if ($matchedLocality)
                $context['locality'] = $matchedLocality;
            session(['ai_context' => $context]);
        }

        // 3. Strict Product Validation for Non-Bogota
        if (Str::slug($targetCity) !== 'bogota') {
            // Check if user is asking for a Fresh product or has one in context
            $product = $this->detectProduct($content); // Use robust detection

            $isFreshRequest = false;
            $pivotSourceProduct = null;

            // Check current message
            if ($product) {
                $pivotSourceProduct = $product;
                if (
                    ($product->category && str_contains(strtolower($product->category->name), 'fresco')) ||
                    str_contains(strtolower($product->name), 'fresco')
                ) {
                    $isFreshRequest = true;
                }
            }
            // Check stored context (Memory) via helper
            elseif ($lastProduct = $this->getLastProductConsulted()) {
                $pivotSourceProduct = $lastProduct;
                // Re-evaluate freshness from stored product
                if (
                    ($lastProduct->category && str_contains(strtolower($lastProduct->category->name), 'fresco')) ||
                    str_contains(strtolower($lastProduct->name), 'fresco')
                ) {
                    $isFreshRequest = true;
                }
            }

            // Auto-suggest logic if Fresh requested OR just as a general rule
            if ($isFreshRequest && $pivotSourceProduct) {
                // PIVOT LOGIC: Find dry products of the SAME STRAIN
                $alternatives = $this->findDryAlternatives($pivotSourceProduct);

                if ($alternatives->isNotEmpty()) {
                    $list = $alternatives->map(function ($p) {
                        return "• {$p->name} ($" . number_format($p->price, 0) . ")";
                    })->join("<br>");

                    $strainName = $pivotSourceProduct->strain->name ?? 'la misma cepa';

                    // STORE PENDING SUGGESTION
                    $context = session('ai_context', []);
                    $context['pending_suggestion_products'] = $alternatives->pluck('id')->toArray();
                    session(['ai_context' => $context]);

                    // Context update instruction (implicit in flow, but could be made explicit if next msg is yes)
                    // We inform usage of alternatives
                    return [
                        'type' => 'suggestion',
                        'message' => "Veo que estás en {$targetCity}. Por seguridad, no enviamos productos frescos allí, pero tengo disponibles estas opciones deshidratadas de **{$strainName}**:<br><br>{$list}<br><br>¿Te gustaría cambiar tu pedido a alguna de estas opciones?"
                    ];
                }
            }

            // Generic Fallback (If no pivots found OR generic fresh request)
            if ($isFreshRequest || !$product) {
                $dryProducts = $this->findDryProducts();

                if ($dryProducts->isEmpty()) {
                    // Strict "No Stock" Message
                    return [
                        'type' => 'answer',
                        'message' => "En el momento no tenemos stock disponible para hongos deshidratados, que son los únicos que podemos enviar a {$targetCity}. ¡Vuelve pronto!"
                    ];
                }

                $list = $dryProducts->map(function ($p) {
                    return "• {$p->name} ($" . number_format($p->price, 0) . ")";
                })->join("<br>");

                // STORE PENDING SUGGESTION
                $context = session('ai_context', []);
                $context['pending_suggestion_products'] = $dryProducts->pluck('id')->toArray();
                session(['ai_context' => $context]);

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

    protected function handleOrderConfirmation(array $context): array
    {
        $productIds = $context['pending_suggestion_products'] ?? [];

        if (empty($productIds)) {
            return [
                'type' => 'error',
                'message' => 'Lo siento, no tengo un pedido pendiente por confirmar. ¿Qué te gustaría comprar?'
            ];
        }

        // CLEANUP PENDING
        unset($context['pending_suggestion_products']);
        session(['ai_context' => $context]);

        // Generate Link (Simulation, assuming a route exists or just query params)
        // In a real app we might create an Order record here.
        // For now, we point to a generic checkout with products query param
        $idsParam = implode(',', $productIds);
        $link = url("/checkout?products={$idsParam}");

        return [
            'type' => 'system',
            'message' => "¡Perfecto! He añadido los productos a tu carrito. Puedes finalizar tu compra aquí:<br><br>👉 <a href='{$link}' target='_blank'>Ir a Pagar</a>"
        ];
    }

    protected function getLastProductConsulted(): ?Product
    {
        $context = session('ai_context', []);
        if (isset($context['last_product_id'])) {
            return Product::find($context['last_product_id']);
        }
        return null;
    }

    protected function findDryAlternatives(Product $sourceProduct)
    {
        return Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->where('id', '!=', $sourceProduct->id) // Exclude self
            ->where(function ($q) use ($sourceProduct) {
                // Same strain
                if ($sourceProduct->strain_id) {
                    $q->where('strain_id', $sourceProduct->strain_id);
                }
                // AND is dry
                $q->where(function ($q2) {
                    $q2->whereHas('category', function ($q3) {
                        $q3->where('name', 'like', '%deshidratado%')
                            ->orWhere('name', 'like', '%seco%');
                    })
                        ->orWhere('name', 'like', '%deshidratado%')
                        ->orWhere('name', 'like', '%seco%');
                });
            })
            ->limit(3)
            ->get();
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
            $product = Product::where('name', $bestMatchName)->first();
            // Store in session (Memory)
            if ($product) {
                $context = session('ai_context', []);
                $context['last_product_id'] = $product->id;
                session(['ai_context' => $context]);
            }
            return $product;
        }

        // Fallback: Word by word check (Legacy but useful)
        $words = explode(' ', $content);
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $match = $this->findBestMatch($word, $allProducts);
                if ($match) {
                    $product = Product::where('name', $match)->first();
                    // Store in session (Memory)
                    if ($product) {
                        $context = session('ai_context', []);
                        $context['last_product_id'] = $product->id;
                        session(['ai_context' => $context]);
                    }
                    return $product;
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
        // 1. Check for Bogota Localities first (Specific > General)
        $bogotaLocalities = ShippingZone::where('city', 'Bogotá')->whereNotNull('locality')->pluck('locality');

        // Use findBestMatch on words for localities too
        $words = explode(' ', str_replace([',', '.', '!', '?'], ' ', $content));

        foreach ($words as $word) {
            if (strlen($word) < 4)
                continue;

            $matchedLocality = $this->findBestMatch($word, $bogotaLocalities);
            if ($matchedLocality) {
                return ['city' => 'Bogotá', 'locality' => $matchedLocality];
            }
        }

        // 2. Check for Cities
        $dbCities = ShippingZone::select('city')->distinct()->pluck('city');

        foreach ($words as $word) {
            if (strlen($word) < 3)
                continue; // "Cali" is 4, but let's be safe with 3 for short names if any (e.g. Ica?) No, shortest is usually 4.

            // Avoid matching common words "para", "pero", "donde" that might fuzzy match
            $commonWords = ['para', 'pero', 'como', 'donde', 'envio', 'valor', 'costo', 'tienen', 'hongo', 'luego', 'puedo', 'quiero', 'dale', 'bien', 'bueno', 'gracias'];
            if (in_array(strtolower($word), $commonWords))
                continue;

            // Stricter checking for short words
            $options = $dbCities;
            $bestMatch = null;
            $shortestDistance = -1;

            foreach ($options as $option) {
                $dist = levenshtein(strtolower($word), strtolower(Str::ascii($option)));

                // If short word (<= 4 chars), allow max 1 mistake. Else 2.
                $maxDist = strlen($word) <= 4 ? 1 : 2;

                if ($dist <= $maxDist && ($shortestDistance === -1 || $dist < $shortestDistance)) {
                    $bestMatch = $option;
                    $shortestDistance = $dist;
                }
            }

            if ($bestMatch) {
                return ['city' => $bestMatch];
            }
        }

        // Fallback: Check multi-word cities (e.g. San Andres) using ascii stripos
        // This covers cases where the user types "San Andres" and fuzzy match on "San" or "Andres" alone might be ambiguous or weak
        foreach ($dbCities as $dbCity) {
            if (str_contains(Str::ascii(strtolower($content)), Str::ascii(strtolower($dbCity)))) {
                return ['city' => $dbCity];
            }
        }

        return null;
    }
}
