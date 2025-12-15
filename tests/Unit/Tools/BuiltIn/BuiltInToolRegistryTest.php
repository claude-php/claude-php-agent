<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\BuiltInToolRegistry;
use PHPUnit\Framework\TestCase;

class BuiltInToolRegistryTest extends TestCase
{
    public function testCreateWithAll(): void
    {
        $registry = BuiltInToolRegistry::createWithAll();

        $this->assertEquals(5, $registry->count()); // calculator, datetime, http, filesystem, regex
        $this->assertTrue($registry->has('calculate'));
        $this->assertTrue($registry->has('datetime'));
        $this->assertTrue($registry->has('http_request'));
        $this->assertTrue($registry->has('filesystem'));
        $this->assertTrue($registry->has('regex'));
    }

    public function testCreateWithAllAndConfig(): void
    {
        $registry = BuiltInToolRegistry::createWithAll([
            'calculator' => ['allow_functions' => true],
            'datetime' => ['default_timezone' => 'America/New_York'],
            'http' => ['timeout' => 60],
        ]);

        $this->assertEquals(5, $registry->count()); // calculator, datetime, http, filesystem, regex
    }

    public function testCreateWithAllExcludingCalculator(): void
    {
        $registry = BuiltInToolRegistry::createWithAll([
            'calculator' => false,
        ]);

        $this->assertEquals(4, $registry->count()); // datetime, http, filesystem, regex
        $this->assertFalse($registry->has('calculate'));
        $this->assertTrue($registry->has('datetime'));
        $this->assertTrue($registry->has('http_request'));
        $this->assertTrue($registry->has('filesystem'));
        $this->assertTrue($registry->has('regex'));
    }

    public function testCreateWithAllExcludingDateTime(): void
    {
        $registry = BuiltInToolRegistry::createWithAll([
            'datetime' => false,
        ]);

        $this->assertEquals(4, $registry->count()); // calculator, http, filesystem, regex
        $this->assertTrue($registry->has('calculate'));
        $this->assertFalse($registry->has('datetime'));
        $this->assertTrue($registry->has('http_request'));
        $this->assertTrue($registry->has('filesystem'));
        $this->assertTrue($registry->has('regex'));
    }

    public function testCreateWithAllExcludingHTTP(): void
    {
        $registry = BuiltInToolRegistry::createWithAll([
            'http' => false,
        ]);

        $this->assertEquals(4, $registry->count()); // calculator, datetime, filesystem, regex
        $this->assertTrue($registry->has('calculate'));
        $this->assertTrue($registry->has('datetime'));
        $this->assertFalse($registry->has('http_request'));
        $this->assertTrue($registry->has('filesystem'));
        $this->assertTrue($registry->has('regex'));
    }

    public function testWithCalculator(): void
    {
        $registry = BuiltInToolRegistry::withCalculator();

        $this->assertEquals(1, $registry->count());
        $this->assertTrue($registry->has('calculate'));
        $this->assertFalse($registry->has('datetime'));
        $this->assertFalse($registry->has('http_request'));
    }

    public function testWithCalculatorAndConfig(): void
    {
        $registry = BuiltInToolRegistry::withCalculator([
            'allow_functions' => true,
        ]);

        $this->assertEquals(1, $registry->count());
        $this->assertTrue($registry->has('calculate'));
    }

    public function testWithDateTime(): void
    {
        $registry = BuiltInToolRegistry::withDateTime();

        $this->assertEquals(1, $registry->count());
        $this->assertFalse($registry->has('calculate'));
        $this->assertTrue($registry->has('datetime'));
        $this->assertFalse($registry->has('http_request'));
    }

    public function testWithDateTimeAndConfig(): void
    {
        $registry = BuiltInToolRegistry::withDateTime([
            'default_timezone' => 'Europe/London',
        ]);

        $this->assertEquals(1, $registry->count());
        $this->assertTrue($registry->has('datetime'));
    }

