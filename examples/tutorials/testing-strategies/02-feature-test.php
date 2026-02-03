<?php

/**
 * Testing Strategies Tutorial 2: Feature Testing
 * 
 * This shows feature test structure
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 2: Feature Testing ===\n\n";

echo "Example feature test:\n\n";

$example = <<<'PHP'
<?php

namespace Tests\Feature;

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group feature
 */
class ValidationWorkflowTest extends TestCase
{
    public function test_complete_validation_workflow(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator());
        
        $code = '<?php class Test {}';
        $result = $coordinator->validate($code);
        
        $this->assertTrue($result->isValid());
    }
}
PHP;

echo $example . "\n\n";

echo "Run with: ./vendor/bin/phpunit tests/Feature/ --group feature\n\n";

echo "✓ See tests/Feature/ for complete examples\n";
