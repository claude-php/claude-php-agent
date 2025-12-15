<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentResult;
use PHPUnit\Framework\TestCase;

class AgentResultEnhancementsTest extends TestCase
{
    public function testJsonSerializable(): void
    {
        $result = AgentResult::success(
            answer: 'Test answer',
            messages: [['role' => 'user', 'content' => 'Hi']],
            iterations: 2,
            metadata: ['test' => 'value'],
        );

        $json = json_encode($result);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals('Test answer', $decoded['answer']);
    }

    public function testToJson(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
        );

        $json = $result->toJson();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('success', $decoded);
    }

    public function testFromArray(): void
    {
        $data = [
            'success' => true,
            'answer' => 'Restored answer',
            'iterations' => 3,
            'messages' => [['role' => 'user', 'content' => 'Test']],
            'metadata' => ['key' => 'value'],
            'error' => null,
        ];

        $result = AgentResult::fromArray($data);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Restored answer', $result->getAnswer());
        $this->assertEquals(3, $result->getIterations());
    }

    public function testFromJson(): void
    {
        $json = json_encode([
            'success' => false,
            'answer' => '',
            'error' => 'Test error',
            'iterations' => 1,
            'messages' => [],
            'metadata' => [],
        ]);

        $result = AgentResult::fromJson($json);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Test error', $result->getError());
    }

    public function testGetMetadataValue(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
            metadata: ['custom' => 'data', 'score' => 95],
        );

        $this->assertEquals('data', $result->getMetadataValue('custom'));
        $this->assertEquals(95, $result->getMetadataValue('score'));
        $this->assertNull($result->getMetadataValue('missing'));
        $this->assertEquals('default', $result->getMetadataValue('missing', 'default'));
    }

    public function testHasMetadata(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
            metadata: ['exists' => true],
        );

        $this->assertTrue($result->hasMetadata('exists'));
        $this->assertFalse($result->hasMetadata('missing'));
    }

    public function testWithMetadata(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
            metadata: ['original' => 'value'],
        );

        $newResult = $result->withMetadata('added', 'new');

        // Original unchanged (immutable)
        $this->assertFalse($result->hasMetadata('added'));

        // New result has both
        $this->assertTrue($newResult->hasMetadata('original'));
        $this->assertTrue($newResult->hasMetadata('added'));
        $this->assertEquals('new', $newResult->getMetadataValue('added'));
    }

    public function testIsPartial(): void
    {
        $partialResult = AgentResult::success(
            answer: 'Partial',
            messages: [],
            iterations: 1,
            metadata: ['is_partial' => true],
        );

        $completeResult = AgentResult::success(
            answer: 'Complete',
            messages: [],
            iterations: 1,
        );

        $this->assertTrue($partialResult->isPartial());
        $this->assertFalse($completeResult->isPartial());
    }

    public function testCompareTo(): void
    {
        $success1 = AgentResult::success('Answer', [], 5);
        $success2 = AgentResult::success('Answer', [], 3);
        $failure = AgentResult::failure('Error');

        // Success beats failure
        $this->assertEquals(1, $success1->compareTo($failure));
        $this->assertEquals(-1, $failure->compareTo($success1));

        // Fewer iterations is better
        $this->assertEquals(-1, $success1->compareTo($success2));
        $this->assertEquals(1, $success2->compareTo($success1));
    }

    public function testIsBetterThan(): void
    {
        $better = AgentResult::success('Answer', [], 2);
        $worse = AgentResult::success('Answer', [], 5);
        $failure = AgentResult::failure('Error');

        $this->assertTrue($better->isBetterThan($worse));
        $this->assertFalse($worse->isBetterThan($better));
        $this->assertTrue($better->isBetterThan($failure));
    }

    public function testGetQualityScore(): void
    {
        $success = AgentResult::success('Good answer', [], 2);
        $failure = AgentResult::failure('Error');

        $successScore = $success->getQualityScore();
        $failureScore = $failure->getQualityScore();

        $this->assertGreaterThan(0, $successScore);
        $this->assertLessThanOrEqual(1.0, $successScore);
        $this->assertEquals(0.0, $failureScore);
    }

    public function testToString(): void
    {
        $success = AgentResult::success('This is a test answer', [], 3);
        $failure = AgentResult::failure('Something went wrong', [], 2);

        $successStr = (string) $success;
        $failureStr = (string) $failure;

        $this->assertStringContainsString('Success', $successStr);
        $this->assertStringContainsString('3 iterations', $successStr);
        $this->assertStringContainsString('This is a test answer', $successStr);

        $this->assertStringContainsString('Failed', $failureStr);
        $this->assertStringContainsString('2 iterations', $failureStr);
        $this->assertStringContainsString('Something went wrong', $failureStr);
    }

    public function testValidationSuccessRequiresNonEmptyAnswer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty');

        AgentResult::success(
            answer: '   ',
            messages: [],
            iterations: 1,
        );
    }

    public function testValidationFailureRequiresNonEmptyError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error message cannot be empty');

        AgentResult::failure(
            error: '   ',
        );
    }

    public function testFromJsonInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        AgentResult::fromJson('invalid json');
    }
}
