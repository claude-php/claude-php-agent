<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function testCreateTool(): void
    {
        $tool = Tool::create('calculator')
            ->description('Performs mathematical calculations');

        $this->assertEquals('calculator', $tool->getName());
        $this->assertEquals('Performs mathematical calculations', $tool->getDescription());
    }

    public function testStringParameter(): void
    {
        $tool = Tool::create('search')
            ->stringParam('query', 'Search query', true);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['query']['type']);
        $this->assertContains('query', $schema['required']);
    }

    public function testNumberParameter(): void
    {
        $tool = Tool::create('multiply')
            ->numberParam('a', 'First number', true, 0, 100)
            ->numberParam('b', 'Second number', true);

        $schema = $tool->getInputSchema();

        $this->assertEquals('number', $schema['properties']['a']['type']);
        $this->assertEquals(0, $schema['properties']['a']['minimum']);
        $this->assertEquals(100, $schema['properties']['a']['maximum']);
        $this->assertCount(2, $schema['required']);
    }

    public function testBooleanParameter(): void
    {
        $tool = Tool::create('toggle')
            ->booleanParam('enabled', 'Whether to enable', true);

        $schema = $tool->getInputSchema();

        $this->assertEquals('boolean', $schema['properties']['enabled']['type']);
    }

    public function testArrayParameter(): void
    {
        $tool = Tool::create('process')
            ->arrayParam('items', 'List of items', true, ['type' => 'string']);

        $schema = $tool->getInputSchema();

        $this->assertEquals('array', $schema['properties']['items']['type']);
        $this->assertArrayHasKey('items', $schema['properties']['items']);
        $this->assertEquals('string', $schema['properties']['items']['items']['type']);
    }

    public function testOptionalParameters(): void
    {
        $tool = Tool::create('search')
            ->stringParam('query', 'Query', true)
            ->stringParam('filter', 'Filter', false);

        $schema = $tool->getInputSchema();

        $this->assertContains('query', $schema['required']);
        $this->assertNotContains('filter', $schema['required']);
    }

    public function testEnumParameter(): void
    {
        $tool = Tool::create('sort')
            ->stringParam('order', 'Sort order', true, ['asc', 'desc']);

        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('enum', $schema['properties']['order']);
        $this->assertEquals(['asc', 'desc'], $schema['properties']['order']['enum']);
    }

    public function testHandlerExecution(): void
    {
        $tool = Tool::create('add')
            ->numberParam('a', 'First number')
            ->numberParam('b', 'Second number')
            ->handler(function (array $input): int {
                return $input['a'] + $input['b'];
            });

        $result = $tool->execute(['a' => 5, 'b' => 3]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('8', $result->getContent());
    }

    public function testHandlerWithStringReturn(): void
    {
        $tool = Tool::create('greet')
            ->stringParam('name', 'Name')
            ->handler(function (array $input): string {
                return "Hello, {$input['name']}!";
            });

        $result = $tool->execute(['name' => 'World']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello, World!', $result->getContent());
    }

    public function testHandlerWithArrayReturn(): void
    {
        $tool = Tool::create('analyze')
            ->stringParam('text', 'Text')
            ->handler(function (array $input): array {
                return [
                    'length' => strlen($input['text']),
                    'words' => str_word_count($input['text']),
                ];
            });

        $result = $tool->execute(['text' => 'Hello world']);

        $this->assertTrue($result->isSuccess());
        $content = json_decode($result->getContent(), true);
        $this->assertEquals(11, $content['length']);
        $this->assertEquals(2, $content['words']);
    }

    public function testHandlerWithToolResultReturn(): void
    {
        $tool = Tool::create('custom')
            ->handler(function (): ToolResult {
                return ToolResult::success('Custom result');
            });

        $result = $tool->execute([]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Custom result', $result->getContent());
    }

    public function testHandlerException(): void
    {
        $tool = Tool::create('failing')
            ->handler(function (): void {
                throw new \RuntimeException('Test error');
            });

        $result = $tool->execute([]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Test error', $result->getContent());
    }

    public function testToolWithoutHandler(): void
    {
        $tool = Tool::create('empty');

        $result = $tool->execute([]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('no handler', $result->getContent());
    }

    public function testToDefinition(): void
    {
        $tool = Tool::create('test')
            ->description('Test tool')
            ->stringParam('input', 'Input param');

        $definition = $tool->toDefinition();

        $this->assertArrayHasKey('name', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('input_schema', $definition);
        $this->assertEquals('test', $definition['name']);
        $this->assertEquals('Test tool', $definition['description']);
    }

    public function testFromDefinition(): void
    {
        $definition = [
            'name' => 'search',
            'description' => 'Search tool',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                    ],
                ],
                'required' => ['query'],
            ],
        ];

        $handler = fn (array $input): string => "Searching for: {$input['query']}";

        $tool = Tool::fromDefinition($definition, $handler);

        $this->assertEquals('search', $tool->getName());
        $this->assertEquals('Search tool', $tool->getDescription());
        $this->assertArrayHasKey('query', $tool->getInputSchema()['properties']);

        $result = $tool->execute(['query' => 'test']);
        $this->assertEquals('Searching for: test', $result->getContent());
    }

    public function testMultipleRequiredParameters(): void
    {
        $tool = Tool::create('combine')
            ->stringParam('first', 'First', true)
            ->stringParam('second', 'Second', true)
            ->stringParam('third', 'Third', true);

        $schema = $tool->getInputSchema();

        $this->assertCount(3, $schema['required']);
        $this->assertContains('first', $schema['required']);
        $this->assertContains('second', $schema['required']);
        $this->assertContains('third', $schema['required']);
    }

    public function testGenericParameter(): void
    {
        $tool = Tool::create('custom')
            ->parameter('custom_param', 'object', 'Custom parameter', true, [
                'properties' => [
                    'nested' => ['type' => 'string'],
                ],
            ]);

        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['properties']['custom_param']['type']);
        $this->assertArrayHasKey('properties', $schema['properties']['custom_param']);
    }

    public function testFluentAPI(): void
    {
        $tool = Tool::create('fluent')
            ->description('Test fluent API')
            ->stringParam('name', 'Name')
            ->numberParam('age', 'Age')
            ->booleanParam('active', 'Active')
            ->handler(fn (): string => 'success');

        $this->assertEquals('fluent', $tool->getName());
        $this->assertEquals('Test fluent API', $tool->getDescription());
        $this->assertCount(3, $tool->getInputSchema()['properties']);

        $result = $tool->execute(['name' => 'test', 'age' => 25, 'active' => true]);
        $this->assertTrue($result->isSuccess());
    }
}
