<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class ConversationContext
{
    protected array $data;
    protected array $history;

    public function __construct()
    {
        $this->reload();
    }

    public function reload(): void
    {
        $this->data = session('ai_context', []);
        $this->history = session('ai_chat_history', []);
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
        $this->persist();
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
        $this->persist();
    }

    public function all(): array
    {
        return $this->data;
    }

    public function addProduct(int $productId): void
    {
        $confirmed = $this->data['confirmed_products'] ?? [];
        if (!is_array($confirmed)) {
            $confirmed = [];
        }

        // Ensure uniqueness
        if (!in_array($productId, $confirmed)) {
            $confirmed[] = $productId;
        }

        $this->data['confirmed_products'] = $confirmed;
        $this->data['last_product_id'] = $productId; // Update focus
        $this->persist();
    }

    public function getConfirmedProductIds(): array
    {
        $confirmed = $this->data['confirmed_products'] ?? [];
        return (is_array($confirmed) && !array_is_list($confirmed)) ? array_keys($confirmed) : $confirmed;
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function addHistoryMessage(string $role, string $content): void
    {
        $this->history[] = ['role' => $role, 'content' => $content];

        // Keep last 20
        if (count($this->history) > 20) {
            $this->history = array_slice($this->history, -20);
        }

        session(['ai_chat_history' => $this->history]);
    }

    protected function persist(): void
    {
        session(['ai_context' => $this->data]);
        Log::info("AiContext Persisted: " . json_encode($this->data));
    }
}
