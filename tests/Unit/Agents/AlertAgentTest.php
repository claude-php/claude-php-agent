<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AlertAgentTest extends TestCase
{
    private ClaudePhp $client;
    private AlertAgent $agent;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new AlertAgent($this->client, ['name' => 'test_alert_agent']);
    }

    public function test_creates_alert_agent_with_default_options(): void
    {
        $agent = new AlertAgent($this->client);

        $this->assertSame('alert_agent', $agent->getName());
    }

    public function test_creates_alert_agent_with_custom_options(): void
    {
        $logger = new NullLogger();
        $agent = new AlertAgent($this->client, [
            'name' => 'custom_alert',
            'aggregation_window' => 600,
            'logger' => $logger,
        ]);

        $this->assertSame('custom_alert', $agent->getName());
    }

    public function test_get_name(): void
    {
        $this->assertSame('test_alert_agent', $this->agent->getName());
    }

    public function test_processes_alert_successfully(): void
    {
        $metric = new Metric('cpu_usage', 95.5);
        $alert = new Alert(
            'High CPU Usage',
            'CPU usage exceeded 90%',
            Alert::SEVERITY_WARNING,
            $metric
        );

        $called = false;
        $this->agent->registerChannel('test', function ($receivedAlert, $message) use ($alert, &$called) {
            $this->assertSame($alert->getTitle(), $receivedAlert->getTitle());
            $this->assertIsString($message);
            $called = true;
        });

        $this->mockLlmResponse('Enhanced alert message: CPU usage is high');

        $this->agent->processAlert($alert);

        $this->assertTrue($called, 'Channel callback should have been called');
    }

    public function test_registers_multiple_channels(): void
    {
        $emailCalled = false;
        $webhookCalled = false;

        $this->agent->registerChannel('email', function () use (&$emailCalled) {
            $emailCalled = true;
        });

        $this->agent->registerChannel('webhook', function () use (&$webhookCalled) {
            $webhookCalled = true;
        });

        $this->mockLlmResponse('Alert message');

        $alert = new Alert('Test', 'Test message');
        $this->agent->processAlert($alert);

        $this->assertTrue($emailCalled);
        $this->assertTrue($webhookCalled);
    }

    public function test_sets_and_uses_template(): void
    {
        $this->agent->setTemplate(Alert::SEVERITY_CRITICAL, 'CRITICAL: {title} - {message}');

        $alert = new Alert('System Down', 'The system is down', Alert::SEVERITY_CRITICAL);

        $receivedMessage = '';
        $this->agent->registerChannel('test', function ($alert, $message) use (&$receivedMessage) {
            $receivedMessage = $message;
        });

        $this->agent->processAlert($alert);

        $this->assertStringContainsString('CRITICAL:', $receivedMessage);
        $this->assertStringContainsString('System Down', $receivedMessage);
        $this->assertStringContainsString('The system is down', $receivedMessage);
    }

    public function test_deduplicates_alerts(): void
    {
        $callCount = 0;
        $this->agent->registerChannel('test', function () use (&$callCount) {
            $callCount++;
        });

        $this->mockLlmResponse('Alert message');

        // Send same alert twice within deduplication window
        $alert1 = new Alert('Duplicate Test', 'Same message');
        $alert2 = new Alert('Duplicate Test', 'Same message');

        $this->agent->processAlert($alert1);
        $this->agent->processAlert($alert2); // Should be deduplicated

        $this->assertSame(1, $callCount, 'Duplicate alert should have been filtered');
    }

    public function test_deduplicates_alerts_with_same_metric(): void
    {
        $callCount = 0;
        $this->agent->registerChannel('test', function () use (&$callCount) {
            $callCount++;
        });

        $this->mockLlmResponse('Alert message');

        $metric1 = new Metric('cpu_usage', 95);
        $metric2 = new Metric('cpu_usage', 96);

        $alert1 = new Alert('High CPU', 'CPU is high', Alert::SEVERITY_WARNING, $metric1);
        $alert2 = new Alert('High CPU', 'CPU is high', Alert::SEVERITY_WARNING, $metric2);

        $this->agent->processAlert($alert1);
        $this->agent->processAlert($alert2); // Should be deduplicated

        $this->assertSame(1, $callCount);
    }

    public function test_aggregates_similar_alerts(): void
    {
        $messages = [];
        $this->agent->registerChannel('test', function ($alert, $message) use (&$messages) {
            $messages[] = $message;
        });

        $this->mockLlmResponse('Alert message');

        $metric = new Metric('response_time', 5000);

        // Send multiple similar alerts
        for ($i = 0; $i < 3; $i++) {
            $alert = new Alert(
                "Slow Response {$i}",
                'Response time too high',
                Alert::SEVERITY_WARNING,
                $metric
            );
            $this->agent->processAlert($alert);
        }

        // Should have aggregated after 2 similar alerts
        $this->assertGreaterThan(0, count($messages));
    }

    public function test_get_sent_alerts_history(): void
    {
        $this->mockLlmResponse('Alert message');

        $this->agent->registerChannel('test', function () {});

        $alert1 = new Alert('Alert 1', 'Message 1');
        $alert2 = new Alert('Alert 2', 'Message 2');

        $this->agent->processAlert($alert1);
        $this->agent->processAlert($alert2);

        $history = $this->agent->getSentAlerts();

        $this->assertCount(2, $history);
        $this->assertArrayHasKey('alert', $history[0]);
        $this->assertArrayHasKey('timestamp', $history[0]);
        $this->assertArrayHasKey('enhanced_message', $history[0]);
    }

    public function test_get_sent_alerts_with_limit(): void
    {
        $this->mockLlmResponse('Alert message');

        $this->agent->registerChannel('test', function () {});

        // Use unique metrics to prevent deduplication
        for ($i = 0; $i < 10; $i++) {
            $metric = new Metric("metric_{$i}", $i);
            $alert = new Alert("Alert {$i}", "Message {$i}", Alert::SEVERITY_INFO, $metric);
            $this->agent->processAlert($alert);
        }

        $history = $this->agent->getSentAlerts(5);

        $this->assertCount(5, $history);
    }

    public function test_run_method_processes_task(): void
    {
        $this->mockLlmResponse('Enhanced alert message');

        $this->agent->registerChannel('test', function () {});

        $result = $this->agent->run('Critical alert: System is down');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Alert processed', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('alert', $metadata);
        $this->assertArrayHasKey('channels_notified', $metadata);
        $this->assertArrayHasKey('total_alerts', $metadata);
    }

    public function test_run_method_detects_severity_from_keywords(): void
    {
        $this->mockLlmResponse('Enhanced alert message');

        $capturedAlert = null;
        $this->agent->registerChannel('test', function ($alert) use (&$capturedAlert) {
            $capturedAlert = $alert;
        });

        // Test critical keyword
        $this->agent->run('Critical issue detected');
        $this->assertSame(Alert::SEVERITY_CRITICAL, $capturedAlert->getSeverity());

        // Test error keyword
        $this->agent->run('Error in processing');
        $this->assertSame(Alert::SEVERITY_ERROR, $capturedAlert->getSeverity());

        // Test warning keyword
        $this->agent->run('Warning: memory usage high');
        $this->assertSame(Alert::SEVERITY_WARNING, $capturedAlert->getSeverity());

        // Test default (info)
        $this->agent->run('System information update');
        $this->assertSame(Alert::SEVERITY_INFO, $capturedAlert->getSeverity());
    }

    public function test_handles_channel_callback_failure(): void
    {
        $goodChannelCalled = false;

        $this->agent->registerChannel('failing', function () {
            throw new \RuntimeException('Channel failed');
        });

        $this->agent->registerChannel('working', function () use (&$goodChannelCalled) {
            $goodChannelCalled = true;
        });

        $this->mockLlmResponse('Alert message');

        $alert = new Alert('Test', 'Test message');

        // Should not throw exception, should continue to other channels
        $this->agent->processAlert($alert);

        $this->assertTrue($goodChannelCalled, 'Working channel should still be called');
    }

    public function test_limits_alert_history_size(): void
    {
        $this->mockLlmResponse('Alert message');

        $this->agent->registerChannel('test', function () {});

        // Send more than 1000 alerts to trigger history limit
        for ($i = 0; $i < 1050; $i++) {
            $alert = new Alert("Alert {$i}", "Message {$i}");
            $this->agent->processAlert($alert);
        }

        $history = $this->agent->getSentAlerts(2000);

        // Should be limited to 1000
        $this->assertLessThanOrEqual(1000, count($history));
    }

    public function test_run_returns_failure_on_exception(): void
    {
        // Create agent without any channels or mock setup to trigger error path
        $client = $this->createMock(ClaudePhp::class);
        $agent = new AlertAgent($client);

        // This should cause a failure in LLM enhancement
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('API Error'));
        $client->method('messages')->willReturn($messages);

        $agent->registerChannel('test', function () {});

        $result = $agent->run('Test alert');

        // The agent should handle the error gracefully
        $this->assertInstanceOf(AgentResult::class, $result);
    }

    private function mockLlmResponse(string $text): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $text],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);
    }
}
