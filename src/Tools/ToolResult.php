<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools;

use ClaudeAgents\Contracts\ToolResultInterface;

/**
 * Represents the result of a tool execution.
 */
class ToolResult implements ToolResultInterface
{
    public function __construct(
        private readonly string $content,
        private readonly bool $isError = false,
    ) {
    }

    /**
     * Create a successful result.
     */
    public static function success(string|array $content): self
    {
        $contentStr = is_array($content) ? json_encode($content) : $content;

        return new self($contentStr, false);
    }

    /**
     * Create an error result.
     */
    public static function error(string $message): self
    {
        return new self($message, true);
    }

    /**
     * Create from a throwable.
     */
    public static function fromException(\Throwable $e): self
    {
        return new self("Error: {$e->getMessage()}", true);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Check if the result is successful (not an error).
     */
    public function isSuccess(): bool
    {
        return ! $this->isError;
    }

    public function toApiFormat(string $toolUseId): array
    {
        $result = [
            'type' => 'tool_result',
            'tool_use_id' => $toolUseId,
            'content' => $this->content,
        ];

        if ($this->isError) {
            $result['is_error'] = true;
        }

        return $result;
    }
}
