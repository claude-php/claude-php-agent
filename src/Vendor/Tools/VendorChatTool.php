<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Contracts\VendorAdapterInterface;

/**
 * Generic cross-vendor chat tool.
 *
 * Allows Claude to delegate a prompt to any registered vendor
 * model (OpenAI, Gemini, etc.) during agent execution.
 */
class VendorChatTool implements ToolInterface
{
    /**
     * @var array<string, VendorAdapterInterface> Vendor name => adapter
     */
    private array $adapters;

    /**
     * @param array<string, VendorAdapterInterface> $adapters
     */
    public function __construct(array $adapters)
    {
        $this->adapters = $adapters;
    }

    public function getName(): string
    {
        return 'vendor_chat';
    }

    public function getDescription(): string
    {
        $vendors = implode(', ', array_keys($this->adapters));

        return "Send a prompt to another AI model from a different vendor ({$vendors}). "
            . 'Useful for getting a second opinion, leveraging vendor-specific strengths, '
            . 'or cross-validating responses.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'The prompt to send to the vendor model',
                ],
                'vendor' => [
                    'type' => 'string',
                    'description' => 'The vendor to use: ' . implode(', ', array_keys($this->adapters)),
                    'enum' => array_keys($this->adapters),
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Optional specific model ID to use (defaults to vendor\'s best model)',
                ],
                'system' => [
                    'type' => 'string',
                    'description' => 'Optional system prompt for the vendor model',
                ],
            ],
            'required' => ['prompt', 'vendor'],
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        $vendor = $input['vendor'] ?? '';
        $prompt = $input['prompt'] ?? '';

        if (! isset($this->adapters[$vendor])) {
            return ToolResult::error(
                "Vendor '{$vendor}' is not available. Available vendors: "
                . implode(', ', array_keys($this->adapters))
            );
        }

        if ($prompt === '') {
            return ToolResult::error('The prompt parameter is required.');
        }

        try {
            $options = [];
            if (isset($input['model'])) {
                $options['model'] = $input['model'];
            }
            if (isset($input['system'])) {
                $options['system'] = $input['system'];
            }

            $result = $this->adapters[$vendor]->chat($prompt, $options);

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("Vendor chat error ({$vendor}): {$e->getMessage()}");
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
