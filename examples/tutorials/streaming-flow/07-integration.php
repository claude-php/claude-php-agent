<?php

/**
 * Tutorial 7: Integration with Existing Systems
 * 
 * Learn how to:
 * - Integrate streaming with existing agents
 * - Use with StreamingLoop
 * - Combine with other services
 * - Build complete applications
 * 
 * Estimated time: 20 minutes
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\AgentContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use ClaudeAgents\Services\Telemetry\TelemetryServiceFactory;
use ClaudeAgents\Services\Execution\FlowEventManagerServiceFactory;
use ClaudeAgents\Services\Execution\StreamingFlowExecutorServiceFactory;
use ClaudeAgents\Streaming\StreamingLoop;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "==========================================\n";
echo "Tutorial 7: Integration Example\n";
echo "==========================================\n\n";

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

// Step 1: Setup complete service infrastructure
echo "Step 1: Setting up service infrastructure\n";
echo "------------------------------------------\n";

$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new TelemetryServiceFactory())
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());

echo "✅ Service manager configured with:\n";
echo "   - Cache service\n";
echo "   - Telemetry service\n";
echo "   - Flow event manager\n";
echo "   - Streaming executor\n\n";

// Step 2: Get services
echo "Step 2: Getting services\n";
echo "------------------------\n";

$cache = $serviceManager->get(ServiceType::CACHE);
$telemetry = $serviceManager->get(ServiceType::TELEMETRY);
$eventManager = $serviceManager->get(ServiceType::EVENT_MANAGER);
$executor = $serviceManager->get(ServiceType::FLOW_EXECUTOR);

echo "✅ All services initialized\n\n";

// Step 3: Integrate with StreamingLoop
echo "Step 3: Integrating with StreamingLoop\n";
echo "---------------------------------------\n";

$client = new ClaudePhp(apiKey: $apiKey);
$config = new AgentConfig(
    model: 'claude-3-5-sonnet-20241022',
    maxTokens: 512,
    maxIterations: 3
);

// Create StreamingLoop with event manager
$loop = new StreamingLoop();
$loop->setFlowEventManager($eventManager);

echo "✅ StreamingLoop configured with FlowEventManager\n";
echo "   Now emits flow events automatically:\n";
echo "   - Iteration events\n";
echo "   - Token events\n";
echo "   - Tool execution events\n\n";

// Step 4: Add telemetry tracking
echo "Step 4: Adding telemetry tracking\n";
echo "----------------------------------\n";

$eventManager->subscribe(function(FlowEvent $event) use ($telemetry) {
    global $telemetryTracker;
    
    // Track different event types
    if ($event->isToken()) {
        $telemetry->recordCounter('streaming.tokens');
    } elseif ($event->type === FlowEvent::ITERATION_COMPLETED) {
        $telemetry->recordCounter('streaming.iterations');
        if (isset($event->data['tokens']['output'])) {
            $telemetry->recordHistogram(
                'streaming.tokens_per_iteration',
                $event->data['tokens']['output']
            );
        }
    } elseif ($event->type === FlowEvent::TOOL_EXECUTION_COMPLETED) {
        $telemetry->recordCounter('streaming.tool_calls');
    }
});

echo "✅ Telemetry listener registered\n\n";

// Step 5: Add caching integration
echo "Step 5: Adding cache integration\n";
echo "---------------------------------\n";

$cacheKey = 'last_execution_result';

// Check cache
$cachedResult = $cache->get($cacheKey);
if ($cachedResult !== null) {
    echo "📦 Found cached result from previous execution\n";
    echo "   Output: {$cachedResult}\n";
    echo "   Use cache? (This is a tutorial, so we'll run fresh)\n\n";
}

echo "✅ Cache integration ready\n\n";

// Step 6: Create agent with tools
echo "Step 6: Creating integrated agent\n";
echo "----------------------------------\n";

$mathTool = Tool::create('calculate')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression', true)
    ->handler(function (array $input) use ($cache, $telemetry): string {
        // Cache the calculation
        $cacheKey = 'calc_' . md5($input['expression']);
        
        if ($cached = $cache->get($cacheKey)) {
            $telemetry->recordCounter('cache.hits');
            return "Cached result: {$cached}";
        }
        
        $telemetry->recordCounter('cache.misses');
        $result = eval("return {$input['expression']};");
        $cache->set($cacheKey, $result, 300); // Cache for 5 minutes
        
        return "Result: {$result}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$mathTool]);

echo "✅ Agent created with cache-integrated tool\n\n";

// Step 7: Execute with full integration
echo "Step 7: Executing with full integration\n";
echo "----------------------------------------\n";

$task = "Calculate 15 * 8 + 45";
echo "Task: {$task}\n\n";

$startTime = microtime(true);

try {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        switch ($event['type']) {
            case 'flow_started':
                echo "🚀 Integrated execution started\n\n";
                break;
            case 'token':
                print($event['data']['token']);
                break;
            case 'tool_start':
                printf("\n🔧 Tool: %s\n", $event['data']['tool']);
                break;
            case 'tool_end':
                printf("   Result: %s\n", $event['data']['result']);
                break;
            case 'end':
                echo "\n\n✅ Execution completed\n";
                break;
        }
    }
    
    $duration = round(microtime(true) - $startTime, 2);
    
    // Cache the final result
    $progress = $executor->getCurrentProgress();
    if ($progress) {
        $cache->set($cacheKey, [
            'duration' => $duration,
            'metadata' => $progress,
        ], 3600);
        echo "📦 Result cached for future use\n";
    }

} catch (Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
}

// Step 8: Display integrated statistics
echo "\n";
echo "Step 8: Integrated Statistics\n";
echo "------------------------------\n\n";

// Telemetry summary
$telemetrySummary = $telemetry->getSummary();
echo "Telemetry Metrics:\n";
foreach ($telemetrySummary as $metric => $data) {
    if (str_starts_with($metric, 'streaming.')) {
        $count = $data['count'] ?? 0;
        echo "  {$metric}: {$count}\n";
    }
}
echo "\n";

// Cache statistics
echo "Cache Statistics:\n";
echo "  Cache backend: " . get_class($cache) . "\n";
echo "\n";

// Event statistics
$eventQueue = $eventManager->getQueue();
$queueStats = $eventQueue->getStats();
echo "Event Queue:\n";
echo "  Events in queue: {$queueStats['size']}\n";
echo "  Utilization: " . round($queueStats['utilization'], 1) . "%\n";
echo "\n";

// Error summary
if (!empty($errorLog)) {
    echo "Errors Logged:\n";
    foreach ($errorLog as $error) {
        echo "  - {$error['message']}\n";
    }
} else {
    echo "✅ No errors occurred\n";
}

echo "\n";
echo "==========================================\n";
echo "Tutorial Complete! ✅\n";
echo "==========================================\n\n";

echo "What you learned:\n";
echo "- How to integrate streaming with ServiceManager\n";
echo "- How to use StreamingLoop with FlowEventManager\n";
echo "- How to combine with cache and telemetry services\n";
echo "- How to build complete integrated applications\n";
echo "- How to track metrics across the entire stack\n\n";

echo "Congratulations! You've completed the Streaming Flow tutorial series!\n\n";

echo "Next steps:\n";
echo "- Read the comprehensive docs: docs/execution/README.md\n";
echo "- Explore advanced patterns: docs/execution/STREAMING.md\n";
echo "- Review the event reference: docs/execution/EVENTS.md\n";
echo "- Try the advanced examples in examples/Execution/\n";
