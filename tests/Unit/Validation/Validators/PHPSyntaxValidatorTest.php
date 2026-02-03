<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Validation\Validators;

use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use PHPUnit\Framework\TestCase;

class PHPSyntaxValidatorTest extends TestCase
{
    private PHPSyntaxValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PHPSyntaxValidator();
    }

    public function test_validates_correct_php_code(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

class Test
{
    public function hello(): string
    {
        return "Hello, World!";
    }
}
PHP;

        $result = $this->validator->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_detects_syntax_errors(): void
    {
        $code = <<<'PHP'
<?php

class Broken
{
    public function test()
    {
        return "missing semicolon"
    }
}
PHP;

        $result = $this->validator->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_detects_missing_braces(): void
    {
        $code = <<<'PHP'
<?php

function test() {
    if (true) {
        echo "test";
    // Missing closing brace
PHP;

        $result = $this->validator->validate($code);

        $this->assertFalse($result->isValid());
    }

    public function test_can_handle_php_code(): void
    {
        $phpCode = '<?php echo "test";';
        $this->assertTrue($this->validator->canHandle($phpCode));

        $nonPhpCode = 'Just plain text';
        $this->assertFalse($this->validator->canHandle($nonPhpCode));
    }

    public function test_has_high_priority(): void
    {
        // PHPSyntaxValidator should run early (low priority number)
        $this->assertEquals(10, $this->validator->getPriority());
    }

    public function test_returns_validator_name(): void
    {
        $this->assertEquals('php_syntax', $this->validator->getName());
    }

    public function test_includes_metadata_in_result(): void
    {
        $code = '<?php echo "test";';
        $result = $this->validator->validate($code);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('validator', $metadata);
        $this->assertEquals('php_syntax', $metadata['validator']);
    }
}
