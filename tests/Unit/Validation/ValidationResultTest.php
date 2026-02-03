<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Validation;

use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_creates_successful_result(): void
    {
        $result = ValidationResult::success();

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isFailed());
        $this->assertEmpty($result->getErrors());
        $this->assertEmpty($result->getWarnings());
    }

    public function test_creates_failed_result(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = ValidationResult::failure($errors);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isFailed());
        $this->assertEquals($errors, $result->getErrors());
        $this->assertTrue($result->hasErrors());
        $this->assertEquals(2, $result->getErrorCount());
    }

    public function test_includes_warnings(): void
    {
        $warnings = ['Warning 1', 'Warning 2'];
        $result = ValidationResult::success($warnings);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertEquals(2, $result->getWarningCount());
    }

    public function test_includes_metadata(): void
    {
        $metadata = ['validator' => 'test', 'duration' => 123];
        $result = ValidationResult::success([], $metadata);

        $this->assertEquals($metadata, $result->getMetadata());
    }

    public function test_merges_results(): void
    {
        $result1 = ValidationResult::success(['Warning 1'], ['meta1' => 'value1']);
        $result2 = ValidationResult::success(['Warning 2'], ['meta2' => 'value2']);

        $merged = $result1->merge($result2);

        $this->assertTrue($merged->isValid());
        $this->assertEquals(['Warning 1', 'Warning 2'], $merged->getWarnings());
        $this->assertEquals(['meta1' => 'value1', 'meta2' => 'value2'], $merged->getMetadata());
    }

    public function test_merge_with_failure_makes_invalid(): void
    {
        $success = ValidationResult::success();
        $failure = ValidationResult::failure(['Error']);

        $merged = $success->merge($failure);

        $this->assertFalse($merged->isValid());
        $this->assertEquals(['Error'], $merged->getErrors());
    }

    public function test_converts_to_array(): void
    {
        $result = ValidationResult::failure(
            ['Error 1'],
            ['Warning 1'],
            ['key' => 'value']
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('error_count', $array);
        $this->assertArrayHasKey('warning_count', $array);

        $this->assertFalse($array['valid']);
        $this->assertEquals(1, $array['error_count']);
        $this->assertEquals(1, $array['warning_count']);
    }

    public function test_converts_to_json(): void
    {
        $result = ValidationResult::success(['Warning']);

        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['valid']);
    }

    public function test_provides_summary(): void
    {
        $success = ValidationResult::success();
        $this->assertStringContainsString('passed', $success->getSummary());

        $successWithWarnings = ValidationResult::success(['Warning']);
        $this->assertStringContainsString('warning', $successWithWarnings->getSummary());

        $failure = ValidationResult::failure(['Error']);
        $this->assertStringContainsString('failed', $failure->getSummary());
        $this->assertStringContainsString('1 error', $failure->getSummary());
    }
}
