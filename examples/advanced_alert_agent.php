#!/usr/bin/env php
<?php
/**
 * Advanced Alert Agent Example
 *
 * Demonstrates advanced features including:
 * - Multiple notification channels (email, webhook, Slack)
 * - Custom templates for different severity levels
 * - Alert aggregation and deduplication
 * - Metric-based threshold alerting
 * - Alert history and statistics
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\AlertAgent;
use ClaudeAgents\Monitoring\Alert;
use ClaudeAgents\Monitoring\Metric;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Console logger with colors
class ColoredLogger extends AbstractLogger
{
    private const COLORS = [
        'info' => "\033[0;36m",     // Cyan
        'warning' => "\033[0;33m",  // Yellow
        'error' => "\033[0;31m",    // Red
        'critical' => "\033[1;31m", // Bold Red
        'reset' => "\033[0m",
    ];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $color = self::COLORS[$level] ?? self::COLORS['reset'];
        $reset = self::COLORS['reset'];
        $timestamp = date('H:i:s.v');
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }
}

// Simulated Email Channel
class EmailChannel
{
    public function __construct(private array $config = [])
    {
    }

    public function send(Alert $alert, string $message): void
    {
        $to = $this->config['to'] ?? 'ops@example.com';
        $subject = "[{$alert->getSeverity()}] {$alert->getTitle()}";
        
        echo "\n📧 EMAIL SENT\n";
        echo "   To: {$to}\n";
        echo "   Subject: {$subject}\n";
        echo "   Message: " . substr($message, 0, 80) . "...\n";
    }
}

// Simulated Webhook Channel
class WebhookChannel
{
    public function __construct(private string $url)
    {
    }

    public function send(Alert $alert, string $message): void
    {
        $payload = [
            'alert_id' => $alert->getId(),
            'title' => $alert->getTitle(),
            'severity' => $alert->getSeverity(),
            'message' => $message,
            'timestamp' => $alert->getTimestamp(),
            'metric' => $alert->getMetric()?->toArray(),
        ];

        echo "\n🌐 WEBHOOK CALLED\n";
        echo "   URL: {$this->url}\n";
        echo "   Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    }
}

// Simulated Slack Channel
class SlackChannel
{
    public function __construct(private string $webhook, private string $channel)
    {
    }

    public function send(Alert $alert, string $message): void
    {
        $color = match ($alert->getSeverity()) {
            Alert::SEVERITY_CRITICAL => 'danger',
            Alert::SEVERITY_ERROR => 'danger',
            Alert::SEVERITY_WARNING => 'warning',
            default => 'good',
        };

        $blocks = [
            'channel' => $this->channel,
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $alert->getTitle(),
                    'text' => $message,
                    'footer' => 'Alert System',
                    'ts' => (int)$alert->getTimestamp(),
                ],
            ],
        ];

        echo "\n💬 SLACK MESSAGE\n";
        echo "   Channel: {$this->channel}\n";
        echo "   Color: {$color}\n";
        echo "   Title: {$alert->getTitle()}\n";
        echo "   Message: " . substr($message, 0, 80) . "...\n";
    }
}

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    Advanced Alert Agent Example                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$logger = new ColoredLogger();
$alertAgent = new AlertAgent($client, [
    'name' => 'advanced_alert_agent',
    'aggregation_window' => 180, // 3 minutes
    'logger' => $logger,
]);

echo "🔧 Setting up multi-channel alert system...\n\n";

// Configure channels
$emailChannel = new EmailChannel(['to' => 'devops@company.com']);
$webhookChannel = new WebhookChannel('https://api.company.com/alerts');
$slackChannel = new SlackChannel('https://hooks.slack.com/xxx', '#alerts');

// Register channels with routing logic
$alertAgent->registerChannel('email', function (Alert $alert, string $message) use ($emailChannel) {
    // Only send critical and error alerts via email
    if (in_array($alert->getSeverity(), [Alert::SEVERITY_CRITICAL, Alert::SEVERITY_ERROR])) {
        $emailChannel->send($alert, $message);
    }
});

$alertAgent->registerChannel('webhook', function (Alert $alert, string $message) use ($webhookChannel) {
    // Send all alerts to webhook for logging
    $webhookChannel->send($alert, $message);
});

$alertAgent->registerChannel('slack', function (Alert $alert, string $message) use ($slackChannel) {
    // Send warnings and above to Slack
    if ($alert->getSeverity() !== Alert::SEVERITY_INFO) {
        $slackChannel->send($alert, $message);
    }
});

echo "✅ Registered channels: email (critical/error), webhook (all), slack (warning+)\n\n";

// Set custom templates for different severity levels
$alertAgent->setTemplate(
    Alert::SEVERITY_CRITICAL,
    "🚨 CRITICAL ALERT 🚨\n\n" .
    "Title: {title}\n" .
    "Message: {message}\n" .
    "Time: {timestamp}\n" .
    "Metric: {metric_name} = {metric_value}\n\n" .
    "IMMEDIATE ACTION REQUIRED!"
);

$alertAgent->setTemplate(
    Alert::SEVERITY_WARNING,
    "⚠️ Warning: {title}\n\n" .
    "{message}\n\n" .
    "Metric: {metric_name} = {metric_value}\n" .
    "Detected at: {timestamp}"
);

echo "✅ Custom templates configured for CRITICAL and WARNING levels\n\n";

// Scenario 1: System monitoring alerts
echo str_repeat("═", 80) . "\n";
echo "Scenario 1: System Monitoring - Multiple Metrics\n";
echo str_repeat("═", 80) . "\n\n";

$metrics = [
    ['name' => 'cpu_usage', 'value' => 88.5, 'threshold' => 85, 'severity' => Alert::SEVERITY_WARNING],
    ['name' => 'memory_usage', 'value' => 92.0, 'threshold' => 90, 'severity' => Alert::SEVERITY_ERROR],
    ['name' => 'disk_usage', 'value' => 97.5, 'threshold' => 95, 'severity' => Alert::SEVERITY_CRITICAL],
];

foreach ($metrics as $metricData) {
    $metric = new Metric($metricData['name'], $metricData['value'], [
        'host' => 'prod-web-01',
        'region' => 'us-west-2',
    ]);

    if ($metric->exceedsThreshold($metricData['threshold'], '>')) {
        $alert = new Alert(
            title: "High " . ucwords(str_replace('_', ' ', $metricData['name'])),
            message: "{$metricData['name']} exceeded threshold of {$metricData['threshold']}%",
            severity: $metricData['severity'],
            metric: $metric,
            context: ['threshold' => $metricData['threshold'], 'host' => 'prod-web-01']
        );

        $alertAgent->processAlert($alert);
        sleep(1);
    }
}

// Scenario 2: Application performance monitoring
echo "\n" . str_repeat("═", 80) . "\n";
echo "Scenario 2: Application Performance Degradation\n";
echo str_repeat("═", 80) . "\n\n";

$endpoints = [
    ['path' => '/api/users', 'response_time' => 850, 'threshold' => 500],
    ['path' => '/api/orders', 'response_time' => 2500, 'threshold' => 1000],
    ['path' => '/api/checkout', 'response_time' => 5000, 'threshold' => 2000],
];

foreach ($endpoints as $endpoint) {
    $metric = new Metric('response_time', $endpoint['response_time'], [
        'endpoint' => $endpoint['path'],
        'unit' => 'ms',
    ]);

    if ($metric->exceedsThreshold($endpoint['threshold'], '>')) {
        $severity = $endpoint['response_time'] > ($endpoint['threshold'] * 2) 
            ? Alert::SEVERITY_ERROR 
            : Alert::SEVERITY_WARNING;

        $alert = new Alert(
            title: "Slow API Response: {$endpoint['path']}",
            message: "Response time {$endpoint['response_time']}ms exceeds threshold of {$endpoint['threshold']}ms",
            severity: $severity,
            metric: $metric,
            context: [
                'endpoint' => $endpoint['path'],
                'threshold' => $endpoint['threshold'],
                'impact' => 'User experience degradation',
            ]
        );

        $alertAgent->processAlert($alert);
        sleep(1);
    }
}

// Scenario 3: Error rate monitoring with aggregation
echo "\n" . str_repeat("═", 80) . "\n";
echo "Scenario 3: Error Rate Spike (Demonstrates Aggregation)\n";
echo str_repeat("═", 80) . "\n\n";

echo "📊 Sending multiple similar error alerts (should aggregate)...\n\n";

$errorMetric = new Metric('error_rate', 5.5, ['service' => 'payment-api']);

for ($i = 0; $i < 4; $i++) {
    $alert = new Alert(
        title: "Error Rate Spike #{$i}",
        message: "Payment API error rate at {$errorMetric->getValue()}%",
        severity: Alert::SEVERITY_WARNING,
        metric: $errorMetric
    );

    $alertAgent->processAlert($alert);
    usleep(500000); // 0.5 second delay
}

// Scenario 4: Using natural language with run() method
echo "\n" . str_repeat("═", 80) . "\n";
echo "Scenario 4: Natural Language Alert Processing\n";
echo str_repeat("═", 80) . "\n\n";

$nlAlerts = [
    "Info: Scheduled maintenance window starting in 1 hour",
    "Warning: Database connection pool at 85% capacity",
    "Error: Authentication service returning 500 errors",
    "Critical: Primary database server is unresponsive",
];

foreach ($nlAlerts as $nlAlert) {
    echo "Processing: \"{$nlAlert}\"\n";
    $result = $alertAgent->run($nlAlert);
    
    if ($result->isSuccess()) {
        echo "   ✅ Processed successfully\n\n";
    } else {
        echo "   ❌ Failed: {$result->getError()}\n\n";
    }
    sleep(1);
}

// Display statistics
echo "\n" . str_repeat("═", 80) . "\n";
echo "Alert System Statistics\n";
echo str_repeat("═", 80) . "\n\n";

$history = $alertAgent->getSentAlerts();
$stats = [
    'total' => count($history),
    'by_severity' => [],
];

foreach ($history as $entry) {
    $severity = $entry['alert']->getSeverity();
    $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
}

echo "📊 Alert Summary:\n";
echo "   Total alerts sent: {$stats['total']}\n\n";
echo "   By severity:\n";
foreach ($stats['by_severity'] as $severity => $count) {
    $emoji = match ($severity) {
        Alert::SEVERITY_CRITICAL => '🔴',
        Alert::SEVERITY_ERROR => '🟠',
        Alert::SEVERITY_WARNING => '🟡',
        Alert::SEVERITY_INFO => '🔵',
        default => '⚪',
    };
    echo "   {$emoji} " . strtoupper($severity) . ": {$count}\n";
}

echo "\n📜 Recent Alerts:\n";
$recent = array_slice($history, -5);
foreach ($recent as $i => $entry) {
    $alert = $entry['alert'];
    echo "   " . (count($history) - count($recent) + $i + 1) . ". ";
    echo "[{$alert->getSeverity()}] {$alert->getTitle()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Advanced Alert Agent example completed!\n";
echo "\n💡 Key Features Demonstrated:\n";
echo "   • Multi-channel routing with conditional logic\n";
echo "   • Custom message templates per severity\n";
echo "   • Alert aggregation for similar events\n";
echo "   • Metric-based threshold alerting\n";
echo "   • Natural language alert processing\n";
echo "   • Alert history and statistics\n";
echo str_repeat("═", 80) . "\n";

