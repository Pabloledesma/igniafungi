<?php

namespace App\Services\Ai\Handlers;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Services\Ai\Traits\FuzzyMatcher;
use Illuminate\Support\Facades\Log;

class HandoffHandler implements IntentHandler
{
    use FuzzyMatcher; // Hypothetically useful if we use fuzzy matching for keywords

    protected array $keywords = [
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

    public function canHandle(string $content, ConversationContext $context): bool
    {
        $normalized = strtolower($content);
        foreach ($this->keywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }
        return false;
    }

    public function handle(string $content, ConversationContext $context): array
    {
        // Simulate Slack Notification
        Log::info("HANDOFF TRIGGERED: " . $content);

        try {
            $user = auth()->user();
            $notificationData = [
                'city' => $context->get('city') ?? ($user->city ?? 'Desconocida'),
                'user' => $user ? "{$user->name} ({$user->email})" : 'Invitado',
                'cart' => implode(', ', $context->getConfirmedProductIds())
            ];

            \Illuminate\Support\Facades\Notification::route('slack', config('services.slack.notifications.channel'))
                ->notify(new \App\Notifications\AiAgentHandoffNotification($content, $notificationData));

        } catch (\Exception $e) {
            Log::error("Failed to send Handoff Notification: " . $e->getMessage());
        }

        return [
            'type' => 'system',
            'message' => 'He notificado a un asesor humano. Te responderemos pronto.'
        ];
    }
}
