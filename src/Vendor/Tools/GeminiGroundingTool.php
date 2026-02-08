<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\GeminiAdapter;

/**
 * Gemini Google Search grounding tool.
 *
 * Returns responses grounded in real-time Google Search results
 * with citations. Useful for fact-checking and current information.
 */
class GeminiGroundingTool implements ToolInterface
{
    public function __construct(
        private readonly GeminiAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'gemini_grounding';
    }

    public function getDescription(): string
    {
        return 'Get a response grounded in Google Search results using Gemini. '
            . 'Returns factual, cited information based on real-time web data. '
            . 'Use for fact verification, current events, or research.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The question or topic to research with Google Search grounding',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Gemini model to use (default: gemini-2.5-flash)',
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
            $result = $this->adapter->groundedSearch(
                $query,
                $input['model'] ?? null,
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("Gemini grounding error: {$e->getMessage()}");
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
