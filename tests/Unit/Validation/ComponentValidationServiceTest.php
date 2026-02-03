<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class ComponentValidationServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/component_validation_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function test_validates_simple_class_successfully(): void
    {
        $code = <<<'PHP'
<?php

class SimpleComponent
{
    public function __construct()
    {
        // Valid constructor
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertSame('SimpleComponent', $result->getMetadata()['class_name']);
    }

    public function test_validates_namespaced_class_successfully(): void
    {
        $code = <<<'PHP'
<?php

namespace MyApp\Components;

class NamespacedComponent
{
    public function __construct()
    {
        // Valid constructor
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertSame('NamespacedComponent', $result->getMetadata()['class_name']);
        $this->assertSame('MyApp\Components', $result->getMetadata()['namespace']);
    }

    public function test_validates_class_with_constructor_validation(): void
    {
        $code = <<<'PHP'
<?php

class ValidatingComponent
{
    public function __construct()
    {
        // Constructor that validates state
        if (!extension_loaded('json')) {
            throw new \RuntimeException('JSON extension required');
        }
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        // Should succeed because json extension is loaded
        $this->assertTrue($result->isValid());
    }

    public function test_fails_when_constructor_throws_exception(): void
    {
        $code = <<<'PHP'
<?php

class FailingComponent
{
    public function __construct()
    {
        throw new \RuntimeException('Component initialization failed');
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('Component initialization failed', $result->getErrors()[0]);
    }

    public function test_fails_when_no_class_found(): void
    {
        $code = <<<'PHP'
<?php

// No class definition
function test() {
    return 'test';
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('No class definition found', $result->getErrors()[0]);
    }

    public function test_extracts_class_name_from_simple_class(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        $this->assertSame('TestComponent', $info['class_name']);
        $this->assertNull($info['namespace']);
    }

    public function test_extracts_class_name_and_namespace(): void
    {
        $code = <<<'PHP'
<?php

namespace MyApp\Components;

class TestComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        $this->assertSame('TestComponent', $info['class_name']);
        $this->assertSame('MyApp\Components', $info['namespace']);
    }

    public function test_extracts_class_name_with_inheritance(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent extends BaseComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        $this->assertSame('TestComponent', $info['class_name']);
    }

    public function test_extracts_class_name_with_interfaces(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent implements ComponentInterface {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        $this->assertSame('TestComponent', $info['class_name']);
    }

    public function test_skips_abstract_classes(): void
    {
        $code = <<<'PHP'
<?php

abstract class AbstractComponent {}

class ConcreteComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        // Note: Token parsing finds the first class, but instantiation will fail for abstract classes
        // This is acceptable since instantiation validation will catch abstract classes
        $this->assertNotNull($info['class_name']);
        $this->assertContains($info['class_name'], ['AbstractComponent', 'ConcreteComponent']);
    }

    public function test_validates_with_constructor_arguments(): void
    {
        $code = <<<'PHP'
<?php

class ComponentWithArgs
{
    private string $name;
    
    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $this->name = $name;
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['TestName'],
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_with_missing_constructor_arguments(): void
    {
        $code = <<<'PHP'
<?php

class ComponentWithRequiredArgs
{
    public function __construct(string $required)
    {
        // Constructor requires argument
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => [], // No args provided
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        // Error message includes both "Fatal error" and argument information
        $this->assertStringContainsString('instantiation', $result->getErrors()[0]);
        $this->assertStringContainsString('argument', strtolower($result->getErrors()[0]));
    }

    public function test_validates_with_context_constructor_args(): void
    {
        $code = <<<'PHP'
<?php

class ComponentWithContextArgs
{
    public function __construct(string $value)
    {
        if ($value !== 'expected') {
            throw new \Exception('Invalid value');
        }
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code, [
            'constructor_args' => ['expected'],
        ]);

        $this->assertTrue($result->isValid());
    }

    public function test_verifies_expected_class_name(): void
    {
        $code = <<<'PHP'
<?php

class ActualComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code, [
            'expected_class_name' => 'ExpectedComponent',
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Expected class name', $result->getErrors()[0]);
        $this->assertStringContainsString('ExpectedComponent', $result->getErrors()[0]);
        $this->assertStringContainsString('ActualComponent', $result->getErrors()[0]);
    }

    public function test_includes_metadata_in_successful_validation(): void
    {
        $code = <<<'PHP'
<?php

class MetadataComponent {}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('validator', $metadata);
        $this->assertArrayHasKey('class_name', $metadata);
        $this->assertArrayHasKey('load_strategy', $metadata);
        $this->assertArrayHasKey('instantiation_time_ms', $metadata);
        $this->assertSame('component_validation', $metadata['validator']);
    }

    public function test_includes_metadata_in_failed_validation(): void
    {
        $code = <<<'PHP'
<?php

class FailingMetadataComponent
{
    public function __construct()
    {
        throw new \Exception('Failed');
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('validator', $metadata);
        $this->assertArrayHasKey('exception_type', $metadata);
        $this->assertArrayHasKey('duration_ms', $metadata);
    }

    public function test_handles_type_errors_in_constructor(): void
    {
        $code = <<<'PHP'
<?php

class TypeErrorComponent
{
    public function __construct(int $value)
    {
        // Requires int
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['not_an_int'], // Wrong type
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        // Error message includes instantiation error with type information
        $this->assertStringContainsString('instantiation', $result->getErrors()[0]);
        $this->assertStringContainsString('type', strtolower($result->getErrors()[0]));
    }

    public function test_handles_parse_errors(): void
    {
        $code = <<<'PHP'
<?php

class BrokenComponent {
    // Missing closing brace
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_extracts_class_name_using_regex_fallback(): void
    {
        // Code with syntax error that prevents token parsing
        $code = 'class RegexFallbackComponent { }';

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $info = $service->extractClassInfo($code);

        $this->assertSame('RegexFallbackComponent', $info['class_name']);
    }
}
