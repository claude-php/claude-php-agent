<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\CalculatorTool;
use PHPUnit\Framework\TestCase;

class CalculatorToolTest extends TestCase
{
    public function testCreateWithDefaults(): void
    {
        $tool = CalculatorTool::create();

        $this->assertEquals('calculate', $tool->getName());
        $this->assertStringContainsString('mathematical', strtolower($tool->getDescription()));
    }

    public function testCreateWithConfig(): void
    {
        $tool = CalculatorTool::create([
            'allow_functions' => true,
            'max_precision' => 5,
        ]);

        $this->assertEquals('calculate', $tool->getName());
    }

    public function testSimpleAddition(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '5 + 3']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(8, $data['result']);
        $this->assertEquals('5 + 3', $data['expression']);
    }

    public function testSimpleSubtraction(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '10 - 4']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(6, $data['result']);
    }

    public function testSimpleMultiplication(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '7 * 8']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(56, $data['result']);
    }

    public function testSimpleDivision(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '20 / 4']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(5, $data['result']);
    }

    public function testComplexExpression(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '(10 + 5) * 2 - 8']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(22, $data['result']);
    }

    public function testDecimalCalculation(): void
    {
        $tool = CalculatorTool::create(['max_precision' => 2]);
        $result = $tool->execute(['expression' => '10.5 * 2.5']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(26.25, $data['result']);
    }

    public function testPrecisionRounding(): void
    {
        $tool = CalculatorTool::create(['max_precision' => 2]);
        $result = $tool->execute(['expression' => '1 / 3']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(0.33, $data['result']);
    }

    public function testEmptyExpression(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('empty', strtolower($result->getContent()));
    }

    public function testInvalidCharacters(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => 'eval("malicious")']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('invalid', strtolower($result->getContent()));
    }

    public function testDangerousFunction(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => 'exec("ls")']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('invalid', strtolower($result->getContent()));
    }

    public function testParenthesesBalancing(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '((5 + 3) * 2) / 4']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(4, $data['result']);
    }

    public function testNegativeNumbers(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '-5 + 10']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(5, $data['result']);
    }

    public function testLargeNumbers(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '1234567 * 89']);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(109876463, $data['result']);
    }

    public function testDivisionByZero(): void
    {
        $tool = CalculatorTool::create();
        $result = $tool->execute(['expression' => '10 / 0']);

        // Should handle gracefully (either error or infinity)
        $this->assertInstanceOf(\ClaudeAgents\Contracts\ToolResultInterface::class, $result);
    }

    public function testInputSchema(): void
    {
        $tool = CalculatorTool::create();
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('expression', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['expression']['type']);
        $this->assertContains('expression', $schema['required']);
    }

    public function testToolDefinition(): void
    {
        $tool = CalculatorTool::create();
        $definition = $tool->toDefinition();

        $this->assertArrayHasKey('name', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('input_schema', $definition);
        $this->assertEquals('calculate', $definition['name']);
    }
}
