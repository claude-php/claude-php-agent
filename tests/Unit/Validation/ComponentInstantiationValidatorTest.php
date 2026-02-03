<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class ComponentInstantiationValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/validator_test_' . uniqid();
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

    public function test_creates_validator_with_default_options(): void
    {
        $validator = new ComponentInstantiationValidator();

        $this->assertInstanceOf(ComponentInstantiationValidator::class, $validator);
        $this->assertSame('component_instantiation', $validator->getName());
        $this->assertSame(50, $validator->getPriority());
        $this->assertTrue($validator->isEnabled());
    }

    public function test_creates_validator_with_custom_priority(): void
    {
        $validator = new ComponentInstantiationValidator([
            'priority' => 100,
        ]);

        $this->assertSame(100, $validator->getPriority());
    }

    public function test_creates_validator_with_disabled_state(): void
    {
        $validator = new ComponentInstantiationValidator([
            'enabled' => false,
        ]);

        $this->assertFalse($validator->isEnabled());
    }

    public function test_validates_simple_class(): void
    {
        $code = <<<'PHP'
<?php

class ValidatorTestComponent
{
    public function __construct()
    {
        // Valid constructor
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $validator->validate($code);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
    }

    public function test_fails_validation_for_invalid_class(): void
    {
        $code = <<<'PHP'
<?php

class InvalidComponent
{
    public function __construct()
    {
        throw new \Exception('Validation failed');
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $validator->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_can_handle_returns_true_for_php_class(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent {}
PHP;

        $validator = new ComponentInstantiationValidator();

        $this->assertTrue($validator->canHandle($code));
    }

    public function test_can_handle_returns_false_for_non_php_code(): void
    {
        $code = 'function test() { return "test"; }';

        $validator = new ComponentInstantiationValidator();

        $this->assertFalse($validator->canHandle($code));
    }

    public function test_can_handle_returns_false_when_disabled(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent {}
PHP;

        $validator = new ComponentInstantiationValidator([
            'enabled' => false,
        ]);

        $this->assertFalse($validator->canHandle($code));
    }

    public function test_returns_success_with_warning_when_disabled(): void
    {
        $code = '<?php class TestComponent {}';

        $validator = new ComponentInstantiationValidator([
            'enabled' => false,
        ]);

        $result = $validator->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertNotEmpty($result->getWarnings());
        $this->assertStringContainsString('disabled', $result->getWarnings()[0]);
    }

    public function test_set_enabled_changes_state(): void
    {
        $validator = new ComponentInstantiationValidator();

        $this->assertTrue($validator->isEnabled());

        $validator->setEnabled(false);

        $this->assertFalse($validator->isEnabled());
    }

    public function test_set_enabled_returns_self(): void
    {
        $validator = new ComponentInstantiationValidator();

        $returned = $validator->setEnabled(false);

        $this->assertSame($validator, $returned);
    }

    public function test_get_service_returns_validation_service(): void
    {
        $validator = new ComponentInstantiationValidator();

        $service = $validator->getService();

        $this->assertInstanceOf(ComponentValidationService::class, $service);
    }

    public function test_passes_context_to_service(): void
    {
        $code = <<<'PHP'
<?php

class ContextComponent
{
    public function __construct(string $value)
    {
        if ($value !== 'expected') {
            throw new \Exception('Invalid value');
        }
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $validator->validate($code, [
            'constructor_args' => ['expected'],
        ]);

        $this->assertTrue($result->isValid());
    }

    public function test_integrates_with_validation_coordinator(): void
    {
        $code = <<<'PHP'
<?php

class CoordinatorTestComponent
{
    public function __construct()
    {
        // Valid
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'temp_dir' => $this->tempDir,
            'priority' => 50,
        ]);

        // Verify it implements ValidatorInterface correctly
        $this->assertSame('component_instantiation', $validator->getName());
        $this->assertSame(50, $validator->getPriority());
        $this->assertTrue($validator->canHandle($code));

        $result = $validator->validate($code);
        $this->assertTrue($result->isValid());
    }

    public function test_respects_constructor_args_option(): void
    {
        $code = <<<'PHP'
<?php

class ArgsComponent
{
    public function __construct(string $name, int $count = 0)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name required');
        }
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['TestName', 5],
        ]);

        $result = $validator->validate($code);

        $this->assertTrue($result->isValid());
    }

    public function test_handles_eval_strategy_option(): void
    {
        $code = <<<'PHP'
<?php

class EvalComponent
{
    public function getValue(): string
    {
        return 'eval_test';
    }
}
PHP;

        $validator = new ComponentInstantiationValidator([
            'load_strategy' => 'eval',
            'allow_eval' => true,
        ]);

        $result = $validator->validate($code);

        $this->assertTrue($result->isValid());
    }

    public function test_can_handle_code_without_php_tag(): void
    {
        $code = 'class TestComponent {}';

        $validator = new ComponentInstantiationValidator();

        $this->assertFalse($validator->canHandle($code));
    }

    public function test_can_handle_code_with_class_but_no_php_tag(): void
    {
        $code = 'class TestComponent {}';

        $validator = new ComponentInstantiationValidator();

        // Should return false because no <?php tag
        $this->assertFalse($validator->canHandle($code));
    }
}
