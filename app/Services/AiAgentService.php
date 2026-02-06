<?php

namespace App\Services;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\Handlers\CatalogHandler;
use App\Services\Ai\Handlers\HandoffHandler;
use App\Services\Ai\Handlers\OrderHandler;
use App\Services\Ai\Handlers\ShippingHandler;
use App\Services\Ai\Handlers\RegistrationHandler;
use App\Services\Ai\Handlers\SpamHandler;
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
        RegistrationHandler $registrationHandler,
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
            $registrationHandler,
            $orderHandler,
            $catalogHandler,
            $shippingHandler
        ];
    }

    public function processMessage(string $content, string $ip, array $sessionContext = []): array
    {
        // Refresh Context (Crucial for Tests/State)
        $this->context->reload();

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

        // Action Loop
        $maxSteps = 5;
        $currentStep = 0;

        // Initial Query (Expecting JSON)
        $nextPrompt = $userMessage;

        while ($currentStep < $maxSteps) {
            // Call Gemini forcing JSON
            $rawResponse = $this->gemini->generateContent($nextPrompt, $systemPrompt, true);
            $parsed = $this->parseJsonAction($rawResponse);

            if ($parsed['needs_action'] && !empty($parsed['action_name'])) {
                // Execute Tool
                $toolResult = $this->executeTool($parsed['action_name'], $parsed['action_params'] ?? []);

                // Add Assistant Action to history (We reconstruct what the assistant 'thought')
                // Ideally we should store the raw JSON response as the assistant message, 
                // but for readability we might want to store a summary or the raw JSON.
                $this->context->addHistoryMessage('assistant', $rawResponse);

                // Add System Tool Output to history
                $this->context->addHistoryMessage('user', "SYSTEM_TOOL_OUTPUT: " . $toolResult);

                // Update Client History
                $this->gemini->setHistory($this->context->getHistory());

                // Re-query Gemini with tool result
                $nextPrompt = "(Tool Output Received)";
                $currentStep++;
            } else {
                // Final Answer (Response is in $parsed['response']) or fallback if parsing failed
                $finalText = $parsed['response'] ?? $rawResponse;

                // If it's a JSON object string, try to extract 'response' field if possible, 
                // otherwise just use the text.
                // parseJsonAction already tried to extract 'response'.

                if (empty($finalText) && isset($parsed['error'])) {
                    $finalText = "Lo siento, tuve un problema interno. ¿Podrías intentar de nuevo?";
                }

                $this->context->addHistoryMessage('assistant', $rawResponse); // Save raw JSON logic

                return [
                    'type' => 'answer',
                    'message' => $finalText
                ];
            }
        }

        return [
            'type' => 'error',
            'message' => 'Lo siento, no pude procesar tu solicitud después de varios intentos.'
        ];
    }

    protected function parseJsonAction(string $response): array
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Backup: Try to find JSON block in markdown ```json ... ```
            if (preg_match('/```json\s*(\{.*\})\s*```/s', $response, $matches)) {
                $data = json_decode($matches[1], true);
            }
        }

        if (is_array($data)) {
            return [
                'needs_action' => $data['needs_action'] ?? false,
                'action_name' => $data['action_name'] ?? null,
                'action_params' => $data['action_params'] ?? [],
                'response' => $data['response'] ?? null
            ];
        }

        // Fallback if not JSON (Old format or error)
        Log::warning("Gemini Non-JSON Response: " . $response);
        return [
            'needs_action' => false,
            'response' => $response,
            'error' => true
        ];
    }

    protected function executeTool(string $actionName, array $params): string
    {
        Log::info("Executing Tool: {$actionName}", $params);

        switch ($actionName) {
            case 'GET_SHIPPING_PRICE':
                // Delegate to ShippingHandler
                $city = $params['city'] ?? '';
                $locality = $params['locality'] ?? null;
                $res = $this->shippingHandler->getShippingInfo($city, $locality);

                if (isset($res['error']))
                    return "Error: " . $res['error'];
                return json_encode($res);

            case 'GET_PRODUCT':
                // Delegate to CatalogHandler
                $name = $params['product_name'] ?? '';
                $res = $this->catalogHandler->findProduct($name, $this->context);

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
}
