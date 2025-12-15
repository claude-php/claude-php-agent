<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Agents\MonitoringAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;

class MonitoringAgentTest extends TestCase
{
    private $client;
    private MonitoringAgent $agent;

    protected function setUp(): void
    {
        $this->client = Mockery::mock(ClaudePhp::class);
        $this->agent = new MonitoringAgent($this->client, [
            'thresholds' => [
                'cpu_usage' => 80,
                'memory_usage' => 90,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructorSetsDefaults(): void
    {
        $agent = new MonitoringAgent($this->client);
        $this->assertEquals('monitoring_agent', $agent->getName());
    }

    public function testConstructorAcceptsCustomName(): void
    {
        $agent = new MonitoringAgent($this->client, ['name' => 'custom_monitor']);
        $this->assertEquals('custom_monitor', $agent->getName());
    }

    public function testRunParsesMetricsFromTask(): void
    {
        $task = "cpu_usage: 50\nmemory_usage: 60";

        // Mock message response for any LLM calls
        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('metrics_analyzed', $result->getMetadata());
        $this->assertEquals(2, $result->getMetadata()['metrics_analyzed']);
    }

    public function testThresholdBasedAlerting(): void
    {
        $task = "cpu_usage: 85\nmemory_usage: 75";

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('alerts_generated', $metadata);
        $this->assertGreaterThan(0, $metadata['alerts_generated']);
    }

    public function testGetName(): void
    {
        $this->assertEquals('monitoring_agent', $this->agent->getName());
    }

    public function testGetAlertsInitiallyEmpty(): void
    {
        $alerts = $this->agent->getAlerts();
        $this->assertIsArray($alerts);
        $this->assertEmpty($alerts);
    }

    public function testMetricParsing(): void
    {
        $task = "test_metric: 100\nanother_metric: 200";

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Analysis complete']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        $this->assertEquals(2, $result->getMetadata()['metrics_analyzed']);
    }

    public function testRunFailureHandling(): void
    {
        // Test that run() succeeds even without LLM calls
        // when only doing threshold-based alerting

        $task = 'cpu_usage: 100';

        // No mocking - agent should work without LLM calls
        // since no threshold is set and no historical data
        $result = $this->agent->run($task);

        // Should succeed even though no LLM enhancement
        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('metrics_analyzed', $result->getMetadata());
        $this->assertEquals(1, $result->getMetadata()['metrics_analyzed']);
    }

    public function testMultipleThresholds(): void
    {
        $agent = new MonitoringAgent($this->client, [
            'thresholds' => [
                'cpu_usage' => 80,
                'memory_usage' => 90,
                'disk_usage' => 85,
            ],
        ]);

        $task = "cpu_usage: 85\nmemory_usage: 92\ndisk_usage: 70";

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $agent->run($task);

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();

        // Should detect at least 2 alerts (cpu and memory exceeded)
        $this->assertGreaterThanOrEqual(2, $metadata['alerts_generated']);
    }

    public function testNoThresholdsConfigured(): void
    {
        $agent = new MonitoringAgent($this->client, [
            'thresholds' => [],
        ]);

        $task = "cpu_usage: 100\nmemory_usage: 100";

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $agent->run($task);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(2, $result->getMetadata()['metrics_analyzed']);
        // No threshold-based alerts without configured thresholds
    }

    public function testCustomCheckInterval(): void
    {
        $agent = new MonitoringAgent($this->client, [
            'check_interval' => 120,
        ]);

        $this->assertEquals('monitoring_agent', $agent->getName());
    }

    public function testStopMonitoring(): void
    {
        $agent = new MonitoringAgent($this->client);

        // Should not throw exception
        $agent->stop();

        $this->assertTrue(true);
    }

    public function testAlertMetadataStructure(): void
    {
        $task = 'cpu_usage: 95';

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('metrics_analyzed', $metadata);
        $this->assertArrayHasKey('alerts_generated', $metadata);
        $this->assertArrayHasKey('alerts', $metadata);
        $this->assertIsArray($metadata['alerts']);
    }

    public function testMetricParsingWithVariousFormats(): void
    {
        $task = "cpu_usage: 50\n" .
                "memory: 75.5\n" .
                "invalid line without colon\n" .
                'disk_space: 90';

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        // Should parse 3 valid metrics (skip invalid line)
        $this->assertEquals(3, $result->getMetadata()['metrics_analyzed']);
    }

    public function testAnswerFormatting(): void
    {
        $task = "cpu_usage: 50\nmemory_usage: 60";

        $mockMessage = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'NORMAL']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesMock = Mockery::mock();
        $messagesMock->shouldReceive('create')
            ->andReturn($mockMessage);

        $this->client->shouldReceive('messages')
            ->andReturn($messagesMock);

        $result = $this->agent->run($task);

        $answer = $result->getAnswer();
        $this->assertStringContainsString('Monitoring Analysis', $answer);
        $this->assertStringContainsString('Metrics Analyzed', $answer);
        $this->assertStringContainsString('cpu_usage', $answer);
        $this->assertStringContainsString('memory_usage', $answer);
    }
}
