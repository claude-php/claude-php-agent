<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools;

use ClaudeAgents\Tools\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolResultTest extends TestCase
{
    public function testSuccessWithString(): void
    {
        $result = ToolResult::success('Test result');

        $this->assertEquals('Test result', $result->getContent());
        $this->assertFalse($result->isError());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithArray(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $result = ToolResult::success($data);

        $this->assertEquals('{"key":"value","number":42}', $result->getContent());
        $this->assertFalse($result->isError());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithEmptyString(): void
    {
        $result = ToolResult::success('');

        $this->assertEquals('', $result->getContent());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithEmptyArray(): void
    {
        $result = ToolResult::success([]);

        $this->assertEquals('[]', $result->getContent());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithNestedArray(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'roles' => ['admin', 'user'],
            ],
            'meta' => [
                'timestamp' => 1234567890,
            ],
        ];
        $result = ToolResult::success($data);

        $decoded = json_decode($result->getContent(), true);
        $this->assertEquals('John', $decoded['user']['name']);
        $this->assertEquals(['admin', 'user'], $decoded['user']['roles']);
        $this->assertTrue($result->isSuccess());
    }

    public function testError(): void
    {
        $result = ToolResult::error('Something went wrong');

        $this->assertEquals('Something went wrong', $result->getContent());
        $this->assertTrue($result->isError());
        $this->assertFalse($result->isSuccess());
    }

    public function testErrorWithEmptyMessage(): void
    {
        $result = ToolResult::error('');

        $this->assertEquals('', $result->getContent());
        $this->assertTrue($result->isError());
    }

    public function testFromException(): void
    {
        $exception = new \RuntimeException('Test exception message');
        $result = ToolResult::fromException($exception);

        $this->assertStringContainsString('Test exception message', $result->getContent());
        $this->assertStringStartsWith('Error: ', $result->getContent());
        $this->assertTrue($result->isError());
        $this->assertFalse($result->isSuccess());
    }

    public function testFromExceptionWithDifferentTypes(): void
    {
        $exceptions = [
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic error'),
            new \Exception('Generic exception'),
        ];

        foreach ($exceptions as $exception) {
            $result = ToolResult::fromException($exception);
            $this->assertTrue($result->isError());
            $this->assertStringContainsString($exception->getMessage(), $result->getContent());
        }
    }

    public function testGetContent(): void
    {
        $success = ToolResult::success('success content');
        $error = ToolResult::error('error content');

        $this->assertEquals('success content', $success->getContent());
        $this->assertEquals('error content', $error->getContent());
    }

    public function testIsError(): void
    {
        $success = ToolResult::success('test');
        $error = ToolResult::error('test');

        $this->assertFalse($success->isError());
        $this->assertTrue($error->isError());
    }

    public function testIsSuccess(): void
    {
        $success = ToolResult::success('test');
        $error = ToolResult::error('test');

        $this->assertTrue($success->isSuccess());
        $this->assertFalse($error->isSuccess());
    }

    public function testToApiFormatWithSuccess(): void
    {
        $result = ToolResult::success('Test output');
        $apiFormat = $result->toApiFormat('tool_use_123');

        $this->assertIsArray($apiFormat);
        $this->assertArrayHasKey('type', $apiFormat);
        $this->assertArrayHasKey('tool_use_id', $apiFormat);
        $this->assertArrayHasKey('content', $apiFormat);
        $this->assertEquals('tool_result', $apiFormat['type']);
        $this->assertEquals('tool_use_123', $apiFormat['tool_use_id']);
        $this->assertEquals('Test output', $apiFormat['content']);
        $this->assertArrayNotHasKey('is_error', $apiFormat);
    }

    public function testToApiFormatWithError(): void
    {
        $result = ToolResult::error('Error message');
        $apiFormat = $result->toApiFormat('tool_use_456');

        $this->assertIsArray($apiFormat);
        $this->assertArrayHasKey('type', $apiFormat);
        $this->assertArrayHasKey('tool_use_id', $apiFormat);
        $this->assertArrayHasKey('content', $apiFormat);
        $this->assertArrayHasKey('is_error', $apiFormat);
        $this->assertEquals('tool_result', $apiFormat['type']);
        $this->assertEquals('tool_use_456', $apiFormat['tool_use_id']);
        $this->assertEquals('Error message', $apiFormat['content']);
        $this->assertTrue($apiFormat['is_error']);
    }

    public function testToApiFormatWithDifferentToolUseIds(): void
    {
        $result = ToolResult::success('test');

        $format1 = $result->toApiFormat('id_1');
        $format2 = $result->toApiFormat('id_2');

        $this->assertEquals('id_1', $format1['tool_use_id']);
        $this->assertEquals('id_2', $format2['tool_use_id']);
    }

    public function testConstructorWithDefaults(): void
    {
        $result = new ToolResult('content');

        $this->assertEquals('content', $result->getContent());
        $this->assertFalse($result->isError());
        $this->assertTrue($result->isSuccess());
    }

    public function testConstructorWithExplicitValues(): void
    {
        $success = new ToolResult('success', false);
        $error = new ToolResult('error', true);

        $this->assertFalse($success->isError());
        $this->assertTrue($success->isSuccess());
        $this->assertTrue($error->isError());
        $this->assertFalse($error->isSuccess());
    }

    public function testSuccessWithNumericValue(): void
    {
        $result = ToolResult::success(['value' => 42, 'pi' => 3.14159]);
        $decoded = json_decode($result->getContent(), true);

        $this->assertEquals(42, $decoded['value']);
        $this->assertEquals(3.14159, $decoded['pi']);
    }

    public function testSuccessWithBoolean(): void
    {
        $result = ToolResult::success(['success' => true, 'failed' => false]);
        $decoded = json_decode($result->getContent(), true);

        $this->assertTrue($decoded['success']);
        $this->assertFalse($decoded['failed']);
    }

    public function testSuccessWithNull(): void
    {
        $result = ToolResult::success(['value' => null]);
        $decoded = json_decode($result->getContent(), true);

        $this->assertNull($decoded['value']);
    }

    public function testErrorMessagePreservation(): void
    {
        $longMessage = str_repeat('Error: ', 100);
        $result = ToolResult::error($longMessage);

        $this->assertEquals($longMessage, $result->getContent());
        $this->assertTrue($result->isError());
    }

    public function testMultipleApiFormatCalls(): void
    {
        $result = ToolResult::success('test');

        // Should be able to call toApiFormat multiple times
        $format1 = $result->toApiFormat('id_1');
        $format2 = $result->toApiFormat('id_2');
        $format3 = $result->toApiFormat('id_3');

        $this->assertEquals('test', $format1['content']);
        $this->assertEquals('test', $format2['content']);
        $this->assertEquals('test', $format3['content']);
        $this->assertNotEquals($format1['tool_use_id'], $format2['tool_use_id']);
    }

    public function testSuccessWithSpecialCharacters(): void
    {
        $specialChars = "Line 1\nLine 2\tTabbed\r\nWindows line";
        $result = ToolResult::success($specialChars);

        $this->assertEquals($specialChars, $result->getContent());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithUnicodeCharacters(): void
    {
        $unicode = 'Hello 世界 🌍 مرحبا';
        $result = ToolResult::success($unicode);

        $this->assertEquals($unicode, $result->getContent());
        $this->assertTrue($result->isSuccess());
    }

    public function testFromExceptionPreservesMessage(): void
    {
        $message = 'Detailed error message with numbers 123 and symbols !@#$%';
        $exception = new \Exception($message);
        $result = ToolResult::fromException($exception);

        $this->assertStringContainsString($message, $result->getContent());
    }

    public function testImmutability(): void
    {
        $result = ToolResult::success('original');

        // Create API format shouldn't modify the original result
        $result->toApiFormat('id_1');
        $result->toApiFormat('id_2');

        $this->assertEquals('original', $result->getContent());
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithComplexJsonStructure(): void
    {
        $complexData = [
            'users' => [
                ['id' => 1, 'name' => 'Alice', 'active' => true],
                ['id' => 2, 'name' => 'Bob', 'active' => false],
            ],
            'metadata' => [
                'count' => 2,
                'timestamp' => '2024-01-01T00:00:00Z',
            ],
        ];

        $result = ToolResult::success($complexData);
        $decoded = json_decode($result->getContent(), true);

        $this->assertCount(2, $decoded['users']);
        $this->assertEquals('Alice', $decoded['users'][0]['name']);
        $this->assertTrue($decoded['users'][0]['active']);
        $this->assertEquals(2, $decoded['metadata']['count']);
    }
}
