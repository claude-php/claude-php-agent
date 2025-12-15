#!/usr/bin/env php
<?php
/**
 * Event System Example
 *
 * Demonstrates the Observer Pattern (Event System) for decoupled monitoring:
 * - Subscribe to agent lifecycle events
 * - Build custom event listeners
 * - Track metrics and performance
 * - Implement alerting and notifications
 *
 * Run: php examples/event_system_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Events\EventDispatcher;
use ClaudeAgents\Events\{AgentStartedEvent, AgentCompletedEvent, AgentFailedEvent};
use ClaudeAgents\Factory\AgentFactory;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
echo "║                         Event System Example                               ║\n";
echo "║              Observer Pattern for Decoupled Monitoring                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Setup: Create Event Dispatcher
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Step 1: Create Event Dispatcher\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$dispatcher = new EventDispatcher();

echo "✅ Created EventDispatcher\n";
echo "   Ready to subscribe to agent lifecycle events\n\n";

// ============================================================================
// Example 1: Basic Event Listeners
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Basic Event Listeners\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Subscribe to AgentStartedEvent
$dispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "🚀 Event: Agent '{$event->getAgentName()}' started\n";
    echo "   Task: {$event->getTask()}\n";
    echo "   Time: " . date('H:i:s', (int)$event->getTimestamp()) . "\n";
});

// Subscribe to AgentCompletedEvent
$dispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "✅ Event: Agent '{$event->getAgentName()}' completed\n";
    echo "   Duration: " . round($event->getDuration(), 2) . "s\n";
    echo "   Iterations: {$event->getIterations()}\n";
});

// Subscribe to AgentFailedEvent
$dispatcher->listen(AgentFailedEvent::class, function($event) {
    echo "❌ Event: Agent '{$event->getAgentName()}' failed\n";
    echo "   Error: {$event->getError()}\n";
    echo "   Duration: " . round($event->getDuration(), 2) . "s\n";
});

echo "✅ Subscribed to 3 event types\n\n";

// Simulate events
echo "Simulating agent lifecycle:\n\n";

$dispatcher->dispatch(new AgentStartedEvent('demo_agent', 'Process data'));
sleep(1);
$dispatcher->dispatch(new AgentCompletedEvent('demo_agent', duration: 1.0, iterations: 5, result: 'Success'));

echo "\n";

// ============================================================================
// Example 2: Metrics Collection
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Metrics Collection\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

class MetricsCollector {
    private array $metrics = [
        'total_runs' => 0,
        'successful_runs' => 0,
        'failed_runs' => 0,
        'total_duration' => 0.0,
        'total_iterations' => 0,
    ];
    
    public function onAgentStarted(AgentStartedEvent $event): void {
        // Track starts
    }
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['successful_runs']++;
        $this->metrics['total_duration'] += $event->getDuration();
        $this->metrics['total_iterations'] += $event->getIterations();
    }
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $this->metrics['total_runs']++;
        $this->metrics['failed_runs']++;
    }
    
    public function getMetrics(): array {
        return [
            ...$this->metrics,
            'success_rate' => $this->metrics['total_runs'] > 0
                ? $this->metrics['successful_runs'] / $this->metrics['total_runs']
                : 0,
            'avg_duration' => $this->metrics['successful_runs'] > 0
                ? $this->metrics['total_duration'] / $this->metrics['successful_runs']
                : 0,
            'avg_iterations' => $this->metrics['successful_runs'] > 0
                ? $this->metrics['total_iterations'] / $this->metrics['successful_runs']
                : 0,
        ];
    }
    
    public function reset(): void {
        $this->metrics = [
            'total_runs' => 0,
            'successful_runs' => 0,
            'failed_runs' => 0,
            'total_duration' => 0.0,
            'total_iterations' => 0,
        ];
    }
}

$metrics = new MetricsCollector();
$metricsDispatcher = new EventDispatcher();

$metricsDispatcher->listen(AgentStartedEvent::class, [$metrics, 'onAgentStarted']);
$metricsDispatcher->listen(AgentCompletedEvent::class, [$metrics, 'onAgentCompleted']);
$metricsDispatcher->listen(AgentFailedEvent::class, [$metrics, 'onAgentFailed']);

echo "✅ Created MetricsCollector\n\n";

// Simulate multiple agent runs
echo "Simulating 10 agent runs:\n";

for ($i = 1; $i <= 10; $i++) {
    $metricsDispatcher->dispatch(new AgentStartedEvent("agent_{$i}", "Task {$i}"));
    
    $duration = rand(5, 30) / 10;  // 0.5 - 3.0 seconds
    $iterations = rand(3, 8);
    
    // 80% success rate
    if (rand(1, 10) <= 8) {
        $metricsDispatcher->dispatch(new AgentCompletedEvent(
            "agent_{$i}",
            duration: $duration,
            iterations: $iterations,
            result: "Success"
        ));
        echo "  ✓ Agent {$i} completed\n";
    } else {
        $metricsDispatcher->dispatch(new AgentFailedEvent(
            "agent_{$i}",
            error: "Simulated error",
            exception: null,
            duration: $duration
        ));
        echo "  ✗ Agent {$i} failed\n";
    }
}

$stats = $metrics->getMetrics();

echo "\n📊 Collected Metrics:\n";
echo "  • Total Runs: {$stats['total_runs']}\n";
echo "  • Successful: {$stats['successful_runs']}\n";
echo "  • Failed: {$stats['failed_runs']}\n";
echo "  • Success Rate: " . round($stats['success_rate'] * 100, 1) . "%\n";
echo "  • Avg Duration: " . round($stats['avg_duration'], 2) . "s\n";
echo "  • Avg Iterations: " . round($stats['avg_iterations'], 1) . "\n\n";

// ============================================================================
// Example 3: Alert System
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Alert System\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

class AlertSystem {
    private array $failureCount = [];
    private int $threshold = 3;
    
    public function onAgentFailed(AgentFailedEvent $event): void {
        $agent = $event->getAgentName();
        $this->failureCount[$agent] = ($this->failureCount[$agent] ?? 0) + 1;
        
        if ($this->failureCount[$agent] >= $this->threshold) {
            $this->sendAlert($agent, $this->failureCount[$agent]);
        }
    }
    
    public function onAgentCompleted(AgentCompletedEvent $event): void {
        // Reset failure count on success
        $agent = $event->getAgentName();
        if (isset($this->failureCount[$agent])) {
            unset($this->failureCount[$agent]);
        }
        
        // Alert on slow performance
        if ($event->getDuration() > 5.0) {
            $this->sendPerformanceAlert($agent, $event->getDuration());
        }
    }
    
    private function sendAlert(string $agent, int $failures): void {
        echo "🚨 ALERT: Agent '{$agent}' has failed {$failures} times!\n";
    }
    
    private function sendPerformanceAlert(string $agent, float $duration): void {
        echo "⚠️  PERFORMANCE: Agent '{$agent}' took " . round($duration, 2) . "s (threshold: 5s)\n";
    }
}

$alerts = new AlertSystem();
$alertDispatcher = new EventDispatcher();

$alertDispatcher->listen(AgentFailedEvent::class, [$alerts, 'onAgentFailed']);
$alertDispatcher->listen(AgentCompletedEvent::class, [$alerts, 'onAgentCompleted']);

echo "✅ Created AlertSystem (threshold: 3 failures)\n\n";

echo "Simulating failures:\n";

// Simulate repeated failures
for ($i = 1; $i <= 4; $i++) {
    $alertDispatcher->dispatch(new AgentFailedEvent(
        'critical_agent',
        error: "Error #{$i}",
        exception: null,
        duration: 1.0
    ));
    echo "  Failure {$i} recorded\n";
}

echo "\n";

// ============================================================================
// Example 4: Multiple Listeners Per Event
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Multiple Listeners Per Event\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$multiDispatcher = new EventDispatcher();

// Listener 1: Console logging
$multiDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "  [Console] Agent completed in " . round($event->getDuration(), 2) . "s\n";
});

// Listener 2: File logging
$multiDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "  [File] Writing to log: agent_{$event->getAgentName()}.log\n";
});

// Listener 3: Metrics
$multiDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "  [Metrics] Sending to monitoring system\n";
});

// Listener 4: Notifications
$multiDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "  [Notify] Sending completion notification\n";
});

echo "✅ Registered 4 listeners for AgentCompletedEvent\n\n";

echo "Dispatching single event:\n";
$multiDispatcher->dispatch(new AgentCompletedEvent('multi_agent', duration: 2.5, iterations: 4, result: 'Done'));

echo "\n💡 All 4 listeners were called!\n\n";

// ============================================================================
// Example 5: Integration with Real Agent
// ============================================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Integration with Real Agent\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$logger = new Logger('event_demo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$factory = new AgentFactory($client, $logger);
$realDispatcher = new EventDispatcher();

// Set up comprehensive monitoring
$realMetrics = new MetricsCollector();
$realAlerts = new AlertSystem();

$realDispatcher->listen(AgentStartedEvent::class, function($event) {
    echo "🎬 Starting: {$event->getAgentName()}\n";
});

$realDispatcher->listen(AgentCompletedEvent::class, [$realMetrics, 'onAgentCompleted']);
$realDispatcher->listen(AgentCompletedEvent::class, [$realAlerts, 'onAgentCompleted']);
$realDispatcher->listen(AgentCompletedEvent::class, function($event) {
    echo "🎉 Completed: {$event->getAgentName()} in " . round($event->getDuration(), 2) . "s\n";
});

$realDispatcher->listen(AgentFailedEvent::class, [$realMetrics, 'onAgentFailed']);
$realDispatcher->listen(AgentFailedEvent::class, [$realAlerts, 'onAgentFailed']);

echo "✅ Set up monitoring with events\n\n";

// Create and run agent
$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression')
    ->handler(function($input) {
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $input['expression'])) {
            return "Error: Invalid expression";
        }
        try {
            return (string) eval("return {$input['expression']};");
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });

$agent = $factory->create('react', [
    'name' => 'math_agent',
    'max_iterations' => 3,
]);
$agent->withTool($calculator);

echo "Running agent with event monitoring:\n\n";

$realDispatcher->dispatch(new AgentStartedEvent('math_agent', 'Calculate 15 * 23'));

$startTime = microtime(true);
$result = $agent->run('What is 15 multiplied by 23? Use the calculate tool.');
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    $realDispatcher->dispatch(new AgentCompletedEvent(
        'math_agent',
        duration: $duration,
        iterations: $result->getIterations(),
        result: $result->getAnswer()
    ));
    echo "\nResult: " . substr($result->getAnswer(), 0, 100) . "...\n";
} else {
    $realDispatcher->dispatch(new AgentFailedEvent(
        'math_agent',
        error: $result->getError(),
        exception: null,
        duration: $duration
    ));
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================

echo str_repeat("═", 80) . "\n";
echo "📚 Event System Summary\n";
echo str_repeat("═", 80) . "\n\n";

echo "✅ Benefits:\n\n";

echo "1. Decoupling:\n";
echo "   • Agents don't know about observers\n";
echo "   • Add monitoring without modifying agents\n";
echo "   • Separation of concerns\n\n";

echo "2. Extensibility:\n";
echo "   • Add new listeners anytime\n";
echo "   • Multiple listeners per event\n";
echo "   • Remove listeners dynamically\n\n";

echo "3. Flexibility:\n";
echo "   • Subscribe to specific events\n";
echo "   • Build custom event handlers\n";
echo "   • Runtime configuration\n\n";

echo "4. Observability:\n";
echo "   • Real-time execution tracking\n";
echo "   • Comprehensive metrics\n";
echo "   • Performance monitoring\n\n";

echo "🎯 Use Cases:\n\n";
echo "• Performance monitoring\n";
echo "• Metrics collection\n";
echo "• Alerting and notifications\n";
echo "• Audit logging\n";
echo "• Real-time dashboards\n";
echo "• Debugging and diagnostics\n";
echo "• A/B testing\n";
echo "• Cost tracking\n\n";

echo "📊 Event Types:\n\n";
echo "• AgentStartedEvent: Agent begins execution\n";
echo "• AgentCompletedEvent: Agent finishes successfully\n";
echo "• AgentFailedEvent: Agent encounters error\n\n";

echo "📖 Learn More:\n";
echo "• Design Patterns Guide: docs/DesignPatterns.md\n";
echo "• Events Documentation: docs/Events.md\n";
echo "• Complete Demo: examples/design_patterns_demo.php\n";
echo "• Best Practices: docs/BestPractices.md\n\n";

echo str_repeat("═", 80) . "\n";
echo "✨ Event System Example Complete!\n";
echo str_repeat("═", 80) . "\n";

