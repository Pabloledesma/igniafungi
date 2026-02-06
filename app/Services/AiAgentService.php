<?php

namespace App\Services;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\Handlers\CatalogHandler;
use App\Services\Ai\Handlers\HandoffHandler;
use App\Services\Ai\Handlers\OrderHandler;
use App\Services\Ai\Handlers\ShippingHandler;
use App\Services\Ai\Handlers\SpamHandler;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    protected ConversationContext $context;
    protected GeminiClient $gemini;

    // Handlers
    protected array $handlers;

    // Dedicated instance for tool usage
    protected ShippingHandler $shippingHandler;
    protected CatalogHandler $catalogHandler;

    public function __construct(
        ConversationContext $context,
        GeminiClient $gemini,
        SpamHandler $spamHandler,
        HandoffHandler $handoffHandler,
        OrderHandler $orderHandler,
        CatalogHandler $catalogHandler,
        ShippingHandler $shippingHandler
    ) {
        $this->context = $context;
        $this->gemini = $gemini;

        $this->shippingHandler = $shippingHandler;
        $this->catalogHandler = $catalogHandler;

        // Pipeline Order Matters!
        $this->handlers = [
            $spamHandler,
            $handoffHandler,
            $orderHandler,
            $catalogHandler,
            $shippingHandler
        ];
    }

    public function processMessage(string $content, string $ip, array $sessionContext = []): array
    {
        // 0. Deduplication Guard
        $msgHash = md5($content . $ip);
        $lastHash = session('ai_last_msg_hash');
        $lastTime = session('ai_last_msg_timestamp');

        if ($lastHash === $msgHash && $lastTime && (time() - $lastTime < 2)) {
            $cached = session('ai_last_response');
            if ($cached) {
                return $cached;
            }
        }

        // 1. Run Pipeline
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content, $this->context)) {
                Log::info("AiAgent Pipeline Matched: " . get_class($handler));
                $response = $handler->handle($content, $this->context);
                $this->cacheResponse($msgHash, $response);
                return $response;
            }
        }

        // 2. Fallback: Gemini LLM with Tool Loop
        $response = $this->queryGeminiWithTools($content);
        $this->cacheResponse($msgHash, $response);
        return $response;
    }

    protected function cacheResponse($hash, $response)
    {
        session([
            'ai_last_msg_hash' => $hash,
            'ai_last_msg_timestamp' => time(),
            'ai_last_response' => $response
        ]);
    }

    protected function queryGeminiWithTools(string $userMessage): array
    {
        // Add User Message to History
        $this->context->addHistoryMessage('user', $userMessage);

        // Prepare Gemini Client
        $this->gemini->setHistory($this->context->getHistory());

        // System Prompt
        $systemPrompt = file_get_contents(resource_path('markdown/GEMINI.md'));

        // Initial Query
        $response = $this->gemini->generateContent($userMessage, $systemPrompt);

        // Action Loop
        $maxSteps = 5;
        $currentStep = 0;

        while ($currentStep < $maxSteps) {
            $parsed = $this->parseAction($response);

            if ($parsed['has_action']) {
                $toolResult = $this->executeTool($parsed['action_name'], $parsed['action_params']);

                // Add Assistant Action Request to history
                $this->context->addHistoryMessage('assistant', $response);

                // Add System Tool Output to history
                $this->context->addHistoryMessage('user', "SYSTEM_TOOL_OUTPUT: " . $toolResult);

                // Update Client History
                $this->gemini->setHistory($this->context->getHistory());

                // Re-query Gemini with tool result
                $response = $this->gemini->generateContent("(Tool Output Received)", $systemPrompt);
                $currentStep++;
            } else {
                // Final Answer
                $this->context->addHistoryMessage('assistant', $response);
                return [
                    'type' => 'answer',
                    'message' => $response
                ];
            }
        }

        return [
            'type' => 'error',
            'message' => 'Lo siento, no pude procesar tu solicitud después de varios intentos.'
        ];
    }

    protected function parseAction(string $response): array
    {
        if (preg_match('/ACTION:\s*(\w+)\s*({.*})?/s', $response, $matches)) {
            $actionName = $matches[1];
            $jsonParams = $matches[2] ?? '{}';
            $params = json_decode($jsonParams, true) ?? [];
            return [
                'has_action' => true,
                'action_name' => $actionName,
                'action_params' => $params
            ];
        }
        return ['has_action' => false];
    }

    protected function executeTool(string $actionName, array $params): string
    {
        Log::info("Executing Tool: {$actionName}", $params);

        switch ($actionName) {
            case 'GET_SHIPPING_PRICE':
                // Delegate to ShippingHandler Logic
                $city = $params['city'] ?? '';
                $locality = $params['locality'] ?? null;
                return $this->callProtectedMethod($this->shippingHandler, 'getShippingInfo', [$city, $locality]);

            case 'GET_PRODUCT':
                $res = $this->getProduct($params['product_name'] ?? '');
                if (isset($res['error']))
                    return "Error: " . $res['error'];

                // Format for LLM
                $str = "Producto: {$res['product']} (Stock: {$res['stock']}). Precio: $" . number_format($res['price'], 0, ',', '.');
                if (!empty($res['description']))
                    $str .= " Descripción: {$res['description']}";
                if (!empty($res['short_description']))
                    $str .= " Resumen: {$res['short_description']}";
                if (!empty($res['category']))
                    $str .= " Categoría: {$res['category']}";
                return $str;

            case 'SHOW_CATALOG':
                $res = $this->catalogHandler->handle('', $this->context);
                return json_encode($res['payload']);

            default:
                return "Error: Tool {$actionName} not found.";
        }
    }

    public function getProduct(string $productName): array
    {
        // Simple Product Search
        $p = Product::where('name', 'like', "%{$productName}%")->where('is_active', true)->first();
        if (!$p)
            return ['error' => "Producto no encontrado."];

        // Track context
        $this->context->addProduct($p->id);

        return [
            'product' => $p->name,
            'stock' => $p->stock,
            'price' => $p->price,
            'description' => $p->description,
            'short_description' => $p->short_description,
            'category' => $p->category->name ?? ''
        ];
    }

    // Helper to call protected methods
    protected function callProtectedMethod($object, $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        $res = $m->invokeArgs($object, $args);

        if (isset($res['error']))
            return "Error: " . $res['error'];
        if (isset($res['price']))
            return "Precio: " . $res['price'];
        return json_encode($res);
    }
}
