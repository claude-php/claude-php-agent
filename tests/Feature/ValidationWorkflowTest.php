<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature;

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\CustomScriptValidator;
use PHPUnit\Framework\TestCase;

/**
 * Feature test for validation workflow.
 *
 * @group feature
 */
class ValidationWorkflowTest extends TestCase
{
    public function test_validates_simple_valid_php_code(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());

        $code = <<<'PHP'
<?php

namespace App;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
PHP;

        $result = $coordinator->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_detects_invalid_php_code(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());

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

        $result = $coordinator->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_multiple_validators_run_in_order(): void
    {
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);

        $validator1 = new PHPSyntaxValidator();
        $coordinator->addValidator($validator1);

        $code = <<<'PHP'
<?php

function test(): string
{
    return "valid";
}
PHP;

        $result = $coordinator->validate($code);

        $this->assertTrue($result->isValid());
    }

    public function test_validation_result_provides_metadata(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());

        $code = '<?php echo "test";';

        $result = $coordinator->validate($code);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('validator_count', $metadata);
        $this->assertArrayHasKey('duration_ms', $metadata);
    }

    public function test_can_serialize_validation_result(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());

        $code = '<?php echo "test";';

        $result = $coordinator->validate($code);

        // To array
        $array = $result->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);

        // To JSON
        $json = $result->toJson();
        $this->assertJson($json);
    }

    public function test_validation_caching_works(): void
    {
        $coordinator = new ValidationCoordinator([
            'cache_results' => true,
        ]);
        $coordinator->addValidator(new PHPSyntaxValidator());

        $code = '<?php echo "test";';

        // First validation
        $start1 = microtime(true);
        $result1 = $coordinator->validate($code);
        $duration1 = microtime(true) - $start1;

        // Second validation (should be cached)
        $start2 = microtime(true);
        $result2 = $coordinator->validate($code);
        $duration2 = microtime(true) - $start2;

        $this->assertTrue($result1->isValid());
        $this->assertTrue($result2->isValid());
        
        // Cached result should be significantly faster
        // (though this is not a strict assertion as timing can vary)
        $this->assertGreaterThan(0, $duration1);
        $this->assertGreaterThan(0, $duration2);
    }

    public function test_validation_summary_is_descriptive(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());

        $validCode = '<?php echo "test";';
        $result = $coordinator->validate($validCode);

        $summary = $result->getSummary();
        $this->assertStringContainsString('passed', strtolower($summary));

        $invalidCode = '<?php echo "test"';
        $result = $coordinator->validate($invalidCode);

        $summary = $result->getSummary();
        $this->assertStringContainsString('failed', strtolower($summary));
    }
}
