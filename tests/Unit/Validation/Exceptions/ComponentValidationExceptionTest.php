<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Exceptions;

use ClaudeAgents\Validation\Exceptions\ComponentValidationException;
use PHPUnit\Framework\TestCase;

class ComponentValidationExceptionTest extends TestCase
{
    public function test_creates_exception_with_all_parameters(): void
    {
        $originalException = new \RuntimeException('Original error');
        $code = "<?php\nclass TestClass {}\n";

        $exception = new ComponentValidationException(
            'Validation failed',
            'TestClass',
            $code,
            $originalException,
            500
        );

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame('TestClass', $exception->getClassName());
        $this->assertSame($code, $exception->getOriginalCode());
        $this->assertSame($originalException, $exception->getOriginalException());
        $this->assertSame(500, $exception->getCode());
    }

    public function test_creates_exception_with_minimal_parameters(): void
    {
        $exception = new ComponentValidationException('Validation failed');

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertNull($exception->getClassName());
        $this->assertSame('', $exception->getOriginalCode());
        $this->assertNull($exception->getOriginalException());
    }

    public function test_get_detailed_message_includes_class_name(): void
    {
        $exception = new ComponentValidationException(
            'Validation failed',
            'TestClass'
        );

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('Validation failed', $detailed);
        $this->assertStringContainsString('TestClass', $detailed);
    }

    public function test_get_detailed_message_includes_original_exception(): void
    {
        $originalException = new \RuntimeException('Original error');
        $exception = new ComponentValidationException(
            'Validation failed',
            'TestClass',
            '',
            $originalException
        );

        $detailed = $exception->getDetailedMessage();

        $this->assertStringContainsString('RuntimeException', $detailed);
        $this->assertStringContainsString('Original error', $detailed);
    }

    public function test_get_code_snippet_with_empty_code(): void
    {
        $exception = new ComponentValidationException('Validation failed');

        $snippet = $exception->getCodeSnippet();

        $this->assertSame('', $snippet);
    }

    public function test_get_code_snippet_shows_context_lines(): void
    {
        $code = implode("\n", [
            '<?php',
            'class TestClass {',
            '    public function __construct() {',
            '        throw new Exception("Error");',
            '    }',
            '}',
        ]);

        $exception = new ComponentValidationException(
            'Validation failed',
            'TestClass',
            $code
        );

        $snippet = $exception->getCodeSnippet(3);

        $this->assertStringContainsString('<?php', $snippet);
        $this->assertStringContainsString('class TestClass', $snippet);
    }

    public function test_get_code_snippet_highlights_error_line(): void
    {
        $code = implode("\n", [
            '<?php',
            'class TestClass {',
            '    public function __construct() {',
            '        throw new Exception("Error");',
            '    }',
            '}',
        ]);

        // Create an exception that has line info
        $innerException = new \Exception('Error on line 4');
        $reflectionException = new \ReflectionClass($innerException);
        $lineProperty = $reflectionException->getProperty('line');
        $lineProperty->setAccessible(true);
        $lineProperty->setValue($innerException, 4);

        $exception = new ComponentValidationException(
            'Validation failed',
            'TestClass',
            $code,
            $innerException
        );

        $snippet = $exception->getCodeSnippet(2);

        // Should highlight line 4
        $this->assertStringContainsString('>>>', $snippet);
    }
}
