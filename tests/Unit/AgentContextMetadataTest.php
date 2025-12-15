<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudePhp\ClaudePhp;
use Mockery;
use PHPUnit\Framework\TestCase;

class AgentContextMetadataTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAddMetadataIsExposedOnResultMetadata(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $config = new AgentConfig();

        $context = new AgentContext(
            client: $client,
            task: 'Test',
            tools: [],
            config: $config,
        );

        $context->addMetadata('final_score', 9);
        $context->addMetadata('reflections', [['iteration' => 1]]);

        $context->complete('Done');

        $result = $context->toResult();
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('final_score', $metadata);
        $this->assertSame(9, $metadata['final_score']);
        $this->assertArrayHasKey('reflections', $metadata);
        $this->assertSame([['iteration' => 1]], $metadata['reflections']);
    }
}
