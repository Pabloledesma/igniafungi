<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    /**
     * Valida y procesa un mensaje del usuario.
     */
    public function processMessage(string $content, string $ip, ?array $context = []): array
    {
        // 0. Deduplication Guard (Prevent Double Clicks / Livewire Retries)
        // If exact same content comes within 2 seconds, return cached response.
        $msgHash = md5($content . $ip); // Include IP for extra safety? content is mostly enough for user.
        $lastHash = session('ai_last_msg_hash');
        $lastTime = session('ai_last_msg_timestamp');

        if ($lastHash === $msgHash && $lastTime && (time() - $lastTime < 2)) {
            $cached = session('ai_last_response');
            if ($cached) {
                return $cached;
            }
        }

        // 1. Process Logic
        $response = $this->generateResponse($content, $ip, $context);

        // 2. Cache Result
        session([
            'ai_last_msg_hash' => $msgHash,
            'ai_last_msg_timestamp' => time(),
            'ai_last_response' => $response
        ]);

        return $response;
    }

    protected function generateResponse(string $content, string $ip, ?array $context = []): array
    {
        Log::info("ProcessMessage Start: '{$content}'");

        // MERGE SESSION CONTEXT PRIORITY
        // We ensure we have the full session history + any new explicit data passed (like IDs)
        $sessionContext = session('ai_context', []);
        $context = array_merge($sessionContext, $context);

        // 1. Explicit ID Handling (Checkbox Batch) from UI
        if (!empty($context['explicit_product_ids'])) {
            Log::info("Explicit IDs: " . implode(',', $context['explicit_product_ids']));
            foreach ($context['explicit_product_ids'] as $pid) {
                $p = Product::find($pid);
                if ($p)
                    $this->updateProductContext($p);
            }
            // Return simplified summary immediately
            return $this->handleShippingQuery($content, $context);
        }

        // 2. GLOBAL INTERCEPTOR: Handoff Request (High Priority)
        // If user explicitly asks for human/agent, we stop everything and trigger handoff.
        if ($this->isHandoffRequest($content)) {
            // Context is already merged above
            // Ensure session matches for callLlm to pick it up (it reads from session)
            session(['ai_context' => $context]);

            Log::info("Returning Handoff");
            return $this->callLlm($content);
        }

        // 0. Load Context from Session (Persistence)
        $sessionContext = session('ai_context', []);
        $context = array_merge($sessionContext, $context);

        // 0.1 PRE-EMPTIVE INFORMATIONAL CHECK (Priority over Registration/City)
        // Check if user is asking a question about a product ("Como se cocina?", "Que es?")
        $infoProduct = $this->detectProduct($content);
        if (!$infoProduct && !empty($context['last_product_id'])) {
            $infoProduct = Product::find($context['last_product_id']);
        }

        if ($infoProduct && $this->isInformationalQuery($content)) {
            // RELOAD CONTEXT because detectProduct might have modified it
            $context = session('ai_context', []);

            // 6. Check for Informational Questions (Description, Usage, etc.)
            $normalizedContent = Str::lower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $content));
            $descriptionTerms = ['que es', 'qué es', 'para que sirve', 'para qué sirve', 'sirve para', 'descripción', 'descripcion', 'beneficios', 'propiedades', 'informacion', 'información', 'detalle', 'cuentame'];
            $usageTerms = ['como se usa', 'cómo se usa', 'como consumir', 'dosis', 'preparación', 'preparacion', 'receta', 'cocinar', 'comer', 'prepara', 'usarla', 'cocina', 'usar', 'preparar', 'uso'];
            $keywords = array_merge($descriptionTerms, $usageTerms);

            $isInfoQuery = false;
            $isUsageQuery = false;
            foreach ($keywords as $kw) {
                if (str_contains($normalizedContent, $kw)) {
                    $isInfoQuery = true;
                    if (in_array($kw, $usageTerms)) {
                        $isUsageQuery = true;
                    }
                    break;
                }
            }

            if ($isInfoQuery) {
                $searchKeywords = $isUsageQuery ? array_unique(array_merge($keywords, $usageTerms)) : $keywords;

                // Search Posts
                $posts = \App\Models\Post::where('product_id', $infoProduct->id)
                    ->where('is_published', true)
                    ->where(function ($q) use ($searchKeywords) {
                        if (!empty($searchKeywords)) {
                            foreach ($searchKeywords as $kw) {
                                $q->orWhere('content', 'like', "%{$kw}%")
                                    ->orWhere('title', 'like', "%{$kw}%")
                                    ->orWhere('summary', 'like', "%{$kw}%");
                            }
                        }
                    })->limit(3)->get();

                $extraInfo = "";
                if ($posts->isNotEmpty()) {
                    foreach ($posts as $post) {
                        $extraInfo .= "<br>🔹 **{$post->title}:** {$post->summary}";
                    }
                }

                $description = $infoProduct->description ?? "Un hongo de excelente calidad.";

                Log::info("Returning Info Query (0.1)");
                return [
                    'type' => 'answer',
                    'message' => "ℹ️ **Sobre {$infoProduct->name}**:<br>{$description}" . ($extraInfo ? "<br><br>💡 **Información Adicional (Blog):**{$extraInfo}" : "")
                ];
            }
        }

        // 0.2 Check if we are waiting for User Data (Lazy Registration)
        // BUT: If user asks a question about product (Informational), we should answer it first.
        if (session('ai_waiting_for_user_data') && !$this->isInformationalQuery($content)) {
            $result = $this->createOrUpdateLead($content, $context);

            if ($result instanceof User) {
                Auth::login($result);
                session()->forget('ai_waiting_for_user_data');
                session()->forget('ai_registration_data'); // Clear temp data

                // RELOAD CONTEXT to ensure it contains everything (including city from DB/current request if updated)
                $context = session('ai_context', []);
                $context['city'] = $result->city;
                $context['locality'] = $result->locality;
                session(['ai_context' => $context]);

                // FORCE UPDATE CHECKOUT SESSION
                $shippingInfo = $this->getShippingInfo($result->city, $result->locality);
                session()->put('checkout_shipping', [
                    'is_bogota' => (Str::slug($result->city) === 'bogota'),
                    'city' => $result->city,
                    'location' => $result->locality,
                    'cost' => $shippingInfo['price'] ?? 0,
                    'delivery_date' => null
                ]);

                // RESTRICTION CHECK (Fresh products outside Bogota)
                $hasFresh = false;
                $confirmed = $context['confirmed_products'] ?? [];
                // Robustness: Get IDs whether List or Map
                $ids = (is_array($confirmed) && !array_is_list($confirmed)) ? array_keys($confirmed) : $confirmed;

                $cartProducts = Product::whereIn('id', $ids)->get();
                foreach ($cartProducts as $p) {
                    if ($p->is_fresh) {
                        $hasFresh = true;
                        break;
                    }
                }

                if ($hasFresh && Str::slug($result->city) !== 'bogota') {
                    // Load Pivot Options (Dry products)
                    $alternatives = Product::where('is_active', true)
                        ->where('stock', '>', 0)
                        ->where('category_id', '!=', 1) // Assuming 1 is Fresh? Better: search by name/category
                        ->where(function ($q) {
                            $q->where('name', 'like', '%seco%')
                                ->orWhere('name', 'like', '%deshidratado%')
                                ->orWhereHas('category', fn($q) => $q->where('name', 'like', '%seco%'));
                        })
                        ->limit(3)
                        ->get();

                    return [
                        'type' => 'suggestion',
                        'message' => "He registrado tus datos para **{$result->city}**. ⚠️ Sin embargo, noté que seleccionaste hongos frescos. Por calidad y transporte, **solo enviamos frescos a Bogotá**.<br><br>Para envíos nacionales te recomiendo nuestros hongos deshidratados (misma calidad, mayor duración):",
                        'payload' => $alternatives->map(fn($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price,
                            'image' => asset($p->first_image)
                        ])->toArray()
                    ];
                }

                // Proceed to generate order now that we are authenticated
                $response = $this->handleOrderConfirmation($context);

                // Enhance message with registration info
                $response['message'] = "✅ **¡Te hemos registrado exitosamente!**<br>Hemos enviado los detalles de tu cuenta a tu correo ({$result->email}).<br><br>" . $response['message'];
                $response['should_reload'] = true; // Signal frontend to reload logic (CSRF/Session)

                Log::info("Returning Waiting for User Data (0.2)");
                return $response;
            } elseif (is_array($result) && isset($result['status']) && $result['status'] === 'partial') {
                // PARTIAL DATA: Determine what to ask next
                $missing = $result['missing'];
                $data = $result['data'];

                // Prioritize Name -> City -> Email logic or whatever flows best.
                // If Name is missing:
                if (in_array('name', $missing)) {
                    $msg = (isset($data['email']) ? "Gracias, ya tengo tu correo." : "") .
                        " Me falta un dato importante: **¿Cuál es tu nombre?**";
                    return ['type' => 'question', 'message' => $msg];
                }

                // If City is missing:
                if (in_array('city', $missing)) {
                    $msg = "Gracias " . ($data['name'] ?? '') . ". Para calcular el envío y generar la orden, necesito saber: **¿Desde qué ciudad nos escribes?**";
                    return ['type' => 'question', 'message' => $msg];
                }

                // If Email is missing:
                if (in_array('email', $missing)) {
                    $msg = "Gracias " . ($data['name'] ?? '') . ", ahora por favor indícame tu **correo electrónico** para enviarte el resumen de la orden.";
                    return ['type' => 'question', 'message' => $msg];
                }
            }

            // Fallback if structure mismatches (shouldnt happen)
            return [
                'type' => 'question',
                'message' => 'Por favor, indícame tu nombre, correo y ciudad para generar la orden.'
            ];
        }

        // 0.2 Context Inference: Check if message is just a City or Locality
        $locationContext = $this->inferLocationFromContent($content);
        if ($locationContext) {
            $context['city'] = $locationContext['city'];
            if (isset($locationContext['locality'])) {
                $context['locality'] = $locationContext['locality'];
            }
            // Update Session IMMEDIATELY
            session(['ai_context' => $context]);

            // ALSO update registration data to ensure persistence across turns
            $regData = session('ai_registration_data', []);
            $regData['city'] = $locationContext['city'];
            if (isset($locationContext['locality'])) {
                $regData['locality'] = $locationContext['locality'];
            }
            session(['ai_registration_data' => $regData]);

            // CONTINUITY LOGIC:
            // Proceed to Shipping/Availability regardless of Auth status (Deferred Registration logic)
            // If explicit shipping query OR implicit context flow (User providing city after product selection)
            $hasProducts = !empty($context['confirmed_products'])
                || !empty($context['last_product_id'])
                || !empty($context['pending_suggestion_products']);

            // Always handle shipping query if we have products and location now
            if ($hasProducts) {
                Log::info("Returning Location Context (0.2 Inference)");
                return $this->handleShippingQuery($content, $context);
            }
        }



        // 1. Rate Limiting (Keep existing)
        $key = 'ai_chat:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return [
                'type' => 'error',
                'message' => 'Has excedido el límite de mensajes. Por favor intenta de nuevo en un minuto.'
            ];
        }
        RateLimiter::hit($key, 60);

        // 1.1 Handle "Agregar más productos" Action explicitly
        if (str_contains(strtolower($content), 'agregar más') || str_contains(strtolower($content), 'agregar mas') || str_contains(strtolower($content), 'ver catalogo')) {
            $response = $this->handleAvailabilityQuery();
            $response['message'] = "¡Claro! ¿Qué otro hongo te gustaría agregar? Aquí tienes la lista de nuevo:<br><br>" . str_replace("¡Hola! Estos son los hongos que tenemos disponibles para ti hoy:<br><br>", "", $response['message']);
            return $response;
        }

        // 1.5 Strict Coherence Validation (Keep existing)
        if ($this->isAffirmation($content)) {
            // ... (Keep existing affirmation logic)
            if (isset($context['city']) && isset($context['last_offered_product_type'])) {
                if ($context['last_offered_product_type'] === 'fresh' && Str::slug($context['city']) !== 'bogota') {
                    return [
                        'type' => 'suggestion',
                        'message' => "Entiendo que quieres continuar, pero recuerda que **en {$context['city']} solo podemos entregar productos secos**. Por favor selecciona una de las opciones sugeridas."
                    ];
                }
            }
            if (isset($context['pending_suggestion_products']) || !empty($context['confirmed_products'])) {
                return $this->handleOrderConfirmation($context);
            }
        }

        // ... (Spam/Lead logic)

        // 6. Business Logic: Products/Freshness

        // 6.0 Handle Category Selection (e.g. "1")
        if (preg_match('/^(\d+)$/', trim($content), $matches)) {
            $index = (int) $matches[1];
            $pendingCats = $context['pending_suggestion_categories'] ?? [];
            if (isset($pendingCats[$index])) {
                $categoryData = $pendingCats[$index];
                $catIds = is_array($categoryData) ? $categoryData : [$categoryData];

                // Fetch products for one or multiple categories
                $products = Product::whereIn('category_id', $catIds)
                    ->where('is_active', true)
                    ->where('stock', '>', 0)
                    ->get();

                // Determine display name
                $catName = "Selección";
                if (count($catIds) === 1) {
                    $c = \App\Models\Category::find($catIds[0]);
                    if ($c)
                        $catName = $c->name;
                } else {
                    $catName = "Hongos Frescos (Gourmet y Medicina)";
                }

                if ($products->isNotEmpty()) {
                    $list = "";
                    $idx = 1;
                    $ids = [];
                    foreach ($products as $p) {
                        $list .= "{$idx}. *{$p->name}*: $" . number_format($p->price, 0) . "<br>";
                        $ids[] = $p->id;
                        $idx++;
                    }

                    // CONTEXT PRESERVATION (CRITICAL)
                    // Reload fresh session context to prevent overwriting city/locality
                    $currentContext = session('ai_context', []);
                    $currentContext['pending_suggestion_products'] = $ids;

                    // Remove pending categories to clean up? Or keep?
                    // Safe to keep, but unset for cleanliness if logic dictates.
                    // unset($currentContext['pending_suggestion_categories']); 

                    session(['ai_context' => $currentContext]);

                    return [
                        'type' => 'catalog',
                        'message' => "Has seleccionado **{$catName}**. Aquí están los productos disponibles:<br><br>" . $list . "<br>Marca los hongos que te interesan y presiona el botón para añadirlos a tu pedido.",
                        'payload' => $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price])->toArray()
                    ];
                }
            }
        }

        // 2. Conditional Product Detection
        // Only run detectProduct if we are NOT handling a location response and NOT making an affirmation
        // This prevents double counting when user says "Bogota" or "Si"
        $locationContext = $this->inferLocationFromContent($content);

        // PERSIST INFERRED LOCATION IMMEDIATELY
        if (!empty($locationContext)) {
            $context = array_merge($context, $locationContext);
            session(['ai_context' => $context]);
        }

        // Actually, logic flow:

        // Actually, logic flow:
        // 0.2 inferLocation -> Update Context.
        // If inferred, we typically return ShippingQuery.
        // But if we fall through, we might detect product.

        // We should skip detection if it looks like a location or simple affirmation
        $isAffirmation = $this->isAffirmation($content);
        $looksLikeLocation = !empty($locationContext);

        $product = null;
        if (!$looksLikeLocation) {
            $product = $this->detectProduct($content);
        }

        // Fallback: If no product detected but query is informational, assume context
        if (!$product && $this->isInformationalQuery($content)) {
            $product = $this->getLastProductConsulted();
        }

        if ($product) {
            // RELOAD CONTEXT because detectProduct modified it
            $context = session('ai_context', []);

            // 6.1 Check coherence BEFORE handling SALES INTENT
            if (isset($context['city']) && Str::slug($context['city']) !== 'bogota') {
                $isFresh = false;
                if ($product->category) {
                    $slug = $product->category->slug;
                    if (in_array($slug, ['hongos-gourmet', 'medicina-ancestral'])) {
                        $isFresh = true;
                    }
                }
                if (!$isFresh && str_contains(strtolower($product->name), 'fresco')) {
                    $isFresh = true;
                }

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

        // 6.5 Check strictly for informational queries about a product (even if detectProduct handled it, we intercepted it)
        // Actually, detectProduct calls session update and return matched product.
        // We can check intent here.

        // Refactor: Logic 6 checks detectProduct.
        // Inside logic 6, we should branch: Is it a Sales Intent ("Lo quiero") or Info Intent ("Que es?")?

        // Let's modify block 6 in a larger chunk


        // 7. General Shipping/Availability fallback check
        if ($this->isAvailabilityQuery($content)) {
            return $this->handleAvailabilityQuery();
        }

        if ($this->isShippingQuery($content)) {
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
        if (str_contains($normalized, 'los quiero') || str_contains($normalized, 'quiero comprar') || str_contains($normalized, 'carrito') || str_contains($normalized, 'agregala') || str_contains($normalized, 'lo quiero')) {
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
        'sobre',
        'hongo',
        'hongos',
        'tienen',
        'tiene',
        'venden',
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

        // PRE-FILTER: Detect Qualifiers
        $lowerContent = mb_strtolower($content);
        $wantsDry = str_contains($lowerContent, 'seco') || str_contains($lowerContent, 'deshidratado');
        $wantsFresh = str_contains($lowerContent, 'fresco');

        $query = Product::where('is_active', true)->where('stock', '>', 0);

        if ($wantsDry) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%seco%')
                    ->orWhere('name', 'like', '%deshidratado%')
                    ->orWhereHas(
                        'category',
                        fn($c) => $c->where('slug', 'deshidratados') // Adjust slug if different
                            ->orWhere('name', 'like', '%deshidratado%')
                            ->orWhere('name', 'like', '%sec%')
                    );
            });
        } elseif ($wantsFresh) {
            // If user explicitly asks for Fresh, prioritize it.
            // Note: Often Fresh products don't have "Fresco" in the name, but rely on category.
            // But avoiding negation filters is safer.
        }

        $allProducts = $query->pluck('name', 'id');

        // 1. Exact/Approximate & Substring
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

        // 3. KEYWORD PARTIAL MATCH
        $words = explode(' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', '', $content)));
        $potentialKeywords = array_filter($words, fn($w) => mb_strlen($w) > 3 && !in_array($w, $this->stopWords));

        foreach ($potentialKeywords as $word) {
            // Re-apply query filter logic here too check only against filtered candidates?
            // Actually, we can reuse $query
            // But strict WHERE name LIKE %word% might miss if we filtered too hard.
            // Let's use the BASE query logic.

            $clonedQuery = clone $query;
            $product = $clonedQuery->where('name', 'like', "%{$word}%")->first();

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
        // 1. Fetch Categories with Active Products and Stock
        // Use eager loading for products to check counts/stock
        $categories = \App\Models\Category::whereHas('products', function ($q) {
            $q->where('is_active', true)->where('stock', '>', 0);
        })->with([
                    'products' => function ($q) {
                        $q->where('is_active', true)->where('stock', '>', 0);
                    }
                ])->get();

        if ($categories->isEmpty()) {
            return [
                'type' => 'answer',
                'message' => "Lo sentimos, en este momento no tenemos stock disponible. Vuelve pronto."
            ];
        }

        // Logic to prioritize or alias categories as "Hongos Gourmet" (Fresh) and "Deshidratados" (Dry)
        // Check if we have specific matches or just list them.
        // User request: "buscar las categorias hongos gurmet y deshidratados"
        // Since names might vary (e.g. "Hongos Frescos"), I will try to map them or just use the names we have.
        // But I will group them or ensure the message logic is clean.

        // 2. Build List
        $list = "";
        $index = 1;
        $categoryMap = [];

        // GROUPING LOGIC
        $freshGroupIds = [];
        $otherCategories = [];

        foreach ($categories as $category) {
            $slug = $category->slug;
            if (in_array($slug, ['hongos-gourmet', 'medicina-ancestral'])) {
                $freshGroupIds[] = $category->id;
            } else {
                $otherCategories[] = $category;
            }
        }

        $list = "";
        $index = 1;
        $categoryMap = [];

        // 1. Add Fresh Group if exists
        if (!empty($freshGroupIds)) {
            $list .= "{$index}. **🍄 Hongos Frescos (Gourmet y Medicina)**\n";
            $categoryMap[$index] = $freshGroupIds; // Store array of IDs
            $index++;
        }

        // 2. Add Others
        foreach ($otherCategories as $category) {
            if ($category->products->count() > 0) {
                $displayName = $category->name;
                if (stripos($category->name, 'seco') !== false || stripos($category->name, 'deshidratado') !== false) {
                    $displayName = "🍂 Hongos Deshidratados (Secos)";
                }
                $list .= "{$index}. **{$displayName}**\n";
                $categoryMap[$index] = $category->id; // Store single ID
                $index++;
            }
        }

        // 3. Update Context (Preserve existing keys if needed, but here we set new pending)
        // We merged session at start of generateResponse, so $context has old data.
        // But we want to ensure we don't wipe it when saving.
        $context = session('ai_context', []);
        $context['pending_suggestion_categories'] = $categoryMap;
        session(['ai_context' => $context]);

        return [
            'type' => 'catalog',
            'message' => "¡Excelente elección! Estos son los tipos de hongos que tenemos disponibles:<br><br>" . nl2br($list) . "<br>Selecciona la categoría que te interesa para ver los productos.",
            'payload' => collect($categoryMap)->map(function ($val, $idx) use ($categories) {
                // Determine name for Payload
                $name = "Categoría {$idx}";
                if (is_array($val)) {
                    $name = "Hongos Frescos (Gourmet y Medicina)";
                } else {
                    $cat = $categories->firstWhere('id', $val);
                    $name = $cat ? $cat->name : $name;
                }
                return ['id' => 0, 'name' => $name, 'index' => $idx]; // ID 0 for group/placeholder
            })->values()->toArray()
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

    protected function createOrUpdateLead(string $content, array $context): User|array|null
    {
        // 1. Recover accumulated data from session
        $accumulated = session('ai_registration_data', []);

        // 2. Extract NEW data from content

        // Extract Email
        preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}/i', $content, $matches);
        if (isset($matches[0])) {
            $accumulated['email'] = $matches[0];
        }

        // Extract Name (Improved regex)
        // Matches: "Soy [Name]", "Me llamo [Name]", "Mi nombre es [Name]"
        if (preg_match('/(soy|me llamo|mi nombre es)\s+([a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+)/iu', $content, $nameMatch)) {
            $candidate = trim($nameMatch[2]);
            // Filter out common trailing words if user says "Soy Pablo y busco..." or "Soy Pablo y mi correo..."
            $candidate = preg_split('/(y\s+busco|y\s+quiero|pero|y\s+vivo|donde|y\s+mi|y\s+el|y\s+la|y\s+correo)/iu', $candidate)[0];
            $accumulated['name'] = trim($candidate);
        } elseif (preg_match('/(mi|el)\s+correo/iu', $content)) {
            // Implicit: "Juan Perez y mi correo es..."
            // Split to separated Name from Email introduction
            $parts = preg_split('/(y\s+)?(mi|el)\s+correo/iu', $content);
            if (!empty($parts[0]) && strlen(trim($parts[0])) > 2) {
                $accumulated['name'] = trim($parts[0]);
            }
        } elseif (isset($accumulated['email']) && !isset($accumulated['name'])) {
            // If email was just provided, maybe the rest of the string is the name?
            // User: "pepe@test.com" -> No name.
            // User: "Soy Pepe, pepe@test.com" -> Handled by logic above.
            // User: "Pepe Perez" (Just name answer) -> Hard to distinguish from random text.
            // We rely on "Waiting for user data" state.
            if (session('ai_waiting_for_user_data')) {
                // Heuristic: If content is short and not an email, assume it's the requested data (Name?)
                $cleanContent = trim(preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}/i', '', $content));
                $cleanContent = trim(preg_replace('/(soy|mi correo es|email|correo)/iu', '', $cleanContent));
                if (strlen($cleanContent) > 2 && strlen($cleanContent) < 40) {
                    // Only assign if we were asking for name?? Or just assume generic update?
                    // Let's rely on specific prompts. For now, only explicit "Soy" or explicit extraction logic.
                    // But if we already have email and are waiting, maybe this IS the name.
                    if (!isset($accumulated['name']) && !str_contains($cleanContent, ' ')) {
                        // $accumulated['name'] = $cleanContent; // Risky. Let's stick to explicit or simple prompts.
                    }
                    // Actually, usually the prompt is "Dime tu nombre". So the answer is "Pepe".
                    // If we are missing name, treat input as name.
                    if (!isset($accumulated['name'])) {
                        $accumulated['name'] = $cleanContent; // Trust input as name if valid length
                    }
                }
            }
        }

        // Extract City (explicit or from context)
        // If context has city (from inferLocation logic), pull it in.
        $contextCity = $context['city'] ?? null;
        if ($contextCity) {
            $accumulated['city'] = $contextCity;
        } else {
            // Try to infer from content again if not in context
            $inferred = $this->inferLocationFromContent($content);
            if ($inferred) {
                $accumulated['city'] = $inferred['city'];
                if (isset($inferred['locality']))
                    $accumulated['locality'] = $inferred['locality'];
            }
        }

        // Update Session with new accumulated data
        session(['ai_registration_data' => $accumulated]);

        // 3. Validation
        $missing = [];
        if (empty($accumulated['name']))
            $missing[] = 'name';
        if (empty($accumulated['email']))
            $missing[] = 'email';
        if (empty($accumulated['city']))
            $missing[] = 'city';

        if (!empty($missing)) {
            return ['status' => 'partial', 'missing' => $missing, 'data' => $accumulated];
        }

        // 4. Create User (All data present)
        $city = $accumulated['city'];
        $locality = $accumulated['locality'] ?? ($context['locality'] ?? null);

        // Persist DB logic
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
            ['email' => $accumulated['email']],
            [
                'name' => $accumulated['name'],
                'password' => Hash::make(Str::random(16)),
                'city' => $city,
                'locality' => $locality
            ]
        );
    }

    protected function isShippingQuery(string $content): bool
    {
        $content = Str::ascii(Str::lower($content));
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

        // STRICT CONTEXT CHECK BEFORE ASKING
        // If session already has a city, respect it and skip "Where are you?"
        $sessionCity = session('ai_context.city');
        if ($sessionCity) {
            $context['city'] = $sessionCity;
            if (session('ai_context.locality')) {
                $context['locality'] = session('ai_context.locality');
            }
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

        // Use ONLY memory (Last Consulted) to avoid re-triggering detectProduct (which adds to cart again)
        $last = $this->getLastProductConsulted();
        if ($last)
            $checkQueue[] = $last;

        $confirmed = $context['confirmed_products'] ?? [];
        // Robustness: Get IDs whether List or Map
        $ids = (is_array($confirmed) && !array_is_list($confirmed)) ? array_keys($confirmed) : $confirmed;

        foreach ($ids as $pid) {
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
            if ($product->is_fresh) {
                $isFreshRequest = true;
            }
        } elseif ($lastProduct = $this->getLastProductConsulted()) {
            // Fallback to memory
            $product = $lastProduct; // Weak binding, might need confirmation if msg didn't mention it
            if ($lastProduct->is_fresh) {
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
                        'message' => "Veo que estás en {$targetCity}. Por seguridad, no enviamos productos frescos allí, pero tengo disponibles estas opciones deshidratadas de **{$strainName}**:<br><br>{$list}<br><br>Marca las opciones que desees y presiona el botón para agregar.",
                        'payload' => $alternatives->map(fn($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price,
                            'image' => asset($p->first_image)
                        ])->values()->toArray()
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
                    'message' => "El costo de envío a {$targetCity} es de ${price} COP.<br><br>⚠️ <strong>Importante:</strong> En {$targetCity} no podemos entregar productos frescos (solo en Bogotá), pero tenemos disponibles estos productos secos para ti:<br>{$list}<br>Marca los que te interesen y presiona agregar.",
                    'payload' => $dryProducts->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => $p->price,
                        'image' => asset($p->first_image)
                    ])->values()->toArray()
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
            if (!is_array($confirmed) || (count($confirmed) > 0 && !array_is_list($confirmed))) {
                $confirmed = array_keys($confirmed); // Fallback if previous Map
            }
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
        $confirmed = $context['confirmed_products'] ?? [];

        // Robustness: Get IDs whether List or Map
        $ids = (is_array($confirmed) && !array_is_list($confirmed)) ? array_keys($confirmed) : $confirmed;

        if (!empty($ids)) {
            $productsKeyed = Product::whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');
            $productNames = [];

            foreach ($ids as $pid) {
                if (isset($productsKeyed[$pid])) {
                    $productNames[] = $productsKeyed[$pid]->name;
                }
            }
            // Unique names to avoid repetition in display if array had dupes
            $productNames = array_unique($productNames);
            $list = implode(', ', $productNames);

            $message .= "<br><br>Tienes en tu lista: <strong>{$list}</strong>.<br>";
            $message .= "¿Deseas agregar algo más o generamos la orden?";
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
        $confirmed = $context['confirmed_products'] ?? [];
        // Robustness: Get IDs whether List or Map
        $ids = (is_array($confirmed) && !array_is_list($confirmed)) ? array_keys($confirmed) : $confirmed;

        // Fallback: If no confirmed list but we have a last product (e.g. direct flow)
        if (empty($ids) && isset($context['last_product_id'])) {
            $ids = [$context['last_product_id']];
        }

        if (empty($ids) && isset($context['pending_suggestion_products'])) {
            // Second fallback: Pending suggestions
            if (count($context['pending_suggestion_products']) === 1) {
                $ids = $context['pending_suggestion_products'];
            }
        }

        if (empty($ids)) {
            return [
                'type' => 'error',
                'message' => 'Lo siento, no tengo claro qué productos deseas incluir en tu orden. ¿Podrías decirme el nombre o número del producto?'
            ];
        }

        // 1. LAZY REGISTRATION REMOVED - DEFER TO CHECKOUT PAGE
        // We no longer block here. We let CartManagement handle the session cart
        // and let the Checkout page handle the User creation/Login logic.


        // PRE-FILL SHIPPING DATA FOR CHECKOUT FIRST TO KNOW CONTEXT
        $city = $context['city'] ?? 'Bogotá';
        $location = $context['locality'] ?? null;
        $isBogota = (Str::slug($city) === 'bogota');

        // FILTER PRODUCTS BASED ON CITY
        $productsAdded = [];
        $ignoredFresh = false;

        // Ensure unique IDs to add qty 1
        $ids = array_unique($ids);

        foreach ($ids as $id) {
            $p = Product::with('category')->find($id);
            if (!$p)
                continue;

            // Freshness Logic (Slug Based)
            $isFresh = false;
            if ($p->category) {
                $slug = $p->category->slug;
                if (in_array($slug, ['hongos-gourmet', 'medicina-ancestral'])) {
                    $isFresh = true;
                }
            }
            if (!$isFresh && str_contains(strtolower($p->name), 'fresco')) {
                $isFresh = true;
            }

            // Strict Fresh Check: Block ONLY if we are SURE it is NOT Bogota
            if ($isFresh && !$isBogota) {
                // Double check session just in case context was stale
                $sessCity = session('ai_context')['city'] ?? '';
                if (Str::slug($sessCity) !== 'bogota') {
                    $ignoredFresh = true;
                    continue;
                }
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
                // GENERATE CHECKOUT LINK
                $cartUrl = route('cart');

                // PERSISTENCE PRE-CHECKOUT (CRITICAL)
                // Ensure checkout_shipping has the final location data before generating link
                $finalCity = session('ai_context.city') ?? $context['city'] ?? 'Bogotá';
                $finalLocality = session('ai_context.locality') ?? $context['locality'] ?? null;
                $shippingInfo = $this->getShippingInfo($finalCity, $finalLocality);

                session()->put('checkout_shipping', [
                    'is_bogota' => (Str::slug($finalCity) === 'bogota'),
                    'city' => $finalCity,
                    'location' => $finalLocality,
                    'cost' => $shippingInfo['price'] ?? 0,
                    'delivery_date' => null
                ]);

                // Cleanup processed context
                unset($context['confirmed_products']);
                unset($context['pending_suggestion_products']); // Clean suggestions too

                session(['ai_context' => $context]);

                return [
                    'type' => 'system',
                    'message' => "¡Listo! He añadido los productos a tu carrito. 🛒<br><br>Hemos configurado el envío para <strong>{$finalCity}" . ($finalLocality ? " ({$finalLocality})" : "") . "</strong>.<br><br>👉 <a href='{$cartUrl}' class='text-green-600 font-bold underline'>Haz clic aquí para finalizar tu compra</a>",
                    'actions' => [
                        ['label' => '💳 Ir a Pagar', 'type' => 'link', 'url' => $cartUrl]
                    ]
                ];
            }
        }

        // GENERATE CHECKOUT LINK
        $cartUrl = route('cart');

        // PERSISTENCE PRE-CHECKOUT (CRITICAL)
        // Ensure checkout_shipping has the final location data before generating link
        $finalCity = session('ai_context.city') ?? $context['city'] ?? 'Bogotá';
        $finalLocality = session('ai_context.locality') ?? $context['locality'] ?? null;
        $shippingInfo = $this->getShippingInfo($finalCity, $finalLocality);

        session()->put('checkout_shipping', [
            'is_bogota' => (Str::slug($finalCity) === 'bogota'),
            'city' => $finalCity,
            'location' => $finalLocality,
            'cost' => $shippingInfo['price'] ?? 0,
            'delivery_date' => null
        ]);

        // Cleanup processed context
        unset($context['confirmed_products']);
        unset($context['pending_suggestion_products']); // Clean suggestions too

        session(['ai_context' => $context]);

        return [
            'type' => 'system',
            'message' => "¡Listo! He añadido los productos a tu carrito. 🛒<br><br>Hemos configurado el envío para <strong>{$finalCity}" . ($finalLocality ? " ({$finalLocality})" : "") . "</strong>.<br><br>👉 <a href='{$cartUrl}' class='text-green-600 font-bold underline'>Haz clic aquí para finalizar tu compra</a>",
            'actions' => [
                ['label' => '💳 Ir a Pagar', 'type' => 'link', 'url' => $cartUrl]
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

        // STRICT IDEMPOTENCY (Set of IDs)
        // Ensure standard indexed array
        if (!is_array($confirmed) || (count($confirmed) > 0 && !array_is_list($confirmed))) {
            $confirmed = array_keys($confirmed); // Fallback if previous Map
        }

        if (!in_array($product->id, $confirmed)) {
            $confirmed[] = $product->id;
        }

        $context['confirmed_products'] = $confirmed;

        session(['ai_context' => $context]);
    }



    protected function handleProductQuery(Product $product, array $context): array
    {
        // RELOAD Context from session to be absolutely sure we have the latest city
        $fullContext = session('ai_context', []);
        $city = $fullContext['city'] ?? $context['city'] ?? '';
        $locality = $fullContext['locality'] ?? $context['locality'] ?? null;

        // Fallback: Check registration data if city is missing in main context
        if (empty($city)) {
            $regData = session('ai_registration_data', []);
            $city = $regData['city'] ?? '';
            $locality = $regData['locality'] ?? null;

            // If found in reg data, restore to main context
            if ($city) {
                $fullContext['city'] = $city;
                if ($locality)
                    $fullContext['locality'] = $locality;
                session(['ai_context' => $fullContext]);
            }
        }

        if ($product->is_fresh) {
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
        // Simpler: Just acknowledge and ask for locality if Bogota AND we don't know it yet.
        if (Str::slug($city) === 'bogota' && empty($locality)) {
            return [
                'type' => 'question',
                'message' => "¡Perfecto, **{$product->name}**! Como estás en Bogotá, ¿me podrías confirmar tu localidad (ej. Usaquén, Chapinero) para darte el valor exacto del domicilio?"
            ];
        }

        // National with City known OR Bogota with Locality known
        // Just show the price/closure directly (Delegate to Shipping Query logic)
        // CRITICAL: Pass $fullContext so handleShippingQuery sees the reloaded city/locality
        return $this->handleShippingQuery('costo envio', $fullContext);
    }

    protected function callLlm(string $content): array
    {
        // 0. Safety Net for Guest Users (Lazy Registration / Lead Capture)
        // If we don't know who they are, don't handoff yet. Ask for data.
        if (!auth()->check()) {
            session(['ai_waiting_for_user_data' => true]);
            return [
                'type' => 'question',
                'message' => "Antes de pasarte con uno de nuestros agentes o procesar tu solicitud, necesito registrarte. Por favor indícame tu **nombre** y **correo electrónico**."
            ];
        }

        // 1. Notify via Slack Channel (Using Notification Facade)
        // Build rich payload
        $context = session('ai_context', []);
        $city = $context['city'] ?? 'No detectada';

        $cartItems = [];
        $confirmedMap = $context['confirmed_products'] ?? [];
        if (array_is_list($confirmedMap) && !empty($confirmedMap))
            $confirmedMap = array_count_values($confirmedMap);

        if (!empty($confirmedMap)) {
            foreach ($confirmedMap as $pid => $qty) {
                $p = Product::find($pid);
                if ($p)
                    $cartItems[] = $p->name;
            }
        }
        if (empty($cartItems) && !empty($context['last_product_id'])) {
            $p = Product::find($context['last_product_id']);
            if ($p)
                $cartItems[] = $p->name . " (Interés)";
        }
        $cartString = empty($cartItems) ? 'Vacío' : implode(', ', $cartItems);
        $userString = auth()->check() ? auth()->user()->email : 'Guest';

        try {
            \Illuminate\Support\Facades\Notification::route('slack', config('services.slack.notifications.channel'))
                ->notify(new \App\Notifications\AiAgentHandoffNotification($content, [
                    'city' => $city,
                    'user' => $userString,
                    'cart' => $cartString
                ]));
        } catch (\Exception $e) {
            Log::error("Failed to send Slack notification: " . $e->getMessage());
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

        // 2. Check for Cities (Fuzzy Match)
        // Optimization: Cache cities or query efficiently.
        $dbCities = ShippingZone::select('city')->distinct()->pluck('city');

        $shortestCityDistance = -1;
        $bestCityMatch = null;

        // Flatten words array to string for multi-word city check? 
        // Or check word by word? "San Andres" is 2 words.
        // Let's first check word-by-word for single-word cities, then full string logic.

        // Manual Map for common colloquialisms
        $cityAliases = [
            'villao' => 'Villavicencio',
            'medallo' => 'Medellín',
            'quilla' => 'Barranquilla',
            'cartacho' => 'Cartagena',
            'bogo' => 'Bogotá',
            'bog' => 'Bogotá'
        ];

        foreach ($words as $word) {
            $wordLower = strtolower($word);

            // Check Aliases first
            if (isset($cityAliases[$wordLower])) {
                return ['city' => $cityAliases[$wordLower]];
            }

            if (strlen($word) < 3)
                continue;

            $commonWords = ['para', 'pero', 'como', 'donde', 'envio', 'valor', 'costo', 'tienen', 'hongo', 'luego', 'puedo', 'quiero', 'dale', 'bien', 'bueno', 'gracias', 'estoy', 'vivo', 'ciudad'];
            if (in_array($wordLower, $commonWords))
                continue;

            foreach ($dbCities as $option) {
                // Remove accents for comparison
                $optionAscii = Str::ascii(strtolower($option));
                $wordAscii = Str::ascii($wordLower);

                // Exact match (ascii)
                if ($optionAscii === $wordAscii) {
                    return ['city' => $option];
                }

                $dist = levenshtein($wordAscii, $optionAscii);

                // Adaptive tolerance: 
                // <= 4 chars -> 0 or 1 error (but 1 on 3 chars is risky, e.g. Cali vs Cali?)
                // > 4 chars -> 2 errors
                $maxDist = strlen($word) <= 4 ? 1 : 2;

                if ($dist <= $maxDist) {
                    // Store best match but keep searching?
                    if ($shortestCityDistance === -1 || $dist < $shortestCityDistance) {
                        $bestCityMatch = $option;
                        $shortestCityDistance = $dist;
                    }
                }
            }
        }

        if ($bestCityMatch) {
            return ['city' => $bestCityMatch];
        }

        // Fallback: Check multi-word cities (e.g. San Andres) using ascii stripos on FULL CONTENT
        // This covers cases where the user types "San Andres" and fuzzy match on "San" or "Andres" alone might be ambiguous or weak
        $contentAscii = Str::ascii(strtolower($content));
        foreach ($dbCities as $dbCity) {
            if (str_contains($contentAscii, Str::ascii(strtolower($dbCity)))) {
                return ['city' => $dbCity];
            }
        }

        return null;
    }

    protected function isHandoffRequest(string $content): bool
    {
        $content = strtolower($content);
        $keywords = [
            'humano',
            'persona',
            'asesor',
            'alguien real',
            'hablar con alguien',
            'atencion al cliente',
            'soporte',
            'ayuda humana',
            'comunicame',
            'contactar'
        ];

        foreach ($keywords as $kw) {
            if (str_contains($content, $kw)) {
                return true;
            }
        }
        return false;
    }
}
