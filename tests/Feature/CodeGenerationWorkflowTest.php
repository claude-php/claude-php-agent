<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature;

use ClaudeAgents\Generation\ComponentResult;
use ClaudeAgents\Generation\ComponentTemplate;
use ClaudeAgents\Support\CodeFormatter;
use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Feature test for code generation workflow.
 *
 * @group feature
 */
class CodeGenerationWorkflowTest extends TestCase
{
    public function test_component_result_stores_code_and_validation(): void
    {
        $code = '<?php echo "test";';
        $validation = ValidationResult::success();

        $result = new ComponentResult($code, $validation);

        $this->assertEquals($code, $result->getCode());
        $this->assertSame($validation, $result->getValidation());
        $this->assertTrue($result->isValid());
        $this->assertTrue($result->wasValidated());
    }

    public function test_component_result_can_save_to_file(): void
    {
        $code = '<?php echo "test";';
        $validation = ValidationResult::success();
        $result = new ComponentResult($code, $validation);

        $tempFile = sys_get_temp_dir() . '/test_component_' . uniqid() . '.php';

        try {
            $saved = $result->saveToFile($tempFile);

            $this->assertTrue($saved);
            $this->assertFileExists($tempFile);

            $content = file_get_contents($tempFile);
            $this->assertEquals($code, $content);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_component_result_provides_summary(): void
    {
        $code = str_repeat("<?php\necho 'line';\n", 10);
        $validation = ValidationResult::success();
        $result = new ComponentResult($code, $validation);

        $summary = $result->getSummary();

        $this->assertStringContainsString('bytes', $summary);
        $this->assertStringContainsString('lines', $summary);
        $this->assertStringContainsString('passed', strtolower($summary));
    }

    public function test_component_template_generates_class(): void
    {
        $code = ComponentTemplate::classTemplate(
            name: 'TestClass',
            namespace: 'App\\Test',
            options: [
                'properties' => [
                    ['name' => 'id', 'type' => 'int', 'visibility' => 'private'],
                ],
            ]
        );

        $this->assertStringContainsString('namespace App\\Test', $code);
        $this->assertStringContainsString('class TestClass', $code);
        $this->assertStringContainsString('private int $id', $code);
        $this->assertStringStartsWith('<?php', $code);
    }

    public function test_component_template_generates_interface(): void
    {
        $code = ComponentTemplate::interfaceTemplate(
            name: 'TestInterface',
            namespace: 'App\\Contracts'
        );

        $this->assertStringContainsString('namespace App\\Contracts', $code);
        $this->assertStringContainsString('interface TestInterface', $code);
    }

    public function test_component_template_generates_trait(): void
    {
        $code = ComponentTemplate::traitTemplate(
            name: 'TestTrait',
            namespace: 'App\\Traits'
        );

        $this->assertStringContainsString('namespace App\\Traits', $code);
        $this->assertStringContainsString('trait TestTrait', $code);
    }

    public function test_component_template_generates_service_with_dependencies(): void
    {
        $code = ComponentTemplate::serviceTemplate(
            name: 'UserService',
            namespace: 'App\\Services',
            options: [
                'dependencies' => [
                    ['name' => 'repository', 'type' => 'UserRepository'],
                    ['name' => 'logger', 'type' => 'LoggerInterface'],
                ],
            ]
        );

        $this->assertStringContainsString('namespace App\\Services', $code);
        $this->assertStringContainsString('class UserService', $code);
        $this->assertStringContainsString('UserRepository $repository', $code);
        $this->assertStringContainsString('LoggerInterface $logger', $code);
        $this->assertStringContainsString('__construct', $code);
    }

    public function test_code_formatter_cleans_php_code(): void
    {
        $messyCode = "```php\n<?php\n\necho 'test';  \n\n```";
        $cleaned = CodeFormatter::cleanPhpCode($messyCode);

        $this->assertStringStartsWith('<?php', $cleaned);
        $this->assertStringNotContainsString('```', $cleaned);
        $this->assertStringEndsWith("'test';\n", $cleaned);
    }

    public function test_code_formatter_extracts_php_from_markdown(): void
    {
        $markdown = "Here is some code:\n\n```php\n<?php echo 'test';\n```\n\nMore text";
        $code = CodeFormatter::extractPhpCode($markdown);

        $this->assertNotNull($code);
        $this->assertStringContainsString('<?php', $code);
        $this->assertStringNotContainsString('```', $code);
    }

    public function test_code_formatter_adds_line_numbers(): void
    {
        $code = "<?php\necho 'line 1';\necho 'line 2';";
        $numbered = CodeFormatter::addLineNumbers($code);

        $this->assertStringContainsString('1 |', $numbered);
        $this->assertStringContainsString('2 |', $numbered);
        $this->assertStringContainsString('3 |', $numbered);
    }

    public function test_code_formatter_provides_statistics(): void
    {
        $code = <<<'PHP'
<?php

// This is a comment
class Test
{
    public function method()
    {
        return true;
    }
}
PHP;

        $stats = CodeFormatter::getStatistics($code);

        $this->assertArrayHasKey('total_lines', $stats);
        $this->assertArrayHasKey('code_lines', $stats);
        $this->assertArrayHasKey('comment_lines', $stats);
        $this->assertArrayHasKey('blank_lines', $stats);
        $this->assertGreaterThan(0, $stats['total_lines']);
    }

    public function test_component_result_provides_code_with_line_numbers(): void
    {
        $code = "<?php\necho 'test';";
        $validation = ValidationResult::success();
        $result = new ComponentResult($code, $validation);

        $numbered = $result->getCodeWithLineNumbers();

        $this->assertStringContainsString('1 |', $numbered);
        $this->assertStringContainsString('<?php', $numbered);
    }

    public function test_component_result_converts_to_array(): void
    {
        $code = '<?php echo "test";';
        $validation = ValidationResult::success();
        $result = new ComponentResult($code, $validation, ['custom' => 'meta']);

        $array = $result->toArray();

        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('code_length', $array);
        $this->assertArrayHasKey('line_count', $array);
        $this->assertArrayHasKey('validation', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals('meta', $array['metadata']['custom']);
    }
}
