<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Exceptions;

use ClaudeAgents\Validation\Exceptions\ClassLoadException;
use PHPUnit\Framework\TestCase;

class ClassLoadExceptionTest extends TestCase
{
    public function test_creates_exception_with_all_parameters(): void
    {
        $previous = new \RuntimeException('Previous error');
        $tempFile = '/tmp/test.php';

        $exception = new ClassLoadException(
            'Failed to load class',
            'TestClass',
            'temp_file',
            $tempFile,
            500,
            $previous
        );

        $this->assertSame('Failed to load class', $exception->getMessage());
        $this->assertSame('TestClass', $exception->getClassName());
        $this->assertSame('temp_file', $exception->getLoadStrategy());
        $this->assertSame($tempFile, $exception->getTempFilePath());
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_creates_exception_with_minimal_parameters(): void
    {
        $exception = new ClassLoadException('Failed to load class');

        $this->assertSame('Failed to load class', $exception->getMessage());
        $this->assertNull($exception->getClassName());
        $this->assertSame('unknown', $exception->getLoadStrategy());
        $this->assertNull($exception->getTempFilePath());
    }

    public function test_get_detailed_message_includes_strategy(): void
    {
        $exception = new ClassLoadException(
            'Failed to load class',
            'TestClass',
            'temp_file'
        );

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('Failed to load class', $detailed);
        $this->assertStringContainsString('temp_file', $detailed);
        $this->assertStringContainsString('TestClass', $detailed);
    }

    public function test_get_detailed_message_includes_temp_file_path(): void
    {
        $tempFile = '/tmp/test.php';
        $exception = new ClassLoadException(
            'Failed to load class',
            'TestClass',
            'temp_file',
            $tempFile
        );

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString($tempFile, $detailed);
    }

    public function test_get_detailed_message_with_eval_strategy(): void
    {
        $exception = new ClassLoadException(
            'Failed to load class',
            'TestClass',
            'eval'
        );

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('eval', $detailed);
        $this->assertStringNotContainsString('temp file', $detailed);
    }
}
