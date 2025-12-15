<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Progress;

use ClaudeAgents\Progress\AgentUpdate;
use PHPUnit\Framework\TestCase;

class AgentUpdateTest extends TestCase
{
    public function testGettersAndToArray(): void
    {
        $update = new AgentUpdate(
            type: 'llm.iteration',
            agent: 'test_agent',
            data: ['iteration' => 1, 'text' => 'hello'],
            timestamp: 123.456,
        );

        $this->assertSame('llm.iteration', $update->getType());
        $this->assertSame('test_agent', $update->getAgent());
        $this->assertSame(['iteration' => 1, 'text' => 'hello'], $update->getData());
        $this->assertSame(123.456, $update->getTimestamp());

        $array = $update->toArray();
        $this->assertSame('llm.iteration', $array['type']);
        $this->assertSame('test_agent', $array['agent']);
        $this->assertSame(123.456, $array['timestamp']);
        $this->assertSame(['iteration' => 1, 'text' => 'hello'], $array['data']);
    }
}
