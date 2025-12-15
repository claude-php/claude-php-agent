#!/usr/bin/env php
<?php
/**
 * Advanced Coordinator Agent Example
 *
 * Demonstrates advanced coordination patterns including:
 * - Dynamic agent registration
 * - Complex task routing
 * - Performance optimization
 * - Multi-stage workflows
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;

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
echo "║               Advanced Coordinator Agent Example                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

/**
 * Custom agent that tracks its own metrics
 */
class TrackedWorkerAgent implements AgentInterface
{
    private string $name;
    private WorkerAgent $worker;
    private array $taskHistory = [];

    public function __construct(ClaudePhp $client, string $name, string $specialty, string $system)
    {
        $this->name = $name;
        $this->worker = new WorkerAgent($client, [
            'name' => $name,
            'specialty' => $specialty,
            'system' => $system,
        ]);
    }

    public function run(string $task): AgentResult
    {
        $startTime = microtime(true);
        $result = $this->worker->run($task);
        $duration = microtime(true) - $startTime;

        $this->taskHistory[] = [
            'task' => substr($task, 0, 50) . '...',
            'duration' => $duration,
            'success' => $result->isSuccess(),
            'timestamp' => time(),
        ];

        return $result;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTaskHistory(): array
    {
        return $this->taskHistory;
    }

    public function getStats(): array
    {
        $total = count($this->taskHistory);
        $successful = count(array_filter($this->taskHistory, fn($t) => $t['success']));
        $avgDuration = $total > 0 
            ? array_sum(array_column($this->taskHistory, 'duration')) / $total 
            : 0;

        return [
            'total_tasks' => $total,
            'successful_tasks' => $successful,
            'failed_tasks' => $total - $successful,
            'average_duration' => round($avgDuration, 3),
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0,
        ];
    }
}

// Create coordinator
$coordinator = new CoordinatorAgent($client, [
    'name' => 'advanced_coordinator',
]);

// Create tracked agents
$agents = [
    'backend_dev' => new TrackedWorkerAgent(
        $client,
        'backend_dev',
        'backend development, API design, database optimization',
        'You are a backend development expert specializing in APIs and databases.'
    ),
    'frontend_dev' => new TrackedWorkerAgent(
        $client,
        'frontend_dev',
        'frontend development, UI/UX, React, Vue, CSS',
        'You are a frontend development expert specializing in modern web frameworks.'
    ),
    'devops' => new TrackedWorkerAgent(
        $client,
        'devops',
        'DevOps, CI/CD, cloud infrastructure, Docker, Kubernetes',
        'You are a DevOps engineer expert in cloud infrastructure and automation.'
    ),
    'security' => new TrackedWorkerAgent(
        $client,
        'security',
        'security, penetration testing, vulnerability assessment',
        'You are a security expert specializing in application security and threat assessment.'
    ),
];

// Register agents with detailed capabilities
$coordinator->registerAgent('backend_dev', $agents['backend_dev'], [
    'backend', 'api', 'database', 'server', 'coding', 'implementation'
]);

$coordinator->registerAgent('frontend_dev', $agents['frontend_dev'], [
    'frontend', 'ui', 'ux', 'interface', 'design', 'react', 'vue'
]);

$coordinator->registerAgent('devops', $agents['devops'], [
    'devops', 'deployment', 'infrastructure', 'ci/cd', 'docker', 'kubernetes'
]);

$coordinator->registerAgent('security', $agents['security'], [
    'security', 'testing', 'vulnerability', 'penetration', 'audit'
]);

echo "Scenario: Building a Secure E-Commerce Platform\n";
echo str_repeat("=", 80) . "\n\n";

// Complex workflow with multiple stages
$workflow = [
    'Phase 1: Architecture & Design' => [
        'Design a RESTful API for product catalog management',
        'Design the database schema for users, products, and orders',
        'Plan the frontend architecture using React',
    ],
    'Phase 2: Implementation' => [
        'Implement user authentication with JWT tokens',
        'Create a responsive product listing page with filters',
        'Set up CI/CD pipeline for automated deployments',
    ],
    'Phase 3: Security & Testing' => [
        'Perform security audit on the authentication system',
        'Test the API for SQL injection vulnerabilities',
        'Review Docker container security configuration',
    ],
];

foreach ($workflow as $phase => $tasks) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "{$phase}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    foreach ($tasks as $i => $task) {
        $taskNum = $i + 1;
        echo "[{$taskNum}] {$task}\n";

        $result = $coordinator->run($task);

        if ($result->isSuccess()) {
            $metadata = $result->getMetadata();
            echo "    ✓ Assigned to: {$metadata['delegated_to']}\n";
            echo "    ⏱ Duration: " . round($metadata['duration'], 3) . "s\n";
            echo "    📋 Result: " . substr($result->getAnswer(), 0, 100) . "...\n";
        } else {
            echo "    ✗ Failed: {$result->getError()}\n";
        }
        
        echo "\n";
    }
}

// Detailed analytics
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Detailed Analytics\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1. Workload Distribution:\n";
$workload = $coordinator->getWorkload();
arsort($workload);
foreach ($workload as $agent => $load) {
    $percentage = ($load / max(array_sum($workload), 1)) * 100;
    echo sprintf("   %-15s: %2d tasks (%.1f%%)\n", $agent, $load, $percentage);
}

echo "\n2. Agent Performance:\n";
foreach ($agents as $name => $agent) {
    $stats = $agent->getStats();
    if ($stats['total_tasks'] > 0) {
        echo "   {$name}:\n";
        echo "     • Success rate: {$stats['success_rate']}%\n";
        echo "     • Avg duration: {$stats['average_duration']}s\n";
        echo "     • Total tasks: {$stats['total_tasks']}\n";
    }
}

echo "\n3. Coordinator Performance:\n";
$coordPerf = $coordinator->getPerformance();
foreach ($coordPerf as $agentId => $perf) {
    if ($perf['total_tasks'] > 0) {
        $successRate = ($perf['successful_tasks'] / $perf['total_tasks']) * 100;
        echo sprintf(
            "   %-15s: %d/%d tasks (%.1f%% success, avg %.3fs)\n",
            $agentId,
            $perf['successful_tasks'],
            $perf['total_tasks'],
            $successRate,
            $perf['average_duration']
        );
    }
}

echo "\n4. Task History Sample:\n";
foreach ($agents as $name => $agent) {
    $history = $agent->getTaskHistory();
    if (!empty($history)) {
        echo "   {$name} - Last task:\n";
        $last = end($history);
        echo "     • Task: {$last['task']}\n";
        echo "     • Duration: " . round($last['duration'], 3) . "s\n";
        echo "     • Status: " . ($last['success'] ? '✓ Success' : '✗ Failed') . "\n";
    }
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Advanced coordinator example completed!\n\n";

echo "Key Features Demonstrated:\n";
echo "  ✓ Custom agent implementations with tracking\n";
echo "  ✓ Multi-phase workflow coordination\n";
echo "  ✓ Detailed performance analytics\n";
echo "  ✓ Load distribution optimization\n";
echo "  ✓ Per-agent task history\n";
echo "  ✓ Real-world project simulation\n";

