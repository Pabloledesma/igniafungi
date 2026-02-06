<?php

namespace App\Services\Ai\Handlers;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use App\Services\Ai\Traits\FuzzyMatcher;
use App\Models\ShippingZone;

class RegistrationHandler implements IntentHandler
{
    use FuzzyMatcher;

    protected OrderHandler $orderHandler;

    public function __construct(OrderHandler $orderHandler)
    {
        $this->orderHandler = $orderHandler;
    }

    public function canHandle(string $content, ConversationContext $context): bool
    {
        return session('ai_waiting_for_user_data', false) === true;
    }

    public function handle(string $content, ConversationContext $context): array
    {
        $data = session('ai_registration_data', []);

        // 1. Extract Email
        if (empty($data['email'])) {
            preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches);
            if (!empty($matches[0])) {
                $data['email'] = $matches[0];
            }
        }

        // 2. Extract Name (If no regex match for email, or if we have email and this is a new message)
        // Heuristic: If prompt was "Dime tu nombre", assume content is name.
        // Or if content starts with "Soy", "Me llamo".

        $nameCandidate = null;
        if (preg_match('/(soy|me llamo|mi nombre es)\s+([^,;.]+)/i', $content, $m)) {
            $nameCandidate = trim($m[2]);
        } elseif (empty($data['email']) && !str_contains($content, '@')) {
            // If we don't have email and input doesn't look like email, maybe it's name?
            // But usually we ask specific questions.
            // Test case: "Soy Pepe Perez" -> matches regex.
        } elseif (!empty($data['email']) && !isset($data['name'])) {
            // We have email, maybe this message IS the name?
            // If valid email was just extracted, the REST of the string might be name?
            // Or if this message does NOT contain email, treat as name.
            if (!str_contains($content, '@')) {
                $nameCandidate = trim($content);
            }
        }

        if ($nameCandidate) {
            $data['name'] = $nameCandidate;
        }

        // Save progress
        session(['ai_registration_data' => $data]);

        // 3. Check Completeness
        if (empty($data['email'])) {
            return [
                'type' => 'question',
                'message' => 'Por favor compárteme tu correo electrónico para enviarte el resumen.'
            ];
        }

        if (empty($data['name'])) {
            return [
                'type' => 'question',
                'message' => '¿Cuál es tu nombre completo para el registro?'
            ];
        }

        // 3b. Infer Location (Robustness)
        // Even if we are registering, user might say "Soy Juan from Bogota"
        $cities = ShippingZone::pluck('city')->unique()->values()->toArray();
        $locData = $this->inferLocationFromContent($content, $cities);
        if (!empty($locData['city'])) {
            $context->set('city', $locData['city']);
            if (!empty($locData['locality'])) {
                $context->set('locality', $locData['locality']);
            }
        }

        // 4. Create User
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make('password'), // Helper default
                'city' => $context->get('city') ?? 'Bogotá' // Fallback
            ]
        );

        // Update city if not set on user
        if (!$user->city && $context->get('city')) {
            $user->city = $context->get('city');
            $user->save();
        }

        Auth::login($user);

        // Clear Session Flags
        session()->forget(['ai_waiting_for_user_data', 'ai_registration_data']);

        // 5. Finalize via OrderHandler
        $response = $this->orderHandler->processOrder($context);

        // Prepend success message
        $response['message'] = "¡Gracias {$user->name}! Te he registrado exitosamente.\n\n" . $response['message'];

        return $response;
    }
}
