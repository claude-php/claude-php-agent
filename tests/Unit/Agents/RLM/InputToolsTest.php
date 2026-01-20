<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents\RLM;

use ClaudeAgents\Agents\RLM\InputTools;
use ClaudeAgents\Agents\RLM\REPLContext;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use PHPUnit\Framework\TestCase;

class InputToolsTest extends TestCase
{
    private string $sampleInput;
    private REPLContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleInput = "Line 1: Hello world\nLine 2: This is a test\nLine 3: More content here\nLine 4: Final line";
        $this->context = new REPLContext($this->sampleInput);
    }

    public function testPeekToolCreation(): void
    {
        $tool = InputTools::peek($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('peek_input', $tool->getName());
        $this->assertStringContainsString('character position', $tool->getDescription());
    }

    public function testPeekToolExecution(): void
    {
        $tool = InputTools::peek($this->context);
        
        $result = $tool->execute(['start' => 0, 'length' => 10]);
        
        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertEquals('Line 1: He', $content['content']);
        $this->assertEquals(0, $content['start']);
        $this->assertTrue($content['has_more']);
    }

    public function testPeekToolLimitsLength(): void
    {
        $tool = InputTools::peek($this->context);
        
        // Request more than max
        $result = $tool->execute(['start' => 0, 'length' => 50000]);
        
        $this->assertFalse($result->isError());
        $content = json_decode($result->getContent(), true);
        $this->assertLessThanOrEqual(10000, $content['length']);
    }

    public function testSliceToolCreation(): void
    {
        $tool = InputTools::slice($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('slice_input', $tool->getName());
        $this->assertStringContainsString('lines', $tool->getDescription());
    }

    public function testSliceToolExecution(): void
    {
        $tool = InputTools::slice($this->context);
        
        $result = $tool->execute(['start_line' => 2, 'end_line' => 3]);
        
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertStringContainsString('Line 2', $content['content']);
        $this->assertStringContainsString('Line 3', $content['content']);
        $this->assertEquals(2, $content['lines_returned']);
    }

    public function testSliceToolLimitsRange(): void
    {
        // Create a context with many lines
        $largeInput = implode("\n", array_map(fn($i) => "Line $i", range(1, 500)));
        $context = new REPLContext($largeInput);
        $tool = InputTools::slice($context);
        
        // Request more than max
        $result = $tool->execute(['start_line' => 1, 'end_line' => 500]);
        
        $this->assertFalse($result->isError());
        $content = json_decode($result->getContent(), true);
        $this->assertLessThanOrEqual(200, $content['lines_returned']);
    }

    public function testSearchToolCreation(): void
    {
        $tool = InputTools::search($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('search_input', $tool->getName());
        $this->assertStringContainsString('regular expression', $tool->getDescription());
    }

    public function testSearchToolExecution(): void
    {
        $tool = InputTools::search($this->context);
        
        $result = $tool->execute(['pattern' => '/test/i']);
        
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertEquals(1, $content['match_count']);
        $this->assertFalse($content['truncated']);
    }

    public function testSearchToolWithInvalidPattern(): void
    {
        $tool = InputTools::search($this->context);
        
        $result = $tool->execute(['pattern' => '/invalid[/']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Invalid regular expression', $result->getContent());
    }

    public function testSearchToolWithEmptyPattern(): void
    {
        $tool = InputTools::search($this->context);
        
        $result = $tool->execute(['pattern' => '']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', $result->getContent());
    }

    public function testSearchToolWithContextLines(): void
    {
        $tool = InputTools::search($this->context);
        
        $result = $tool->execute(['pattern' => '/test/i', 'context_lines' => 1]);
        
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertNotEmpty($content['matches'][0]['context']);
    }

    public function testInfoToolCreation(): void
    {
        $tool = InputTools::info($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('get_input_info', $tool->getName());
    }

    public function testInfoToolExecution(): void
    {
        $tool = InputTools::info($this->context);
        
        $result = $tool->execute([]);
        
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('char_count', $content);
        $this->assertArrayHasKey('line_count', $content);
        $this->assertArrayHasKey('word_count', $content);
        $this->assertArrayHasKey('estimated_tokens', $content);
        $this->assertArrayHasKey('variables', $content);
    }

    public function testSetVariableToolCreation(): void
    {
        $tool = InputTools::setVariable($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('set_variable', $tool->getName());
    }

    public function testSetVariableToolExecution(): void
    {
        $tool = InputTools::setVariable($this->context);
        
        $result = $tool->execute(['name' => 'my_result', 'value' => 'test value']);
        
        $this->assertFalse($result->isError());
        $this->assertTrue($this->context->hasVariable('my_result'));
        $this->assertEquals('test value', $this->context->getVariable('my_result'));
    }

    public function testSetVariableToolWithInvalidName(): void
    {
        $tool = InputTools::setVariable($this->context);
        
        $result = $tool->execute(['name' => '123invalid', 'value' => 'test']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Invalid variable name', $result->getContent());
    }

    public function testSetVariableToolCannotOverwriteInput(): void
    {
        $tool = InputTools::setVariable($this->context);
        
        $result = $tool->execute(['name' => 'input', 'value' => 'new value']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('reserved', $result->getContent());
    }

    public function testSetVariableToolWithEmptyName(): void
    {
        $tool = InputTools::setVariable($this->context);
        
        $result = $tool->execute(['name' => '', 'value' => 'test']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', $result->getContent());
    }

    public function testGetVariableToolCreation(): void
    {
        $tool = InputTools::getVariable($this->context);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('get_variable', $tool->getName());
    }

    public function testGetVariableToolExecution(): void
    {
        $this->context->setVariable('stored', 'my value');
        $tool = InputTools::getVariable($this->context);
        
        $result = $tool->execute(['name' => 'stored']);
        
        $this->assertFalse($result->isError());
        
        $content = json_decode($result->getContent(), true);
        $this->assertEquals('stored', $content['name']);
        $this->assertEquals('my value', $content['value']);
    }

    public function testGetVariableToolWithNonexistent(): void
    {
        $tool = InputTools::getVariable($this->context);
        
        $result = $tool->execute(['name' => 'nonexistent']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('not found', $result->getContent());
    }

    public function testGetVariableToolWithEmptyName(): void
    {
        $tool = InputTools::getVariable($this->context);
        
        $result = $tool->execute(['name' => '']);
        
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', $result->getContent());
    }

    public function testAllToolsReturnsExpectedTools(): void
    {
        $tools = InputTools::all($this->context);
        
        $this->assertCount(6, $tools);
        
        $names = array_map(fn($t) => $t->getName(), $tools);
        $this->assertContains('peek_input', $names);
        $this->assertContains('slice_input', $names);
        $this->assertContains('search_input', $names);
        $this->assertContains('get_input_info', $names);
        $this->assertContains('set_variable', $names);
        $this->assertContains('get_variable', $names);
    }

    public function testToolsUpdateDescription(): void
    {
        $smallContext = new REPLContext("tiny");
        $largeContext = new REPLContext(str_repeat("x", 10000));
        
        $smallPeek = InputTools::peek($smallContext);
        $largePeek = InputTools::peek($largeContext);
        
        // Descriptions should contain different character counts
        $this->assertStringContainsString('4', $smallPeek->getDescription());
        $this->assertStringContainsString('10000', $largePeek->getDescription());
    }
}
