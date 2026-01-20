<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents\RLM;

use ClaudeAgents\Agents\RLM\REPLContext;
use PHPUnit\Framework\TestCase;

class REPLContextTest extends TestCase
{
    private string $sampleInput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleInput = "Line 1: Hello world\nLine 2: This is a test\nLine 3: More content here\nLine 4: Final line";
    }

    public function testConstructorStoresInput(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $this->assertEquals($this->sampleInput, $context->getInput());
        $this->assertEquals($this->sampleInput, $context->getVariable('input'));
    }

    public function testGetCharCount(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $this->assertEquals(strlen($this->sampleInput), $context->getCharCount());
    }

    public function testGetLineCount(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $this->assertEquals(4, $context->getLineCount());
    }

    public function testGetWordCount(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $this->assertGreaterThan(0, $context->getWordCount());
    }

    public function testGetInputLines(): void
    {
        $context = new REPLContext($this->sampleInput);
        $lines = $context->getInputLines();
        
        $this->assertCount(4, $lines);
        $this->assertEquals('Line 1: Hello world', $lines[0]);
        $this->assertEquals('Line 4: Final line', $lines[3]);
    }

    public function testPeek(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        // Peek at the beginning
        $result = $context->peek(0, 10);
        $this->assertEquals('Line 1: He', $result);
        
        // Peek in the middle
        $result = $context->peek(20, 15);
        $this->assertEquals(substr($this->sampleInput, 20, 15), $result);
    }

    public function testPeekWithNegativeStart(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $result = $context->peek(-5, 10);
        $this->assertEquals(substr($this->sampleInput, 0, 10), $result);
    }

    public function testSlice(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        // Get line 2
        $result = $context->slice(2, 2);
        $this->assertEquals('Line 2: This is a test', $result);
        
        // Get lines 1-2
        $result = $context->slice(1, 2);
        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 2', $result);
    }

    public function testSliceWithOutOfBoundsValues(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        // Start line too high
        $result = $context->slice(100, 200);
        $this->assertNotEmpty($result); // Should return last line
        
        // End line too high
        $result = $context->slice(1, 100);
        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 4', $result);
    }

    public function testSearch(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        // Search for a pattern
        $results = $context->search('/test/i');
        
        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]['line_number']);
        $this->assertStringContainsString('test', $results[0]['line']);
    }

    public function testSearchWithMultipleMatches(): void
    {
        $input = "Error in line 1\nNo issues here\nAnother error found\nAll good";
        $context = new REPLContext($input);
        
        $results = $context->search('/error/i');
        
        $this->assertCount(2, $results);
    }

    public function testSearchWithContext(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $results = $context->search('/test/i', 1);
        
        $this->assertCount(1, $results);
        $this->assertNotEmpty($results[0]['context']);
    }

    public function testGetInfo(): void
    {
        $context = new REPLContext($this->sampleInput);
        $info = $context->getInfo();
        
        $this->assertArrayHasKey('char_count', $info);
        $this->assertArrayHasKey('line_count', $info);
        $this->assertArrayHasKey('word_count', $info);
        $this->assertArrayHasKey('estimated_tokens', $info);
        $this->assertArrayHasKey('first_lines', $info);
        $this->assertArrayHasKey('variables', $info);
        $this->assertArrayHasKey('recursion_depth', $info);
        $this->assertArrayHasKey('max_recursion_depth', $info);
    }

    public function testSetAndGetVariable(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $context->setVariable('result', 'test value');
        
        $this->assertTrue($context->hasVariable('result'));
        $this->assertEquals('test value', $context->getVariable('result'));
    }

    public function testGetNonexistentVariable(): void
    {
        $context = new REPLContext($this->sampleInput);
        
        $this->assertNull($context->getVariable('nonexistent'));
        $this->assertFalse($context->hasVariable('nonexistent'));
    }

    public function testGetVariableNames(): void
    {
        $context = new REPLContext($this->sampleInput);
        $context->setVariable('foo', 'bar');
        $context->setVariable('baz', 'qux');
        
        $names = $context->getVariableNames();
        
        $this->assertContains('input', $names);
        $this->assertContains('foo', $names);
        $this->assertContains('baz', $names);
    }

    public function testRecursionDepth(): void
    {
        $context = new REPLContext($this->sampleInput, 5);
        
        $this->assertEquals(0, $context->getRecursionDepth());
        $this->assertEquals(5, $context->getMaxRecursionDepth());
        $this->assertTrue($context->canRecurse());
    }

    public function testEnterAndExitRecursion(): void
    {
        $context = new REPLContext($this->sampleInput, 3);
        
        $context->enterRecursion('task 1');
        $this->assertEquals(1, $context->getRecursionDepth());
        
        $context->enterRecursion('task 2');
        $this->assertEquals(2, $context->getRecursionDepth());
        
        $context->exitRecursion('result 2');
        $this->assertEquals(1, $context->getRecursionDepth());
        
        $context->exitRecursion('result 1');
        $this->assertEquals(0, $context->getRecursionDepth());
    }

    public function testEnterRecursionThrowsWhenMaxDepthReached(): void
    {
        $context = new REPLContext($this->sampleInput, 2);
        
        $context->enterRecursion('task 1');
        $context->enterRecursion('task 2');
        
        $this->assertFalse($context->canRecurse());
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum recursion depth');
        
        $context->enterRecursion('task 3');
    }

    public function testRecursionHistory(): void
    {
        $context = new REPLContext($this->sampleInput, 5);
        
        $context->enterRecursion('task 1');
        $context->exitRecursion('result 1');
        
        $history = $context->getRecursionHistory();
        
        $this->assertCount(1, $history);
        $this->assertEquals('task 1', $history[0]['task']);
        $this->assertEquals(1, $history[0]['depth']);
        $this->assertEquals('result 1', $history[0]['result']);
    }

    public function testCreateChildContext(): void
    {
        $context = new REPLContext($this->sampleInput, 5);
        $context->enterRecursion('parent task');
        
        $child = $context->createChildContext('child input');
        
        $this->assertEquals('child input', $child->getInput());
        $this->assertEquals(1, $child->getRecursionDepth());
        $this->assertEquals(5, $child->getMaxRecursionDepth());
    }

    public function testGetSummary(): void
    {
        $context = new REPLContext($this->sampleInput);
        $summary = $context->getSummary();
        
        $this->assertIsString($summary);
        $this->assertStringContainsString('chars', $summary);
        $this->assertStringContainsString('lines', $summary);
    }

    public function testEmptyInput(): void
    {
        $context = new REPLContext('');
        
        $this->assertEquals(0, $context->getCharCount());
        $this->assertEquals(1, $context->getLineCount()); // Empty string has 1 "line"
    }

    public function testLargeInput(): void
    {
        $largeInput = implode("\n", array_fill(0, 10000, "Line of text"));
        $context = new REPLContext($largeInput);
        
        $this->assertEquals(10000, $context->getLineCount());
        $this->assertGreaterThan(100000, $context->getCharCount());
    }
}
