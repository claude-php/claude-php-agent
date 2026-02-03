<?php

/**
 * Testing Strategies Tutorial 4: Mocking
 * 
 * Run: php examples/tutorials/testing-strategies/04-mocking.php
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 4: Mocking ===\n\n";

echo "Example with Mockery:\n\n";

$example = <<<'PHP'
<?php

use ClaudePhp\ClaudePhp;
use Mockery;

class MockedTest extends TestCase
{
    public function test_with_mock(): void
    {
        $mock = Mockery::mock(ClaudePhp::class);
        
        $mock->shouldReceive('messages->create')
            ->once()
            ->andReturn((object) [
                'content' => [(object) ['text' => 'Mocked']],
            ]);
        
        $agent = Agent::create($mock);
        $result = $agent->run('test');
        
        $this->assertStringContainsString('Mocked', $result->getAnswer());
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
    }
}
PHP;

echo $example . "\n\n";

echo "Install Mockery:\n";
echo "  composer require --dev mockery/mockery\n\n";

echo "✓ See tests/Unit/ for mocking examples\n";
