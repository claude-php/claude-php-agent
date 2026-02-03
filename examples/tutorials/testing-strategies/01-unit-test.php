<?php

/**
 * Testing Strategies Tutorial 1: Unit Testing
 * 
 * This is an example unit test structure
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 1: Unit Testing ===\n\n";

echo "Example PHPUnit test structure:\n\n";

$example = <<<'PHP'
<?php

namespace Tests\Unit;

use ClaudeAgents\Tools\Tool;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function test_tool_executes(): void
    {
        $tool = Tool::create('test')
            ->handler(fn($input) => 'result');
        
        $result = $tool->execute([]);
        
        $this->assertSame('result', $result);
    }
}
PHP;

echo $example . "\n\n";

echo "Run with: ./vendor/bin/phpunit tests/Unit/\n\n";

echo "✓ See tests/Unit/ for complete examples\n";