    public function testWithHTTP(): void
    {
        $registry = BuiltInToolRegistry::withHTTP();

        $this->assertEquals(1, $registry->count());
        $this->assertFalse($registry->has('calculate'));
        $this->assertFalse($registry->has('datetime'));
        $this->assertTrue($registry->has('http_request'));
    }

    public function testWithHTTPAndConfig(): void
    {
        $registry = BuiltInToolRegistry::withHTTP([
            'timeout' => 45,
        ]);

        $this->assertEquals(1, $registry->count());
        $this->assertTrue($registry->has('http_request'));
    }

    public function testWithToolsSubset(): void
    {
        $registry = BuiltInToolRegistry::withTools(['calculator', 'datetime']);

        $this->assertEquals(2, $registry->count());
        $this->assertTrue($registry->has('calculate'));
        $this->assertTrue($registry->has('datetime'));
        $this->assertFalse($registry->has('http_request'));
    }

    public function testWithToolsAndConfig(): void
    {
        $registry = BuiltInToolRegistry::withTools(
            ['calculator', 'http'],
            [
                'calculator' => ['allow_functions' => true],
                'http' => ['timeout' => 20],
            ]
        );

        $this->assertEquals(2, $registry->count());
        $this->assertTrue($registry->has('calculate'));
        $this->assertFalse($registry->has('datetime'));
        $this->assertTrue($registry->has('http_request'));
    }

    public function testWithToolsInvalidToolName(): void
    {
        $registry = BuiltInToolRegistry::withTools(['invalid_tool']);

        $this->assertEquals(0, $registry->count());
    }

    public function testWithToolsEmptyArray(): void
    {
        $registry = BuiltInToolRegistry::withTools([]);

        $this->assertEquals(0, $registry->count());
    }

    public function testAllToolsCanBeRetrieved(): void
    {
        $registry = BuiltInToolRegistry::createWithAll();

        $tools = $registry->all();

        $this->assertCount(5, $tools); // calculator, datetime, http, filesystem, regex
        $this->assertContainsOnlyInstancesOf(\ClaudeAgents\Contracts\ToolInterface::class, $tools);
    }

    public function testToolNamesAreCorrect(): void
    {
        $registry = BuiltInToolRegistry::createWithAll();

        $names = $registry->names();

        $this->assertCount(5, $names); // calculator, datetime, http, filesystem, regex
        $this->assertContains('calculate', $names);
        $this->assertContains('datetime', $names);
        $this->assertContains('http_request', $names);
        $this->assertContains('filesystem', $names);
        $this->assertContains('regex', $names);
    }

    public function testToDefinitions(): void
    {
        $registry = BuiltInToolRegistry::createWithAll();

        $definitions = $registry->toDefinitions();

        $this->assertCount(5, $definitions); // calculator, datetime, http, filesystem, regex
        foreach ($definitions as $definition) {
            $this->assertArrayHasKey('name', $definition);
            $this->assertArrayHasKey('description', $definition);
            $this->assertArrayHasKey('input_schema', $definition);
        }
    }

    public function testExecuteCalculator(): void
    {
        $registry = BuiltInToolRegistry::withCalculator();

        $result = $registry->execute('calculate', ['expression' => '5 + 3']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(8, $data['result']);
    }

    public function testExecuteDateTime(): void
    {
        $registry = BuiltInToolRegistry::withDateTime();

        $result = $registry->execute('datetime', [
            'operation' => 'format',
            'date' => '2024-01-15',
            'format' => 'Y-m-d',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteNonExistentTool(): void
    {
        $registry = BuiltInToolRegistry::withCalculator();

        $result = $registry->execute('nonexistent', []);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Unknown tool', $result->getContent());
    }

    public function testRegistryIsExtendable(): void
    {
        $registry = BuiltInToolRegistry::withCalculator();

        // Should be able to register more tools
        $this->assertEquals(1, $registry->count());

        // Registry inherits from ToolRegistry, so it has all its methods
        $this->assertInstanceOf(\ClaudeAgents\Tools\ToolRegistry::class, $registry);
    }
}
