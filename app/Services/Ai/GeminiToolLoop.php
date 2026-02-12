<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\ToolExecutor;
use Illuminate\Support\Facades\Log;

class GeminiToolLoop
{
    protected const MAX_STEPS = 5;

    /** @var array<string, ToolExecutor> */
    protected array $toolRegistry = [];

    public function __construct(
        protected GeminiClient $gemini,
        protected ConversationContext $context
    ) {}

    public function registerExecutor(ToolExecutor $executor): void
    {
        foreach ($executor->supportedTools() as $toolName) {
            $this->toolRegistry[$toolName] = $executor;
        }
    }

    public function execute(string $userMessage): array
    {
        $this->context->addHistoryMessage('user', $userMessage);
        $this->gemini->setHistory($this->context->getHistory());

        $systemPrompt = file_get_contents(resource_path('markdown/GEMINI.md'));
        $nextPrompt = $userMessage;
        $currentStep = 0;

        while ($currentStep < self::MAX_STEPS) {
            $rawResponse = $this->gemini->generateContent($nextPrompt, $systemPrompt, true);
            $parsed = $this->parseJsonAction($rawResponse);

            if ($parsed['needs_action'] && ! empty($parsed['action_name'])) {
                $toolResult = $this->dispatchTool($parsed['action_name'], $parsed['action_params'] ?? []);

                $this->context->addHistoryMessage('assistant', $rawResponse);
                $this->context->addHistoryMessage('user', 'SYSTEM_TOOL_OUTPUT: '.$toolResult);
                $this->gemini->setHistory($this->context->getHistory());

                $nextPrompt = '(Tool Output Received)';
                $currentStep++;
            } else {
                $finalText = $parsed['response'] ?? $rawResponse;

                if (empty($finalText) && isset($parsed['error'])) {
                    $finalText = 'Lo siento, tuve un problema interno. ¿Podrías intentar de nuevo?';
                }

                $this->context->addHistoryMessage('assistant', $rawResponse);

                return [
                    'type' => 'answer',
                    'message' => $finalText,
                ];
            }
        }

        return [
            'type' => 'error',
            'message' => 'Lo siento, no pude procesar tu solicitud después de varios intentos.',
        ];
    }

    protected function dispatchTool(string $actionName, array $params): string
    {
        Log::info("Executing Tool: {$actionName}", $params);

        $executor = $this->toolRegistry[$actionName] ?? null;

        if (! $executor) {
            return "Error: Tool {$actionName} not found.";
        }

        return $executor->executeTool($actionName, $params, $this->context);
    }

    protected function parseJsonAction(string $response): array
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/```json\s*(\{.*\})\s*```/s', $response, $matches)) {
                $data = json_decode($matches[1], true);
            }
        }

        if (is_array($data)) {
            return [
                'needs_action' => $data['needs_action'] ?? false,
                'action_name' => $data['action_name'] ?? null,
                'action_params' => $data['action_params'] ?? [],
                'response' => $data['response'] ?? null,
            ];
        }

        Log::warning('Gemini Non-JSON Response: '.$response);

        return [
            'needs_action' => false,
            'response' => $response,
            'error' => true,
        ];
    }
}
