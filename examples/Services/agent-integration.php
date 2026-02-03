<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Telemetry\TelemetryServiceFactory;
use ClaudeAgents\Services\Tracing\TracingServiceFactory;
use ClaudeAgents\Services\Variable\VariableServiceFactory;
use ClaudeAgents\Services\Variable\VariableType;

/**
 * Agent Integration Example
 *
 * Shows how to integrate services with AI agents for:
 * - Observability (tracing, telemetry)
 * - Configuration management
 * - Secret management
 */

echo "=== Agent with Service Integration ===\n\n";

// Initialize ServiceManager
$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new SettingsServiceFactory(null, [
        'telemetry.enabled' => true,
        'tracing.enabled' => false, // Set to true and configure providers in production
    ]))
    ->registerFactory(new TelemetryServiceFactory())
    ->registerFactory(new TracingServiceFactory())
    ->registerFactory(new VariableServiceFactory());

// Get services
$telemetry = $serviceManager->get(ServiceType::TELEMETRY);
$tracing = $serviceManager->get(ServiceType::TRACING);
$variables = $serviceManager->get(ServiceType::VARIABLE);

// Store API key securely
$variables->setVariable('agent', 'anthropic_api_key', 'sk-ant-...', VariableType::CREDENTIAL);

/**
 * Example Agent Class with Service Integration
 */
class ObservableAgent
{
    private ServiceManager $services;

    public function __construct(ServiceManager $services)
    {
        $this->services = $services;
    }

    public function run(string $prompt): string
    {
        $traceId = uniqid('trace_', true);
        $startTime = microtime(true);

        try {
            // Start tracing
            $tracing = $this->services->get(ServiceType::TRACING);
            $tracing->startTrace($traceId, 'agent.run', [
                'prompt' => substr($prompt, 0, 100),
            ]);

            // Get API key from variables
            $variables = $this->services->get(ServiceType::VARIABLE);
            $apiKey = $variables->getVariable('agent', 'anthropic_api_key');

            // Simulate agent work
            $result = $tracing->recordSpan('llm.call', function () use ($prompt, $apiKey) {
                // In real implementation, call Claude API here
                usleep(50000); // Simulate API call
                return "Processed: {$prompt}";
            });

            // Record metrics
            $duration = (microtime(true) - $startTime) * 1000;
            $telemetry = $this->services->get(ServiceType::TELEMETRY);
            $telemetry->recordAgentRequest(
                success: true,
                tokensInput: 50,
                tokensOutput: 25,
                duration: $duration
            );

            // End tracing
            $tracing->endTrace($traceId, ['result' => $result]);

            return $result;
        } catch (\Throwable $e) {
            // Record error
            $duration = (microtime(true) - $startTime) * 1000;
            $telemetry = $this->services->get(ServiceType::TELEMETRY);
            $telemetry->recordAgentRequest(
                success: false,
                tokensInput: 0,
                tokensOutput: 0,
                duration: $duration,
                error: $e->getMessage()
            );

            throw $e;
        }
    }
}

// Create and run agent
echo "Creating agent with service integration...\n";
$agent = new ObservableAgent($serviceManager);

echo "Running agent tasks...\n";
for ($i = 1; $i <= 3; $i++) {
    $result = $agent->run("Task {$i}: What is 2+2?");
    echo "  Task {$i} result: {$result}\n";
}

// Display telemetry summary
echo "\n--- Telemetry Summary ---\n";
$summary = $telemetry->getSummary();
echo "Total requests: {$summary['total_requests']}\n";
echo "Success rate: " . ($summary['success_rate'] * 100) . "%\n";
echo "Total tokens: {$summary['total_tokens']['total']}\n";
echo "  Input: {$summary['total_tokens']['input']}\n";
echo "  Output: {$summary['total_tokens']['output']}\n";
echo "Average duration: " . round($summary['average_duration_ms'], 2) . "ms\n";

// Display all metrics
echo "\n--- All Metrics ---\n";
$allMetrics = $telemetry->getAllMetrics();
echo "Counters:\n";
foreach ($allMetrics['counters'] as $name => $value) {
    echo "  {$name}: {$value}\n";
}
echo "Histograms:\n";
foreach ($allMetrics['histograms'] as $name => $stats) {
    echo "  {$name}:\n";
    echo "    Count: {$stats['count']}\n";
    echo "    Avg: " . round($stats['avg'], 2) . "\n";
    echo "    Min: " . round($stats['min'], 2) . "\n";
    echo "    Max: " . round($stats['max'], 2) . "\n";
}

// Cleanup
echo "\n--- Cleanup ---\n";
$serviceManager->teardownAll();
echo "✓ Services torn down\n";

echo "\n=== Integration Example Complete ===\n";
