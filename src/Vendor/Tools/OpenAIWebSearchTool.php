<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;

/**
 * OpenAI web search tool.
 *
 * Performs real-time web searches using OpenAI's Responses API
 * with the built-in web_search tool. Returns results with citations.
 */
class OpenAIWebSearchTool implements ToolInterface
{
    public function __construct(
        private readonly OpenAIAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'openai_web_search';
    }

    public function getDescription(): string
    {
        return 'Search the web in real-time using OpenAI. Returns up-to-date '
            . 'information with citations. Use for current events, recent data, '
            . 'or fact-checking.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query',
                ],
                'context' => [
                    'type' => 'string',
                    'description' => 'Optional additional context to guide the search',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        $query = $input['query'] ?? '';

        if ($query === '') {
            return ToolResult::error('The query parameter is required.');
        }

        try {
            $result = $this->adapter->webSearch(
                $query,
                $input['context'] ?? null,
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("OpenAI web search error: {$e->getMessage()}");
        }
    }

    public function toDefinition(): array
    {
        $schema = $this->getInputSchema();
        $schema['properties'] = (object) $schema['properties'];

        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'input_schema' => $schema,
        ];
    }
}
