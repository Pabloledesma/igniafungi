<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class AiAgentHandoffNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $contextData;

    /**
     * Create a new notification instance.
     *
     * @param string $message The user's message
     * @param array $contextData additional context (city, user, cart, etc)
     */
    public function __construct(string $message, array $contextData = [])
    {
        $this->message = $message;
        $this->contextData = $contextData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $user = $this->contextData['user'] ?? 'Guest';
        $city = $this->contextData['city'] ?? 'No detectada';
        $cart = $this->contextData['cart'] ?? 'Vacío';
        $content = $this->message;

        return (new SlackMessage)
            ->to(config('services.slack.notifications.channel'))
            ->text('🚨 Atención Humana Requerida')
            ->headerBlock('🚨 Atención Humana Requerida')
            ->sectionBlock(function ($block) use ($user, $city) {
                $block->field("*Usuario:*\n$user");
                $block->field("*Ciudad:*\n$city");
            })
            ->sectionBlock(function ($block) use ($cart) {
                $block->field("*Carrito:*\n$cart");
            })
            ->dividerBlock()
            ->sectionBlock(function ($block) use ($content) {
                $block->text("*Mensaje:*\n$content");
            });
    }
}
