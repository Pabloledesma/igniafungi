<?php

namespace App\Services;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\Contracts\ToolExecutor;
use App\Services\Ai\ConversationContext;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiToolLoop;
use App\Services\Ai\Handlers\CatalogHandler;
use App\Services\Ai\Handlers\HandoffHandler;
use App\Services\Ai\Handlers\OrderHandler;
use App\Services\Ai\Handlers\RegistrationHandler;
use App\Services\Ai\Handlers\ShippingHandler;
use App\Services\Ai\Handlers\SpamHandler;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    protected ConversationContext $context;

    protected GeminiToolLoop $toolLoop;

    /** @var array<IntentHandler> */
    protected array $handlers;

    public function __construct(
        ConversationContext $context,
        GeminiClient $gemini,
        RegistrationHandler $registrationHandler,
        SpamHandler $spamHandler,
        HandoffHandler $handoffHandler,
        OrderHandler $orderHandler,
        CatalogHandler $catalogHandler,
        ShippingHandler $shippingHandler
    ) {
        $this->context = $context;

        // Pipeline Order Matters!
        $this->handlers = [
            $spamHandler,
            $handoffHandler,
            $registrationHandler,
            $orderHandler,
            $catalogHandler,
            $shippingHandler,
        ];

        $this->toolLoop = new GeminiToolLoop($gemini, $context);
        $this->registerTools();
    }

    public function processMessage(string $content, string $ip, array $sessionContext = []): array
    {
        $this->context->reload();

        // Apply explicit product IDs from UI selections (catalog checkboxes/buttons)
        if (! empty($sessionContext['explicit_product_ids'])) {
            foreach ($sessionContext['explicit_product_ids'] as $productId) {
                $this->context->addProduct((int) $productId);
            }
        }

        if ($cached = $this->deduplicate($content, $ip)) {
            return $cached;
        }

        // Run Pipeline
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content, $this->context)) {
                Log::info('AiAgent Pipeline Matched: '.get_class($handler));
                $response = $handler->handle($content, $this->context);
                $this->cacheResponse(md5($content.$ip), $response);

                return $response;
            }
        }

        // Fallback: Gemini LLM with Tool Loop
        $response = $this->toolLoop->execute($content);
        $this->cacheResponse(md5($content.$ip), $response);

        return $response;
    }

    protected function registerTools(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ToolExecutor) {
                $this->toolLoop->registerExecutor($handler);
            }
        }
    }

    protected function deduplicate(string $content, string $ip): ?array
    {
        $msgHash = md5($content.$ip);
        $lastHash = session('ai_last_msg_hash');
        $lastTime = session('ai_last_msg_timestamp');

        if ($lastHash === $msgHash && $lastTime && (time() - $lastTime < 2)) {
            $cached = session('ai_last_response');
            if ($cached) {
                return $cached;
            }
        }

        return null;
    }

    protected function cacheResponse(string $hash, array $response): void
    {
        session([
            'ai_last_msg_hash' => $hash,
            'ai_last_msg_timestamp' => time(),
            'ai_last_response' => $response,
        ]);
    }
}
