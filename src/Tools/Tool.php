<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;

/**
 * Represents a tool that can be used by an agent.
 *
 * Provides a fluent API for building tool definitions.
 */
class Tool implements ToolInterface
{
    private string $name;
    private string $description = '';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $parameters = [];

    /**
     * @var array<string>
     */
    private array $required = [];

    /**
     * @var callable|null
     */
    private $handler = null;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a new tool with the given name.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the tool description.
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Add a parameter to the tool.
     *
     * @param string $name Parameter name
     * @param string $type Parameter type (string, number, boolean, array, object)
     * @param string $description Parameter description
     * @param bool $required Whether the parameter is required
     * @param array<string, mixed> $extra Additional schema properties (enum, default, etc.)
     */
    public function parameter(
        string $name,
        string $type,
        string $description,
        bool $required = true,
        array $extra = [],
    ): self {
        $this->parameters[$name] = array_merge([
            'type' => $type,
            'description' => $description,
        ], $extra);

        if ($required) {
            $this->required[] = $name;
        }

        return $this;
    }

    /**
     * Add a string parameter.
     */
    public function stringParam(
        string $name,
        string $description,
        bool $required = true,
        ?array $enum = null,
    ): self {
        $extra = [];
        if ($enum !== null) {
            $extra['enum'] = $enum;
        }

        return $this->parameter($name, 'string', $description, $required, $extra);
    }

    /**
     * Add a number parameter.
     */
    public function numberParam(
        string $name,
        string $description,
        bool $required = true,
        ?float $minimum = null,
        ?float $maximum = null,
    ): self {
        $extra = [];
        if ($minimum !== null) {
            $extra['minimum'] = $minimum;
        }
        if ($maximum !== null) {
            $extra['maximum'] = $maximum;
        }

        return $this->parameter($name, 'number', $description, $required, $extra);
    }

    /**
     * Add a boolean parameter.
     */
    public function booleanParam(
        string $name,
        string $description,
        bool $required = true,
    ): self {
        return $this->parameter($name, 'boolean', $description, $required);
    }

    /**
     * Add an array parameter.
     *
     * @param array<string, mixed>|null $items Schema for array items
     */
    public function arrayParam(
        string $name,
        string $description,
        bool $required = true,
        ?array $items = null,
    ): self {
        $extra = [];
        if ($items !== null) {
            $extra['items'] = $items;
        }

        return $this->parameter($name, 'array', $description, $required, $extra);
    }

    /**
     * Mark parameters as required.
     *
     * @param string ...$names Parameter names
     */
    public function required(string ...$names): self
    {
        $this->required = array_unique(array_merge($this->required, $names));

        return $this;
    }

    /**
     * Set the tool handler function.
     *
     * @param callable $handler Function that receives array input and returns string|array
     */
    public function handler(callable $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->parameters,
            'required' => array_values(array_unique($this->required)),
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        if ($this->handler === null) {
            return ToolResult::error("Tool '{$this->name}' has no handler defined");
        }

        try {
            $result = ($this->handler)($input);

            if ($result instanceof ToolResultInterface) {
                return $result;
            }

            return ToolResult::success(
                is_string($result) ? $result : json_encode($result)
            );
        } catch (\Throwable $e) {
            return ToolResult::fromException($e);
        }
    }

    public function toDefinition(): array
    {
        $schema = $this->getInputSchema();

        // Cast properties to object to ensure json_encode produces {} not []
        // when properties is empty. The API requires "properties" to be a JSON object.
        $schema['properties'] = (object) $schema['properties'];

        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $schema,
        ];
    }

    /**
     * Create a tool from a definition array.
     *
     * @param array<string, mixed> $definition
     * @param callable|null $handler
     */
    public static function fromDefinition(array $definition, ?callable $handler = null): self
    {
        $tool = new self($definition['name']);
        $tool->description = $definition['description'] ?? '';

        if (isset($definition['input_schema']['properties'])) {
            $tool->parameters = $definition['input_schema']['properties'];
        }

        if (isset($definition['input_schema']['required'])) {
            $tool->required = $definition['input_schema']['required'];
        }

        if ($handler !== null) {
            $tool->handler = $handler;
        }

        return $tool;
    }

    /**
     * Create a tool from a chain.
     *
     * Wraps a chain as a tool that can be used by agents.
     *
     * @param \ClaudeAgents\Chains\Contracts\ChainInterface $chain The chain to wrap
     * @param string $name Optional tool name (defaults to 'chain')
     * @param string $description Optional description
     */
    public static function fromChain(
        \ClaudeAgents\Chains\Contracts\ChainInterface $chain,
        string $name = 'chain',
        string $description = 'Executes a chain'
    ): self {
        $tool = new self($name);
        $tool->description = $description;

        // Get schema from the chain
        $inputSchema = $chain->getInputSchema();
        if (isset($inputSchema['properties'])) {
            $tool->parameters = $inputSchema['properties'];
        }
        if (isset($inputSchema['required'])) {
            $tool->required = $inputSchema['required'];
        }

        // Set handler to execute the chain
        $tool->handler = function (array $input) use ($chain): string {
            try {
                $result = $chain->invoke($input);

                return json_encode($result);
            } catch (\Throwable $e) {
                return json_encode([
                    'error' => $e->getMessage(),
                    'type' => class_basename($e),
                ]);
            }
        };

        return $tool;
    }
}
