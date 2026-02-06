<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    protected string $apiKey;
    protected string $model;
    protected array $history = [];

    public function __construct()
    {
        $this->apiKey = config('ai.drivers.gemini.api_key');
        $this->model = config('ai.drivers.gemini.model', 'gemini-2.0-flash-exp');
    }

    public function setHistory(array $history): void
    {
        $this->history = $history;
    }

    public function generateContent(string $prompt, string $systemPrompt, bool $expectJson = false): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        // Filter and format history
        $contents = array_map(function ($msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            return [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }, $this->history);

        // Add current user prompt
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $payload = [
            'contents' => $contents,
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 800,
            ]
        ];

        if ($expectJson) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error("Gemini API Error: " . $response->body());
            return json_encode(['error' => "Lo siento, tengo problemas de conexión en este momento."]); // Return JSON error if JSON expected? Or string.
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
