<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Monitoring\Alert;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Alert Agent - Processes events and generates intelligent notifications.
 *
 * Handles multi-channel alerts, severity classification, aggregation,
 * deduplication, and smart escalation.
 */
class AlertAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $channels = [];
    private array $templates = [];
    private array $sentAlerts = [];
    private int $aggregationWindow;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - aggregation_window: Time window for alert aggregation in seconds (default: 300)
     *   - max_history: Maximum alert history to keep (default: 1000)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'alert_agent';
        $this->aggregationWindow = $options['aggregation_window'] ?? 300;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        // Process alert from task description
        $this->logger->info("Alert agent: {$task}");

        try {
            $alert = $this->parseAlertFromTask($task);
            $this->processAlert($alert);

            return AgentResult::success(
                answer: "Alert processed: {$alert->getTitle()}",
                messages: [],
                iterations: 1,
                metadata: [
                    'alert' => $alert->toArray(),
                    'channels_notified' => count($this->channels),
                    'total_alerts' => count($this->sentAlerts),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Alert processing failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Process and send an alert.
     */
    public function processAlert(Alert $alert): void
    {
        $this->logger->info("Processing alert: {$alert->getTitle()} [{$alert->getSeverity()}]");

        // Check for duplication
        if ($this->isDuplicate($alert)) {
            $this->logger->info('Alert is duplicate, skipping');

            return;
        }

        // Check if should aggregate
        $aggregated = $this->tryAggregate($alert);
        if ($aggregated) {
            $this->logger->info('Alert aggregated with similar alerts');

            return;
        }

        // Enhance alert message with LLM
        $enhancedMessage = $this->enhanceAlertMessage($alert);

        // Send to configured channels
        foreach ($this->channels as $channelName => $channelCallback) {
            try {
                $this->sendToChannel($channelName, $alert, $enhancedMessage);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to send alert to {$channelName}: {$e->getMessage()}");
            }
        }

        // Record sent alert
        $this->sentAlerts[] = [
            'alert' => $alert,
            'timestamp' => microtime(true),
            'enhanced_message' => $enhancedMessage,
        ];

        // Keep history limited
        if (count($this->sentAlerts) > 1000) {
            array_shift($this->sentAlerts);
        }
    }

    /**
     * Register an alert channel.
     *
     * @param string $name Channel name (e.g., 'email', 'webhook', 'log')
     * @param callable $callback Channel handler: fn(Alert, string $message): void
     */
    public function registerChannel(string $name, callable $callback): void
    {
        $this->channels[$name] = $callback;
        $this->logger->info("Registered alert channel: {$name}");
    }

    /**
     * Set a message template for a severity level.
     */
    public function setTemplate(string $severity, string $template): void
    {
        $this->templates[$severity] = $template;
    }

    /**
     * Check if alert is duplicate.
     */
    private function isDuplicate(Alert $alert): bool
    {
        $recentWindow = microtime(true) - 60; // Last 60 seconds

        foreach ($this->sentAlerts as $sent) {
            if ($sent['timestamp'] < $recentWindow) {
                continue;
            }

            $sentAlert = $sent['alert'];

            // Check if same title and metric
            if ($sentAlert->getTitle() === $alert->getTitle() &&
                $sentAlert->getMetric()?->getName() === $alert->getMetric()?->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to aggregate similar alerts.
     */
    private function tryAggregate(Alert $alert): bool
    {
        $aggregationStart = microtime(true) - $this->aggregationWindow;
        $similarAlerts = [];

        foreach ($this->sentAlerts as $sent) {
            if ($sent['timestamp'] < $aggregationStart) {
                continue;
            }

            $sentAlert = $sent['alert'];

            // Check if similar (same metric, same severity)
            if ($sentAlert->getMetric()?->getName() === $alert->getMetric()?->getName() &&
                $sentAlert->getSeverity() === $alert->getSeverity()) {
                $similarAlerts[] = $sentAlert;
            }
        }

        // If we have similar alerts, aggregate
        if (count($similarAlerts) >= 2) {
            $this->logger->info('Aggregating alert with ' . count($similarAlerts) . ' similar alerts');

            // Create aggregated alert
            $aggregatedAlert = new Alert(
                title: "Multiple alerts for {$alert->getMetric()?->getName()}",
                message: 'Aggregated ' . (count($similarAlerts) + 1) . " similar alerts in the last {$this->aggregationWindow}s",
                severity: $alert->getSeverity(),
                metric: $alert->getMetric(),
                context: ['aggregated_count' => count($similarAlerts) + 1],
            );

            // Send aggregated alert
            $enhancedMessage = $this->enhanceAlertMessage($aggregatedAlert);

            foreach ($this->channels as $channelName => $channelCallback) {
                $this->sendToChannel($channelName, $aggregatedAlert, $enhancedMessage);
            }

            return true;
        }

        return false;
    }

    /**
     * Enhance alert message using LLM.
     */
    private function enhanceAlertMessage(Alert $alert): string
    {
        // Use template if available
        if (isset($this->templates[$alert->getSeverity()])) {
            return $this->applyTemplate($this->templates[$alert->getSeverity()], $alert);
        }

        // Use LLM to enhance message
        try {
            $prompt = $this->buildEnhancementPrompt($alert);

            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 512,
                'system' => 'You are an alert message formatter. Create clear, actionable alert messages.',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            return $this->extractTextContent($response->content ?? []);
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to enhance alert message: {$e->getMessage()}");

            return $alert->getMessage();
        }
    }

    /**
     * Build prompt for message enhancement.
     */
    private function buildEnhancementPrompt(Alert $alert): string
    {
        $contextStr = json_encode($alert->getContext());

        return <<<PROMPT
            Create an alert notification message:

            Title: {$alert->getTitle()}
            Severity: {$alert->getSeverity()}
            Message: {$alert->getMessage()}
            Context: {$contextStr}

            Format a clear, actionable alert message that includes:
            1. What happened
            2. Why it matters
            3. Recommended action (if applicable)

            Keep it concise but informative.
            PROMPT;
    }

    /**
     * Apply template to alert.
     */
    private function applyTemplate(string $template, Alert $alert): string
    {
        $replacements = [
            '{title}' => $alert->getTitle(),
            '{message}' => $alert->getMessage(),
            '{severity}' => $alert->getSeverity(),
            '{timestamp}' => date('Y-m-d H:i:s', (int)$alert->getTimestamp()),
            '{metric_name}' => $alert->getMetric()?->getName() ?? 'N/A',
            '{metric_value}' => $alert->getMetric()?->getValue() ?? 'N/A',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Send alert to a channel.
     */
    private function sendToChannel(string $channelName, Alert $alert, string $message): void
    {
        $this->logger->info("Sending alert to channel: {$channelName}");

        $callback = $this->channels[$channelName];
        $callback($alert, $message);
    }

    /**
     * Parse alert from task description.
     */
    private function parseAlertFromTask(string $task): Alert
    {
        // Simple parsing - in production, use more sophisticated parsing
        $lines = explode("\n", $task);

        $title = $lines[0] ?? 'Alert';
        $message = implode("\n", array_slice($lines, 1));

        // Detect severity from keywords
        $severity = Alert::SEVERITY_INFO;
        if (stripos($task, 'critical') !== false || stripos($task, 'emergency') !== false) {
            $severity = Alert::SEVERITY_CRITICAL;
        } elseif (stripos($task, 'error') !== false || stripos($task, 'fail') !== false) {
            $severity = Alert::SEVERITY_ERROR;
        } elseif (stripos($task, 'warning') !== false || stripos($task, 'warn') !== false) {
            $severity = Alert::SEVERITY_WARNING;
        }

        return new Alert($title, $message, $severity);
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get sent alerts history.
     *
     * @return array<array>
     */
    public function getSentAlerts(int $limit = 100): array
    {
        return array_slice($this->sentAlerts, -$limit);
    }
}
