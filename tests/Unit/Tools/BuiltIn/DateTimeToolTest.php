<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\DateTimeTool;
use PHPUnit\Framework\TestCase;

class DateTimeToolTest extends TestCase
{
    public function testCreateWithDefaults(): void
    {
        $tool = DateTimeTool::create();

        $this->assertEquals('datetime', $tool->getName());
        $this->assertStringContainsString('date', strtolower($tool->getDescription()));
    }

    public function testCreateWithConfig(): void
    {
        $tool = DateTimeTool::create([
            'default_timezone' => 'America/New_York',
            'default_format' => 'Y-m-d',
        ]);

        $this->assertEquals('datetime', $tool->getName());
    }

    public function testNowOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute(['operation' => 'now']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('datetime', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('timezone', $data);
    }

    public function testNowWithCustomTimezone(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'now',
            'timezone' => 'America/New_York',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals('America/New_York', $data['timezone']);
    }

    public function testFormatOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'format',
            'date' => '2024-01-15',
            'format' => 'Y-m-d',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('formatted', $data);
        $this->assertEquals('2024-01-15', $data['formatted']);
    }

    public function testParseOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'parse',
            'date' => '2024-01-15 14:30:00',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(2024, $data['year']);
        $this->assertEquals(1, $data['month']);
        $this->assertEquals(15, $data['day']);
        $this->assertEquals(14, $data['hour']);
        $this->assertEquals(30, $data['minute']);
        $this->assertArrayHasKey('day_of_week', $data);
    }

    public function testAddOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'add',
            'date' => '2024-01-01',
            'interval' => '+1 day',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('result', $data);
        $this->assertStringContainsString('2024-01-02', $data['result']);
    }

    public function testSubtractOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'subtract',
            'date' => '2024-01-15',
            'interval' => '5 days',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('result', $data);
        $this->assertStringContainsString('2024-01-10', $data['result']);
    }

    public function testDiffOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'diff',
            'date' => '2024-01-01',
            'date2' => '2024-01-31',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('days', $data);
        $this->assertArrayHasKey('total_days', $data);
        $this->assertEquals(30, $data['total_days']);
    }

    public function testFormatWithoutDate(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'format',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testAddWithoutInterval(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'add',
            'date' => '2024-01-01',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testDiffWithoutSecondDate(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'diff',
            'date' => '2024-01-01',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testInvalidOperation(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'invalid_operation',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('unknown', strtolower($result->getContent()));
    }

    public function testInvalidDate(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'format',
            'date' => 'not-a-valid-date',
        ]);

        $this->assertTrue($result->isError());
    }

    public function testInvalidTimezone(): void
    {
        $tool = DateTimeTool::create();
        $result = $tool->execute([
            'operation' => 'now',
            'timezone' => 'Invalid/Timezone',
        ]);

        $this->assertTrue($result->isError());
    }

    public function testInputSchema(): void
    {
        $tool = DateTimeTool::create();
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('operation', $schema['properties']);
        $this->assertContains('operation', $schema['required']);
    }

    public function testToolDefinition(): void
    {
        $tool = DateTimeTool::create();
        $definition = $tool->toDefinition();

        $this->assertArrayHasKey('name', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('input_schema', $definition);
        $this->assertEquals('datetime', $definition['name']);
    }
}
