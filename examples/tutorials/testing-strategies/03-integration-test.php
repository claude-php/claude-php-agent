<?php

/**
 * Testing Strategies Tutorial 3: Integration Testing
 * 
 * This shows integration test with real API
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 3: Integration Testing ===\n\n";

echo "Example integration test:\n\n";

$example = <<<'PHP'
<?php

namespace Tests\Integration;

use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 * @group requires-api-key
 */
class AgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    
    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('API key not set');
        }
        
        $this->client = new ClaudePhp(apiKey: $apiKey);
    }
    
    public function test_agent_with_real_api(): void
    {
        $agent = Agent::create($this->client);
        $result = $agent->run('What is 2+2?');
        
        $this->assertStringContainsString('4', $result->getAnswer());
    }
}
PHP;

echo $example . "\n\n";

echo "Run with:\n";
echo "  ANTHROPIC_API_KEY=your-key ./vendor/bin/phpunit --group integration\n\n";

echo "✓ See tests/Integration/ for complete examples\n";
