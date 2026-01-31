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
        // Check for free shipping threshold if user has a cart total in context
        // For now preventing usage of non-existent variables
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
            return [
                'type' => 'question',
                'message' => 'Para calcular el costo del envío, necesito saber ¿en qué ciudad te encuentras?'
            ];
        }

        // Normalize and find matching city in DB
        $dbCities = ShippingZone::select('city')->distinct()->pluck('city');
        $matchedCity = $dbCities->first(function ($dbCity) use ($city) {
            return Str::slug($dbCity) === Str::slug($city);
        });

        $targetCity = $matchedCity ?? $city; // Use DB version if found, else user input

        if (Str::slug($targetCity) === 'bogota') { // Robust check for Bogotá
            if (!$locality) {
                return [
                    'type' => 'question',
                    'message' => 'Para Bogotá necesito saber tu localidad.'
                ];
            }

            // Query ShippingZone
            $zone = ShippingZone::where('city', $targetCity) // Use canonical name
                ->where('locality', 'like', "%{$locality}%")
                ->first();

            $price = $zone ? number_format($zone->price, 0) : 'variable';
            return [
                'type' => 'answer',
                'message' => "El costo de envío a {$targetCity}, localidad {$locality} es de ${price} COP."
            ];
        }

        // National shipping logic: Look for price in DB first
        if ($matchedCity) {
            $zone = ShippingZone::where('city', $matchedCity)->whereNull('locality')->first();
            if ($zone) {
                $price = number_format($zone->price, 0);
                return [
                    'type' => 'answer',
                    'message' => "El costo de envío a {$matchedCity} es de ${price} COP."
                ];
            }
        }

        return [
            'type' => 'answer',
            'message' => "Hacemos envíos nacionales a {$targetCity}. Contáctanos para cotizar el envío exacto."
        ];
    }

    protected function detectProduct(string $content): ?Product
    {
        // Very simple implementation: split words and search
        // Ideally use full text search or LLM
        $words = explode(' ', $content);
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $product = Product::where('name', 'like', "%{$word}%")
                    ->where('is_active', true)
                    ->where('stock', '>', 0)
                    ->first();
                if ($product)
                    return $product;
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
}
