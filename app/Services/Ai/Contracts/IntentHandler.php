<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\ConversationContext;

interface IntentHandler
{
    public function canHandle(string $content, ConversationContext $context): bool;

    public function handle(string $content, ConversationContext $context): array;
}
