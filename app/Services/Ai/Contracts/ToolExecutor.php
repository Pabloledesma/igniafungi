<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\ConversationContext;

interface ToolExecutor
{
    /** @return array<string> */
    public function supportedTools(): array;

    public function executeTool(string $toolName, array $params, ConversationContext $context): string;
}
