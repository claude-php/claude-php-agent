<?php

declare(strict_types=1);

namespace Tests\Integration\Agents;

use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for AlertAgent with real Claude API.
 * These tests are skipped if ANTHROPIC_API_KEY is not set.
 */
class AlertAgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private ?AlertAgent $agent = null;

    protected function setUp(): void
    {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set, skipping integration tests');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = new AlertAgent($this->client, ['name' => 'integration_test_agent']);
    }

    public function test_processes_alert_with_real_llm_enhancement(): void
    {
        $metric = new Metric('cpu_usage', 92.5, ['host' => 'web-01', 'region' => 'us-east']);
        $alert = new Alert(
            title: 'High CPU Usage Detected',
            message: 'CPU usage has exceeded the threshold of 90% for 5 consecutive minutes',
            severity: Alert::SEVERITY_WARNING,
            metric: $metric,
            context: ['threshold' => 90, 'duration' => '5m']
        );

        $enhancedMessage = null;
        $this->agent->registerChannel('test', function ($alert, $message) use (&$enhancedMessage) {
            $enhancedMessage = $message;
        });

        $this->agent->processAlert($alert);

        $this->assertNotNull($enhancedMessage);
        $this->assertNotEmpty($enhancedMessage);
        $this->assertIsString($enhancedMessage);

        // Enhanced message should be longer and more detailed than original
        $this->assertGreaterThan(10, strlen($enhancedMessage));
    }

    public function test_processes_critical_alert_with_context(): void
    {
        $metric = new Metric('error_rate', 25.5, ['endpoint' => '/api/checkout']);
        $alert = new Alert(
            title: 'Critical Error Rate',
            message: 'Error rate has spiked to 25.5% on checkout endpoint',
            severity: Alert::SEVERITY_CRITICAL,
            metric: $metric,
            context: [
                'endpoint' => '/api/checkout',
                'normal_rate' => 0.5,
                'impact' => 'Customer transactions failing',
            ]
        );

        $channels = [];
        $this->agent->registerChannel('email', function ($alert, $message) use (&$channels) {
            $channels['email'] = $message;
        });
        $this->agent->registerChannel('pagerduty', function ($alert, $message) use (&$channels) {
            $channels['pagerduty'] = $message;
        });

        $this->agent->processAlert($alert);

        $this->assertCount(2, $channels);
        $this->assertArrayHasKey('email', $channels);
        $this->assertArrayHasKey('pagerduty', $channels);
    }

    public function test_run_method_with_complex_alert_description(): void
    {
        $task = <<<TASK
            Critical Database Performance Issue
            The primary database server is experiencing severe performance degradation.
            Query response times have increased from 50ms to 5000ms.
            This is affecting all user-facing features.
            TASK;

        $receivedAlerts = [];
        $this->agent->registerChannel('test', function ($alert, $message) use (&$receivedAlerts) {
            $receivedAlerts[] = [
                'alert' => $alert,
                'message' => $message,
            ];
        });

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $receivedAlerts);

        $alert = $receivedAlerts[0]['alert'];
        $this->assertSame(Alert::SEVERITY_CRITICAL, $alert->getSeverity());
        $this->assertStringContainsString('Database', $alert->getTitle());

        $message = $receivedAlerts[0]['message'];
        $this->assertNotEmpty($message);
    }

    public function test_template_formatting_with_real_alert(): void
    {
        $this->agent->setTemplate(
            Alert::SEVERITY_ERROR,
            "🚨 ERROR ALERT 🚨\n\nTitle: {title}\nSeverity: {severity}\n\n{message}\n\nTime: {timestamp}"
        );

        $alert = new Alert(
            title: 'API Service Failure',
            message: 'The payment API is returning 500 errors',
            severity: Alert::SEVERITY_ERROR
        );

        $formattedMessage = null;
        $this->agent->registerChannel('test', function ($alert, $message) use (&$formattedMessage) {
            $formattedMessage = $message;
        });

        $this->agent->processAlert($alert);

        $this->assertStringContainsString('🚨 ERROR ALERT 🚨', $formattedMessage);
        $this->assertStringContainsString('API Service Failure', $formattedMessage);
        $this->assertStringContainsString('error', strtolower($formattedMessage));
    }

    public function test_handles_multiple_sequential_alerts(): void
    {
        $alertTitles = [];
        $this->agent->registerChannel('test', function ($alert) use (&$alertTitles) {
            $alertTitles[] = $alert->getTitle();
        });

        $alerts = [
            new Alert('Memory Alert', 'Memory usage at 85%', Alert::SEVERITY_WARNING),
            new Alert('Disk Alert', 'Disk space at 95%', Alert::SEVERITY_ERROR),
            new Alert('CPU Alert', 'CPU at 88%', Alert::SEVERITY_WARNING),
        ];

        foreach ($alerts as $alert) {
            $this->agent->processAlert($alert);
        }

        $this->assertCount(3, $alertTitles);
        $this->assertContains('Memory Alert', $alertTitles);
        $this->assertContains('Disk Alert', $alertTitles);
        $this->assertContains('CPU Alert', $alertTitles);
    }

    public function test_alert_history_tracking(): void
    {
        $this->agent->registerChannel('test', function () {});

        $alerts = [
            new Alert('Alert 1', 'First alert message', Alert::SEVERITY_INFO),
            new Alert('Alert 2', 'Second alert message', Alert::SEVERITY_WARNING),
            new Alert('Alert 3', 'Third alert message', Alert::SEVERITY_ERROR),
        ];

        foreach ($alerts as $alert) {
            $this->agent->processAlert($alert);
        }

        $history = $this->agent->getSentAlerts();

        $this->assertCount(3, $history);

        foreach ($history as $entry) {
            $this->assertArrayHasKey('alert', $entry);
            $this->assertArrayHasKey('timestamp', $entry);
            $this->assertArrayHasKey('enhanced_message', $entry);
            $this->assertInstanceOf(Alert::class, $entry['alert']);
        }
    }

    public function test_agent_result_metadata(): void
    {
        $this->agent->registerChannel('email', function () {});
        $this->agent->registerChannel('slack', function () {});

        $result = $this->agent->run('Warning: High memory usage detected on server');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('alert', $metadata);
        $this->assertArrayHasKey('channels_notified', $metadata);
        $this->assertArrayHasKey('total_alerts', $metadata);
        $this->assertSame(2, $metadata['channels_notified']);
    }
}
