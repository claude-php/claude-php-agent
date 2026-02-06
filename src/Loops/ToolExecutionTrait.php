<?php

declare(strict_types=1);

namespace ClaudeAgents\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Tools\ToolResult;

/**
 * Shared tool execution logic for loop implementations.
 *
 * Provides normalizeContentBlocks() and executeTools() methods
 * used by ReactLoop, ReflectionLoop, and PlanExecuteLoop.
 */
trait ToolExecutionTrait
{
    /**
     * Normalize content blocks to ensure tool_use.input is always a JSON object.
     *
     * Fixes the "Input should be a valid dictionary" error that occurs when
     * empty input {} is decoded as [] and re-encoded as [] instead of {}.
     *
     * @param array<mixed> $content Response content blocks
     * @return array<mixed> Normalized content blocks
     */
    private function normalizeContentBlocks(array $content): array
    {
        $normalized = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                $normalized[] = $block;

                continue;
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $input = $block['input'] ?? [];
                // Convert array to stdClass to ensure JSON encodes as {} not []
                if (is_array($input)) {
                    $block['input'] = (object) $input;
                }
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * Execute tools from response content.
     *
     * @param AgentContext $context
     * @param array<mixed> $content Response content blocks
     * @return array<array<string, mixed>> Tool results for API
     */
    private function executeTools(AgentContext $context, array $content): array
    {
        $results = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if ($type !== 'tool_use') {
                continue;
            }

            $toolName = $block['name'] ?? '';
            $toolInput = $block['input'] ?? [];
            $toolId = $block['id'] ?? '';

            // Ensure toolInput is an array for tool execution
            // (may be stdClass after normalizeContentBlocks)
            if ($toolInput instanceof \stdClass) {
                $toolInput = (array) $toolInput;
            }

            $this->logger->debug("Executing tool: {$toolName}", ['input' => $toolInput]);

            // Wrap in try-catch to ensure every tool_use gets a tool_result
            // The API requires each tool_use to have a corresponding tool_result
            try {
                $tool = $context->getTool($toolName);

                if ($tool === null) {
                    $result = ToolResult::error("Unknown tool: {$toolName}");
                } else {
                    $result = $tool->execute($toolInput);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Tool execution failed: {$toolName}", [
                    'error' => $e->getMessage(),
                    'input' => $toolInput,
                ]);
                $result = ToolResult::error("Tool execution failed: {$e->getMessage()}");
            }

            // Record the tool call
            $context->recordToolCall(
                $toolName,
                $toolInput,
                $result->getContent(),
                $result->isError()
            );

            // Fire tool execution callback
            if ($this->onToolExecution !== null) {
                ($this->onToolExecution)($toolName, $toolInput, $result);
            }

            $results[] = $result->toApiFormat($toolId);
        }

        return $results;
    }

    /**
     * Check if response content contains any tool_use blocks.
     *
     * This should be used instead of relying on stop_reason to determine
     * whether tool results need to be added. The API requires every tool_use
     * block to have a corresponding tool_result, regardless of the stop reason.
     *
     * @param array<mixed> $content Response content blocks
     */
    private function contentHasToolUse(array $content): bool
    {
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if ($type === 'text' && isset($block['text'])) {
                $texts[] = $block['text'];
            }
        }

        return implode("\n", $texts);
    }
}
