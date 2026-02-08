<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\GeminiAdapter;

/**
 * Gemini code execution tool.
 *
 * Generates and executes Python code server-side using Gemini's
 * built-in code execution sandbox (30-second timeout).
 */
class GeminiCodeExecTool implements ToolInterface
{
    public function __construct(
        private readonly GeminiAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'gemini_code_execution';
    }

    public function getDescription(): string
    {
        return 'Generate and execute Python code server-side using Gemini. '
            . 'The model writes code, runs it in a sandbox, and returns the output. '
            . 'Use for calculations, data processing, or solving problems with code.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Description of what code to generate and execute (Python only)',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Gemini model to use (default: gemini-2.5-flash)',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        $prompt = $input['prompt'] ?? '';

        if ($prompt === '') {
            return ToolResult::error('The prompt parameter is required.');
        }

        try {
            $result = $this->adapter->codeExecution(
                $prompt,
                $input['model'] ?? null,
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("Gemini code execution error: {$e->getMessage()}");
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
