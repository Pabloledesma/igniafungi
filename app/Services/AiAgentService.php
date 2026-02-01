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

        // 0.1 Check if we are waiting for User Data (Lazy Registration)
        if (session('ai_waiting_for_user_data')) {
            $user = $this->createOrUpdateLead($content, $context);
            if ($user) {
                auth()->login($user);
                session()->forget('ai_waiting_for_user_data');

                // RELOAD CONTEXT to ensure it contains everything (including city from DB/current request if updated)
                $context = session('ai_context', []);

                // Proceed to generate order now that we are authenticated
                $response = $this->handleOrderConfirmation($context);

                // Enhance message with registration info
                $response['message'] = "✅ **¡Te hemos registrado exitosamente!**<br>Hemos enviado los detalles de tu cuenta a tu correo ({$user->email}).<br><br>" . $response['message'];

                return $response;
            } else {
                return [
                    'type' => 'question',
                    'message' => 'No pude identificar tu correo electrónico. Por favor escríbelo para poder enviarte el resumen de la orden.'
                ];
            }
        }

        // 0.2 Context Inference: Check if message is just a City or Locality
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
            // Priority Check: If we are in a 'fresh' context but city is restricted, BLOCK affirmation.
            // This prevents "Ok" from generating an order if we just told them "No enviamos frescos".
            if (isset($context['city']) && isset($context['last_offered_product_type'])) {
                if ($context['last_offered_product_type'] === 'fresh' && Str::slug($context['city']) !== 'bogota') {
                    // Pivot Suggestion again
                    return [
                        'type' => 'suggestion',
                        'message' => "Entiendo que quieres continuar, pero recuerda que **en {$context['city']} solo podemos entregar productos secos**. Por favor selecciona una de las opciones sugeridas."
                    ];
                }
            }

            // Check for Pending Suggestions OR Confirmed Products (Order Confirmation)
            // Fix: Allow "OK" to trigger order if we have items in the mental cart
            if (isset($context['pending_suggestion_products']) || !empty($context['confirmed_products'])) {
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

        // Fallback: If no product detected but query is informational, assume context
        if (!$product && $this->isInformationalQuery($content)) {
            $product = $this->getLastProductConsulted();
        }

        if ($product) {
            // RELOAD CONTEXT because detectProduct modified it
            $context = session('ai_context', []);

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

            // 6.1 Check for Informational Intent
            if ($this->isInformationalQuery($content)) {
                $description = $product->description ?? $product->short_description ?? "Es un excelente producto de Ignia Fungi.";

                // Extract keywords to see if specific info is requested
                // Add product name parts to stop words
                $nameParts = explode(' ', strtolower($product->name));
                $stopWords = array_merge($this->stopWords, $nameParts);

                $words = explode(' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $content)));
                $keywords = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $stopWords));

                // If generic query (no specific keywords), return description
                if (empty($keywords)) {
                    return [
                        'type' => 'answer',
                        'message' => "ℹ️ **Sobre {$product->name}**:<br>{$description}"
                    ];
                }

                // Check if description covers keys
                $covered = false;
                foreach ($keywords as $kw) {
                    if (str_contains(mb_strtolower($description), $kw)) {
                        $covered = true;
                        break;
                    }
                }

                if ($covered) {
                    return [
                        'type' => 'answer',
                        'message' => "ℹ️ **Sobre {$product->name}**:<br>{$description}"
                    ];
                }

                // Expand keywords for Usage/Preparation context
                $usageTerms = ['usar', 'uso', 'consumir', 'consumo', 'preparar', 'prepara', 'cocina', 'receta', 'dosis', 'tomar', 'comer'];
                $isUsageQuery = false;
                foreach ($keywords as $kw) {
                    foreach ($usageTerms as $term) {
                        if (str_contains($kw, $term)) {
                            $isUsageQuery = true;
                            break 2;
                        }
                    }
                }

                $searchKeywords = $isUsageQuery ? array_unique(array_merge($keywords, $usageTerms)) : $keywords;

                // Search Posts
                $posts = \App\Models\Post::where('product_id', $product->id)
                    ->where('is_published', true)
                    ->where(function ($q) use ($searchKeywords) {
                        foreach ($searchKeywords as $kw) {
                            $q->orWhere('content', 'like', "%{$kw}%")
                                ->orWhere('title', 'like', "%{$kw}%")
                                ->orWhere('summary', 'like', "%{$kw}%");
                        }
                    })->limit(3)->get();

                if ($posts->isNotEmpty()) {
                    $extraInfo = "";
                    foreach ($posts as $post) {
                        $extraInfo .= "<br>🔹 **{$post->title}:** {$post->summary}";
                    }
                    return [
                        'type' => 'answer',
                        'message' => "ℹ️ **Sobre {$product->name}**:<br>{$description}<br><br>💡 **Información Adicional (Blog):**{$extraInfo}"
                    ];
                }

                // Handoff if info not found
                return $this->callLlm("Consulta sobre {$product->name}: {$content} (Info no encontrada en descripción ni posts)");
            }

            return $this->handleProductQuery($product, $context);
        }

        // 6.5 Check strictly for informational queries about a product (even if detectProduct handled it, we intercepted it)
        // Actually, detectProduct calls session update and return matched product.
        // We can check intent here.

        // Refactor: Logic 6 checks detectProduct.
        // Inside logic 6, we should branch: Is it a Sales Intent ("Lo quiero") or Info Intent ("Que es?")?

        // Let's modify block 6 in a larger chunk


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
            'proceder',
            'confirmar',
            'comprar'
        ];

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

    protected array $stopWords = [
        'que',
        'es',
        'el',
        'la',
        'los',
        'las',
        'un',
        'una',
        'de',
        'del',
        'para',
        'sirve',
        'cuentame',
        'sobre',
        'hongo',
        'producto',
        'informacion',
        'detalle',
        'beneficios',
        'propiedades',
        'no',
        'se',
        'sabes',
        'tienes',
        'me',
        'puedes',
        'decir',
        'como',
        'cuando',
        'donde',
        'interesa',
        'quiero',
        'quisiera',
        'deseo',
        'gustaria',
        'precio',
        'costo',
        'valor',
        'cuanto',
        'vale',
        'comprar'
    ];

    protected function isInformationalQuery(string $content): bool
    {
        // ... (Keep existing keywords logic)
        $content = strtolower($content);
        $keywords = [
            'que es',
            'qué es',
            'para que sirve',
            'para qué sirve',
            'informacion',
            'información',
            'cuentame',
            'cuéntame',
            'sobre',
            'detalle',
            'beneficios',
            'propiedades',
            'no se',
            'no sé',
            'desconozco',
            'sirve para',
            'usar',
            'consumir',
            'preparar',
            'cocina',
            'receta',
            'uso',
            'dosis',
            'como se usa',
            'prepara',
            'como se prepara',
            'como preparar'
        ];

        foreach ($keywords as $kw) {
            if (str_contains($content, $kw))
                return true;
        }
        return false;
    }

    // ... (rest of methods)

    protected function detectProduct(string $content): ?Product
    {
        // ... (Keep existing numeric and exact/fuzzy/substring checks)
        // 0. Check for Numeric Selection
        if (preg_match('/^(\d+)$/', trim($content), $matches) || preg_match('/opcion (\d+)/i', $content, $matches) || preg_match('/el (\d+)/i', $content, $matches)) {
            $index = (int) end($matches);
            $context = session('ai_context', []);
            $pendingIds = $context['pending_suggestion_products'] ?? [];
            $arrayIndex = $index - 1;

            if (isset($pendingIds[$arrayIndex])) {
                $product = Product::find($pendingIds[$arrayIndex]);
                if ($product) {
                    $this->updateProductContext($product);
                    return $product;
                }
            }
        }

        // 1. Exact/Approximate & Substring
        $allProducts = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->pluck('name', 'id');

        $bestMatchName = $this->findBestMatch($content, $allProducts);
        if ($bestMatchName) {
            $product = Product::where('name', $bestMatchName)->first();
            if ($product) {
                $this->updateProductContext($product);
                return $product;
            }
        }

        foreach ($allProducts as $id => $pName) {
            if (stripos($content, $pName) !== false) {
                $product = Product::find($id);
                if ($product) {
                    $this->updateProductContext($product);
                    return $product;
                }
            }
        }

        // 3. KEYWORD PARTIAL MATCH (New)
        $words = explode(' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $content)));
        $potentialKeywords = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $this->stopWords));

        foreach ($potentialKeywords as $word) {
            // Find product with name containing this word
            $product = Product::where('is_active', true)
                ->where('stock', '>', 0)
                ->where('name', 'like', "%{$word}%")
                ->first();

            if ($product) {
                $this->updateProductContext($product);
                return $product;
            }
        }

        return null;
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

        // Store IDs for number matching
        $ids = [];
        $list = "";
        $index = 1;

        foreach ($products as $p) {
            $list .= "{$index}. *{$p->name}*: $" . number_format($p->price, 0) . "<br>";
            $ids[] = $p->id;
            $index++;
        }

        // Context Update for Numeric Selection
        $context = session('ai_context', []);
        $context['pending_suggestion_products'] = $ids;
        // Note: For numeric selection, we can map number N to index N-1 in this array.
        session(['ai_context' => $context]);

        return [
            'type' => 'catalog',
            'message' => "¡Claro! Estos son los hongos que tenemos disponibles para ti hoy:<br><br>" . $list,
            'payload' => $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price])->toArray()
        ];
    }

    protected function isSpam(string $content): bool
    {
        // Allow numeric selection (1-9) even if short
        if (preg_match('/^[1-9]$/', trim($content))) {
            return false;
        }

        if (strlen($content) < 2)
            return true; // Too short, unless it's a number

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

        // Simple regex to catch "Soy [Name]"
        if (preg_match('/soy\s+([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)(?:,|;|\s+mi)/iu', $content, $nameMatch)) {
            $name = trim($nameMatch[1]);
        } elseif (preg_match('/nombre es\s+([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)/iu', $content, $nameMatch)) {
            $name = trim($nameMatch[1]);
        } else {
            // ... (Fallback regex logic) because User can just say "Name, email"
            $parts = preg_split('/(y\s+)?(mi|el)\s+correo/iu', $content);
            if (!empty($parts[0]) && strlen(trim($parts[0])) > 2 && strlen(trim($parts[0])) < 50) {
                $nameCandidate = trim($parts[0]);
                $nameCandidate = preg_replace('/\s+es$/iu', '', $nameCandidate);
                if (!preg_match('/[0-9]/', $nameCandidate)) {
                    $name = $nameCandidate;
                }
            }
        }

        // Try to extract CITY from "vivo en [City]"
        if (preg_match('/vivo en\s+([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)/iu', $content, $cityMatch)) {
            $extractedCity = trim($cityMatch[1]);
            // Validate against known cities to be sure? Or just trust.
            // Let's Clean it (remove puntuation at end)
            $extractedCity = preg_replace('/[.,;]$/', '', $extractedCity);

            // Update Context
            $context['city'] = $extractedCity;
            session(['ai_context' => $context]);
        }

        // Fix: Use correct array keys from context
        $city = $context['city'] ?? null;
        $locality = $context['locality'] ?? null;

        // PERSISTENCE: Ensure we update session explicitly if we found new data
        if ($city) {
            $shippingInfo = $this->getShippingInfo($city, $locality);
            $cost = $shippingInfo['price'] ?? 0;

            session()->put('checkout_shipping', [
                'is_bogota' => (Str::slug($city) === 'bogota'),
                'city' => $city,
                'location' => $locality,
                'cost' => $cost,
                'delivery_date' => null
            ]);
        }

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(16)), // Temp password
                'city' => $city,
                'locality' => $locality
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

                // Fix: Fetch existing context to avoid overwriting last_product_id
                $context = session('ai_context', []);
                $context['city'] = $city;
                if ($locality)
                    $context['locality'] = $locality;
                session(['ai_context' => $context]);

                // Also update the local reference $context passed to this method so downstream logic checks validation
                // This step is crucial because createOrUpdateLead uses $context variable passed as arg
                // NOTE: We cannot easily update the $context argument passed by value effectively for the caller unless we return it, 
                // but processMessage reloads session before critical steps if needed, OR we should rely on session.
                // However, createOrUpdateLead uses the $context ARRAY passed to it.
                // So we should ideally return this context or ensure createOrUpdateLead reads from session?
                // The easier path: createOrUpdateLead is called in processMessage.
                // processMessage calls inferLocationFromContent (via shippingQuery or directly).

                // If we are inside handleShippingQuery, $context is local. We must update it.
                // The replacement content below is what was already there in previous view? 
                // Let's verify what I am replacing.
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
            // Fix: Reload context to ensure we don't overwrite products added by inferLocation or parallel logic
            $freshContext = session('ai_context', []);
            $freshContext['city'] = $targetCity;
            if ($matchedLocality)
                $freshContext['locality'] = $matchedLocality;

            session(['ai_context' => $freshContext]);

            // Update local context for the rest of this function
            $context = $freshContext;
        }

        // 3.0 Critical Geographic Interceptor
        // Validate if ANY product (Last or Confirmed) is fresh and we are outside Bogota.
        $interceptProduct = null;
        $checkQueue = [];

        $last = $this->detectProduct($content) ?? $this->getLastProductConsulted();
        if ($last)
            $checkQueue[] = $last;

        $confirmedIds = $context['confirmed_products'] ?? [];
        foreach ($confirmedIds as $pid) {
            $p = Product::find($pid);
            if ($p)
                $checkQueue[] = $p;
        }

        if ($targetCity && Str::slug($targetCity) !== 'bogota') {
            foreach ($checkQueue as $p) {
                $isFresh = ($p->category && str_contains(strtolower($p->category->name), 'fresc')) ||
                    str_contains(strtolower($p->name), 'fresc');

                if ($isFresh) {
                    $interceptProduct = $p; // Found a problematic one
                    break;
                }
            }
        }

        if ($interceptProduct) {
            // INTERCEPTED!

            // Update context to avoid future fresh suggestions
            $context['last_offered_product_type'] = 'dry';
            session(['ai_context' => $context]);

            // Find alternatives
            $alternatives = $this->findDryAlternatives($interceptProduct);

            // If no specific alternatives, find generic dry
            if ($alternatives->isEmpty()) {
                $alternatives = $this->findDryProducts();
            }

            $list = "";
            $index = 1;
            $ids = [];
            foreach ($alternatives as $p) {
                $list .= "{$index}. {$p->name} ($" . number_format($p->price, 0) . ")<br>";
                $ids[] = $p->id;
                $index++;
            }

            // Store suggested dry products
            $context = session('ai_context', []);
            $context['pending_suggestion_products'] = $ids;
            session(['ai_context' => $context]);

            // RETURN SUGGESTION ONLY - NO LINK GENERATION
            return [
                'type' => 'suggestion',
                'message' => "Veo que estás en {$targetCity}. Por la delicadeza del producto (**{$interceptProduct->name}**), no enviamos frescos allí, pero tengo estas opciones secas:<br><br>{$list}<br><br>¿Cambiamos tu pedido por uno de estos?",
                'payload' => $alternatives->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray()
            ];
        }


        // 3. Product Detection & Order Intent
        // Check if user is referencing a product (Fresh/Dry)
        $product = $this->detectProduct($content);
        $isFreshRequest = false;

        if ($product) {
            if (
                ($product->category && str_contains(strtolower($product->category->name), 'fresco')) ||
                str_contains(strtolower($product->name), 'fresco')
            ) {
                $isFreshRequest = true;
            }
        } elseif ($lastProduct = $this->getLastProductConsulted()) {
            // Fallback to memory
            $product = $lastProduct; // Weak binding, might need confirmation if msg didn't mention it
            if (
                ($lastProduct->category && str_contains(strtolower($lastProduct->category->name), 'fresco')) ||
                str_contains(strtolower($lastProduct->name), 'fresco')
            ) {
                $isFreshRequest = true;
            }
        }

        // 3.1 Strict Product Validation for Non-Bogota
        if (Str::slug($targetCity) !== 'bogota') {
            // ... (Existing Pivot Logic) ...
            $pivotSourceProduct = $product;

            // Auto-suggest logic if Fresh requested OR just as a general rule
            if ($isFreshRequest && $pivotSourceProduct) {
                // PIVOT LOGIC: Find dry products of the SAME STRAIN
                $alternatives = $this->findDryAlternatives($pivotSourceProduct);

                if ($alternatives->isNotEmpty()) {
                    $list = "";
                    $index = 1;
                    $ids = [];
                    foreach ($alternatives as $p) {
                        $list .= "{$index}. {$p->name} ($" . number_format($p->price, 0) . ")<br>";
                        $ids[] = $p->id;
                        $index++;
                    }

                    $strainName = $pivotSourceProduct->strain->name ?? 'la misma cepa';

                    // STORE PENDING SUGGESTION
                    $context = session('ai_context', []);
                    $context['pending_suggestion_products'] = $ids;
                    session(['ai_context' => $context]);

                    return [
                        'type' => 'suggestion',
                        'message' => "Veo que estás en {$targetCity}. Por seguridad, no enviamos productos frescos allí, pero tengo disponibles estas opciones deshidratadas de **{$strainName}**:<br><br>{$list}<br><br>¿Te gustaría cambiar tu pedido a alguna de estas opciones?",
                        'payload' => $alternatives->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray()
                    ];
                }
            }

            // Generic Fallback (If no pivots found OR generic fresh request)
            if ($isFreshRequest || (!$product && $isFreshRequest)) {
                // ... (Existing Fallback Logic) ...
                // Re-implement simplified or keep existing structure
                $dryProducts = $this->findDryProducts();
                // ...
                // Strict "No Stock" Message
                if ($dryProducts->isEmpty()) {
                    return [
                        'type' => 'answer',
                        'message' => "En el momento no tenemos stock disponible para hongos deshidratados, que son los únicos que podemos enviar a {$targetCity}. ¡Vuelve pronto!"
                    ];
                }
                // Store Pending
                $list = "";
                $index = 1;
                $ids = [];
                foreach ($dryProducts as $p) {
                    $list .= "{$index}. {$p->name} ($" . number_format($p->price, 0) . ")<br>";
                    $ids[] = $p->id;
                    $index++;
                }

                $context = session('ai_context', []);
                $context['pending_suggestion_products'] = $ids;
                session(['ai_context' => $context]);

                return [
                    'type' => 'suggestion',
                    'message' => "El costo de envío a {$targetCity} es de ${price} COP.<br><br>⚠️ <strong>Importante:</strong> En {$targetCity} no podemos entregar productos frescos (solo en Bogotá), pero tenemos disponibles estos productos secos para ti:<br>{$list}",
                    'payload' => $dryProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()->toArray()
                ];
            }
        }

        // 3.2 Bogotá Logic (or Valid Location for Fresh)
        // If product is detected AND intent is high ("Enviame", "Quiero"), previously we converted to Order directly.
        // User Feedback: Show Price + CTA "Add more" first.
        if ($product) {
            // Ensure product is in context for next step
            $context = session('ai_context', []);
            $context['last_product_id'] = $product->id;

            // Add to confirmed products if not already
            $confirmed = $context['confirmed_products'] ?? [];
            if (!in_array($product->id, $confirmed)) {
                $confirmed[] = $product->id;
            }
            $context['confirmed_products'] = $confirmed;

            session(['ai_context' => $context]);

            // DO NOT jump to handleOrderConfirmation yet. Fall through to price quote.
        }

        // Bogota or Acceptable Product (Just Shipping Info)
        $locSuffix = $matchedLocality ? ", localidad {$matchedLocality}" : "";
        $message = "El costo de envío a {$targetCity}{$locSuffix} es de ${price} COP.";

        // If we have products in context (accumulated or just detected), list them
        $confirmedIds = $context['confirmed_products'] ?? [];
        if (!empty($confirmedIds)) {
            $productNames = Product::whereIn('id', $confirmedIds)->pluck('name')->toArray();
            $list = implode(', ', $productNames);
            $message .= "<br><br>Tienes en tu lista: <strong>{$list}</strong>.<br>¿Deseas agregar algo más o generamos la orden?";
        } elseif ($this->getLastProductConsulted()) {
            // Fallback for single product flow if array wasn't populated
            $message .= "<br><br>¿Deseas agregar algún otro producto al pedido o generamos la orden?";
        }

        return [
            'type' => 'order_closure',
            'message' => $message,
            'actions' => [
                ['label' => '➕ Agregar más', 'type' => 'more_products'],
                ['label' => '🛒 Generar orden', 'type' => 'checkout']
            ]
        ];
    }

    protected function handleOrderConfirmation(array $context): array
    {
        // USE CONFIRMED PRODUCTS (Accumulated via detectProduct)
        $productIds = $context['confirmed_products'] ?? [];

        // Fallback: If no confirmed list but we have a last product (e.g. direct flow)
        if (empty($productIds) && isset($context['last_product_id'])) {
            $productIds = [$context['last_product_id']];
        }

        if (empty($productIds) && isset($context['pending_suggestion_products'])) {
            // Second fallback: Pending suggestions (only if explicit direct accept happened?)
            // Actually, this was the source of the bug. We should probably avoid this unless size is 1.
            // Let's assume detectProduct handled it. If empty here, we have a problem.
            if (count($context['pending_suggestion_products']) === 1) {
                $productIds = $context['pending_suggestion_products'];
            }
        }

        if (empty($productIds)) {
            return [
                'type' => 'error',
                'message' => 'Lo siento, no tengo claro qué productos deseas incluir en tu orden. ¿Podrías decirme el nombre o número del producto?'
            ];
        }

        // 1. LAZY REGISTRATION CHECK
        if (!auth()->check()) {
            session(['ai_waiting_for_user_data' => true]);
            return [
                'type' => 'question',
                'message' => '¡Excelente! Tengo todo listo para tu pedido. Para registrarte y proceder al pago, por favor envíame tus datos en este formato: **Soy [Nombre completo], mi correo es [email@ejemplo.com] y vivo en [Ciudad]**.'
            ];
        }

        // PRE-FILL SHIPPING DATA FOR CHECKOUT FIRST TO KNOW CONTEXT
        $city = $context['city'] ?? 'Bogotá';
        $location = $context['locality'] ?? null;
        $isBogota = (Str::slug($city) === 'bogota');

        // FILTER PRODUCTS BASED ON CITY
        $productsAdded = [];
        $ignoredFresh = false;

        foreach ($productIds as $id) {
            $p = Product::find($id);
            if (!$p)
                continue;

            $isFresh = ($p->category && str_contains(strtolower($p->category->name), 'fresco')) ||
                str_contains(strtolower($p->name), 'fresco');

            if (!$isBogota && $isFresh) {
                $ignoredFresh = true;
                continue; // Skip adding this product
            }

            \App\Helpers\CartManagement::addItemsToCart($id, 1);
            $productsAdded[] = $p->name;
        }

        // Calculate estimated cost using Helper
        $shippingInfo = $this->getShippingInfo($city, $location);
        $cost = $shippingInfo['price'] ?? 0;

        session()->put('checkout_shipping', [
            'is_bogota' => $isBogota,
            'city' => $city,
            'location' => $location,
            'cost' => $cost,
            'delivery_date' => null // Let user select
        ]);

        // CLEANUP
        unset($context['pending_suggestion_products']);
        unset($context['confirmed_products']); // Clear basket after order
        unset($context['last_product_id']); // Prevent stale product context
        session(['ai_context' => $context]);

        // Generate Link
        $link = url("/cart"); // Direct to cart

        // Construct Message
        if ($ignoredFresh) {
            if (empty($productsAdded)) {
                return [
                    'type' => 'suggestion',
                    'message' => "❌ **No pude agregar los productos.**\n\nLos hongos frescos (**" . implode(', ', $productsAdded) . "**) no están disponibles para envío a **{$city}** (solo Bogotá). ¿Te gustaría ver las versiones deshidratadas?",
                    'payload' => $this->findDryProducts()->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->toArray()
                ];
            } else {
                $msg = "✅ **He añadido los productos secos a tu carrito.**\n\n⚠️ **Nota:** Los hongos frescos no fueron agregados porque solo están disponibles para Bogotá.\n\nPuedes finalizar tu compra aquí:\n\n👉 <a href='{$link}' target='_blank'>Ir a Pagar</a>";
                return [
                    'type' => 'system',
                    'message' => $msg,
                    'actions' => [
                        ['label' => '💳 Ir a Pagar', 'type' => 'checkout']
                    ]
                ];
            }
        }

        return [
            'type' => 'system',
            'message' => "¡Perfecto! He añadido los productos a tu carrito. Puedes finalizar tu compra aquí:\n\n👉 <a href='{$link}' target='_blank'>Ir a Pagar</a>",
            'actions' => [
                ['label' => '💳 Ir a Pagar', 'type' => 'checkout']
            ]
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
                        $q3->where('name', 'like', '%deshidratad%')
                            ->orWhere('name', 'like', '%sec%');
                    })
                        ->orWhere('name', 'like', '%deshidratad%')
                        ->orWhere('name', 'like', '%sec%');
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
                    $q2->where('name', 'like', '%deshidratad%')
                        ->orWhere('name', 'like', '%sec%');
                })
                    ->orWhere('name', 'like', '%deshidratad%')
                    ->orWhere('name', 'like', '%sec%');
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

    protected function updateProductContext(Product $product): void
    {
        $context = session('ai_context', []);
        $context['last_product_id'] = $product->id;

        $confirmed = $context['confirmed_products'] ?? [];
        if (!in_array($product->id, $confirmed)) {
            $confirmed[] = $product->id;
        }
        $context['confirmed_products'] = $confirmed;

        session(['ai_context' => $context]);
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
            if ($city && strtolower($city) !== 'bogotá' && strtolower($city) !== 'bogota') {
                // Suggest Dry
                return [
                    'type' => 'suggestion',
                    'message' => "El producto '{$product->name}' es fresco y solo se entrega en Bogotá. Para tu ciudad (Here: $city) te recomendamos nuestros hongos deshidratados." // Logic usually handled in ShippingQuery, but good safeguard.
                ];
            }
        }

        // IMPROVEMENT: Drive the sale forward.
        // If we don't have location, ask for it using the standard prompt.
        if (empty($city)) {
            return [
                'type' => 'question',
                'message' => "¡Excelente elección! Has seleccionado **{$product->name}**. <br><br>Para calcular el costo del envío y generar tu orden, necesito saber: ¿En qué ciudad te encuentras?"
            ];
        }

        // If we DO have location, we might want to trigger shipping calc immediately.
        // But since this method returns an array, we can return a message that calls the shipping tool effectively? 
        // Or simply ask for confirmation if we know the price?

        // Simpler: Just acknowledge and ask for locality if Bogota, or confirm if National.
        if (Str::slug($city) === 'bogota') {
            return [
                'type' => 'question',
                'message' => "¡Perfecto, **{$product->name}**! Como estás en Bogotá, ¿me podrías confirmar tu localidad (ej. Usaquén, Chapinero) para darte el valor exacto del domicilio?"
            ];
        }

        // National with City known
        return [
            'type' => 'question',
            'message' => "¡Listo! Para enviar **{$product->name}** a {$city}, ¿deseas que calcule el costo del envío ya mismo?"
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

            // Common words to ignore
            $commonWords = ['para', 'pero', 'como', 'donde', 'envio', 'valor', 'costo', 'tienen', 'hongo', 'luego', 'puedo', 'quiero', 'dale', 'bien', 'bueno', 'gracias', 'dame'];
            if (in_array(strtolower($word), $commonWords))
                continue;

            // Stricter checking for short words
            $options = $bogotaLocalities;
            $bestMatch = null;
            $shortestDistance = -1;

            foreach ($options as $option) {
                $dist = levenshtein(strtolower($word), strtolower(Str::ascii($option)));

                // Localities are often short (Usme, Suba, Bosa). 
                // Allow max 1 mistake for short words (<=4), 2 for longer.
                $maxDist = strlen($word) <= 4 ? 1 : 2;

                if ($dist <= $maxDist && ($shortestDistance === -1 || $dist < $shortestDistance)) {
                    $bestMatch = $option;
                    $shortestDistance = $dist;
                }
            }

            if ($bestMatch) {
                return ['city' => 'Bogotá', 'locality' => $bestMatch];
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
