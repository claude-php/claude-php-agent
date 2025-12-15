<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
    }

    public function testRegisterTool(): void
    {
        $tool = Tool::create('calculator')
            ->description('Performs calculations');

        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('calculator'));
        $this->assertEquals(1, $this->registry->count());
    }

    public function testRegisterMany(): void
    {
        $tools = [
            Tool::create('tool1'),
            Tool::create('tool2'),
            Tool::create('tool3'),
        ];

        $this->registry->registerMany($tools);

        $this->assertEquals(3, $this->registry->count());
        $this->assertTrue($this->registry->has('tool1'));
        $this->assertTrue($this->registry->has('tool2'));
        $this->assertTrue($this->registry->has('tool3'));
    }

    public function testGetTool(): void
    {
        $tool = Tool::create('search')
            ->description('Search tool');

        $this->registry->register($tool);

        $retrieved = $this->registry->get('search');

        $this->assertNotNull($retrieved);
        $this->assertEquals('search', $retrieved->getName());
        $this->assertEquals('Search tool', $retrieved->getDescription());
    }

    public function testGetNonExistentTool(): void
    {
        $result = $this->registry->get('nonexistent');

        $this->assertNull($result);
    }

    public function testHasTool(): void
    {
        $tool = Tool::create('exists');
        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('exists'));
        $this->assertFalse($this->registry->has('does_not_exist'));
    }

    public function testRemoveTool(): void
    {
        $tool = Tool::create('temp');
        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('temp'));

        $this->registry->remove('temp');

        $this->assertFalse($this->registry->has('temp'));
        $this->assertEquals(0, $this->registry->count());
    }

    public function testRemoveNonExistentTool(): void
    {
        // Should not throw an exception
        $this->registry->remove('nonexistent');
        $this->assertEquals(0, $this->registry->count());
    }

    public function testAllTools(): void
    {
        $tools = [
            Tool::create('tool1'),
            Tool::create('tool2'),
            Tool::create('tool3'),
        ];

        $this->registry->registerMany($tools);

        $all = $this->registry->all();

        $this->assertCount(3, $all);
        $this->assertContainsOnlyInstancesOf(Tool::class, $all);
    }

    public function testNamesReturnsToolNames(): void
    {
        $this->registry->registerMany([
            Tool::create('alpha'),
            Tool::create('beta'),
            Tool::create('gamma'),
        ]);

        $names = $this->registry->names();

        $this->assertCount(3, $names);
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
        $this->assertContains('gamma', $names);
    }

    public function testToDefinitions(): void
    {
        $tools = [
            Tool::create('tool1')->description('First tool'),
            Tool::create('tool2')->description('Second tool'),
        ];

        $this->registry->registerMany($tools);

        $definitions = $this->registry->toDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertArrayHasKey('name', $definitions[0]);
        $this->assertArrayHasKey('description', $definitions[0]);
        $this->assertArrayHasKey('input_schema', $definitions[0]);
    }

    public function testExecuteExistingTool(): void
    {
        $tool = Tool::create('add')
            ->numberParam('a', 'First number')
            ->numberParam('b', 'Second number')
            ->handler(fn (array $input): int => $input['a'] + $input['b']);

        $this->registry->register($tool);

        $result = $this->registry->execute('add', ['a' => 5, 'b' => 3]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('8', $result->getContent());
    }

    public function testExecuteNonExistentTool(): void
    {
        $result = $this->registry->execute('unknown', []);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Unknown tool', $result->getContent());
    }

    public function testExecuteToolWithError(): void
    {
        $tool = Tool::create('failing')
            ->handler(function (): void {
                throw new \RuntimeException('Intentional error');
            });

        $this->registry->register($tool);

        $result = $this->registry->execute('failing', []);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Intentional error', $result->getContent());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register(Tool::create('tool1'));
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register(Tool::create('tool2'));
        $this->assertEquals(2, $this->registry->count());

        $this->registry->remove('tool1');
        $this->assertEquals(1, $this->registry->count());
    }

    public function testClear(): void
    {
        $this->registry->registerMany([
            Tool::create('tool1'),
            Tool::create('tool2'),
            Tool::create('tool3'),
        ]);

        $this->assertEquals(3, $this->registry->count());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count());
        $this->assertEmpty($this->registry->all());
    }

    public function testRegisterDuplicateToolOverwrites(): void
    {
        $tool1 = Tool::create('duplicate')->description('First version');
        $tool2 = Tool::create('duplicate')->description('Second version');

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $this->assertEquals(1, $this->registry->count());

        $retrieved = $this->registry->get('duplicate');
        $this->assertEquals('Second version', $retrieved->getDescription());
    }

    public function testFluentRegistration(): void
    {
        $result = $this->registry
            ->register(Tool::create('tool1'))
            ->register(Tool::create('tool2'))
            ->register(Tool::create('tool3'));

        $this->assertInstanceOf(ToolRegistry::class, $result);
        $this->assertEquals(3, $this->registry->count());
    }

    public function testEmptyRegistry(): void
    {
        $this->assertEquals(0, $this->registry->count());
        $this->assertEmpty($this->registry->all());
        $this->assertEmpty($this->registry->names());
        $this->assertEmpty($this->registry->toDefinitions());
    }
}
