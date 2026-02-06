<?php

namespace App\Services\Ai\Handlers;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use Illuminate\Support\Facades\Cache;

class SpamHandler implements IntentHandler
{
    protected string $blockMessage = '';

    public function canHandle(string $content, ConversationContext $context): bool
    {
        // Use IP from request() helper or passed in context if we wanted to be pure, 
        // but Handler contract is (content, context). 
        // We'll rely on request() as per original design.
        $ip = request()->ip();

        return $this->isSpam($ip);
    }

    public function handle(string $content, ConversationContext $context): array
    {
        return [
            'type' => 'error', // Use 'error' type for blocking
            'message' => $this->blockMessage
        ];
    }

    protected function isSpam(string $ip): bool
    {
        // 1. Rate Limiting (Dual Layer)
        $keyMinute = 'ai_chat:' . $ip;
        $keyDaily = 'ai_chat_daily:' . $ip;

        // Layer 1: Burst Protection (10 per minute)
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($keyMinute, 10)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($keyMinute);
            $this->blockMessage = "¡Vas muy rápido! 🏎️ Por favor espera {$seconds} segundos antes de enviar otro mensaje.";
            return true;
        }

        // Layer 2: Daily Cost Control (50 per day)
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($keyDaily, 50)) {
            $this->blockMessage = 'Has alcanzado el límite diario de mensajes gratuitos. Si necesitas soporte urgente, contáctanos directamente por WhatsApp.';
            return true;
        }

        // Hit logic (Increment validation)
        \Illuminate\Support\Facades\RateLimiter::hit($keyMinute, 60); // Decay 60 seconds
        \Illuminate\Support\Facades\RateLimiter::hit($keyDaily, 86400); // Decay 24 hours

        return false;
    }
}
