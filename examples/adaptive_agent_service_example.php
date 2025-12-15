<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\RAGAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment variables
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

echo "=================================================================\n";
echo "Adaptive Agent Service Example\n";
echo "=================================================================\n\n";

echo "This example demonstrates the Adaptive Agent Service which:\n";
echo "1. Analyzes tasks to understand requirements\n";
echo "2. Selects the best agent for the job\n";
echo "3. Validates results for quality and correctness\n";
echo "4. Adapts by trying different agents or reframing requests\n\n";

// Initialize Claude client
$client = ClaudePhp::make($apiKey);

// =================================================================
// Setup: Create various agents with different capabilities
// =================================================================

echo "Setting up agents...\n\n";

// 1. React Agent - Good for general tasks with tools
$calculatorTool = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Mathematical expression to evaluate')
    ->handler(function (array $input): string {
        try {
            // Simple safe evaluation for demo purposes
            $expr = $input['expression'];
            // Remove any non-math characters for safety
            $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $expr);
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    });

$reactAgent = new ReactAgent($client, [
    'tools' => [$calculatorTool],
    'max_iterations' => 5,
    'system' => 'You are a helpful assistant with access to tools.',
]);

// 2. Reflection Agent - Good for quality-critical tasks
$reflectionAgent = new ReflectionAgent($client, [
    'max_refinements' => 2,
    'quality_threshold' => 7,
]);

// 3. Chain of Thought Agent - Good for reasoning tasks
$cotAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

// 4. RAG Agent - Good for knowledge-based tasks
$ragAgent = new RAGAgent($client);
$ragAgent->addDocument(
    'PHP Basics',
    'PHP is a server-side scripting language. Variables start with $. ' .
        'Functions are defined with function keyword. Classes use class keyword.'
);
$ragAgent->addDocument(
    'Web Development',
    'Modern web development uses frameworks like Laravel, Symfony. ' .
        'REST APIs are common. JSON is the standard data format.'
);

// =================================================================
// Setup: Create the Adaptive Agent Service
// =================================================================

echo "Creating Adaptive Agent Service...\n\n";

$adaptiveService = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 7.0,
    'enable_reframing' => true,
]);

// Register agents with their profiles
$adaptiveService->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'strengths' => ['tool usage', 'iterative problem solving'],
    'best_for' => ['calculations', 'API calls', 'multi-step tasks'],
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

$adaptiveService->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'strengths' => ['quality refinement', 'self-improvement'],
    'best_for' => ['writing', 'code generation', 'critical outputs'],
    'complexity_level' => 'medium',
    'speed' => 'slow',
    'quality' => 'high',
]);

$adaptiveService->registerAgent('chain_of_thought', $cotAgent, [
    'type' => 'cot',
    'strengths' => ['reasoning', 'step-by-step logic'],
    'best_for' => ['math problems', 'logical reasoning', 'explanations'],
    'complexity_level' => 'medium',
    'speed' => 'fast',
    'quality' => 'standard',
]);

$adaptiveService->registerAgent('rag', $ragAgent, [
    'type' => 'rag',
    'strengths' => ['knowledge grounding', 'source attribution'],
    'best_for' => ['Q&A', 'documentation queries', 'fact-based tasks'],
    'complexity_level' => 'simple',
    'speed' => 'fast',
    'quality' => 'high',
]);

echo "Registered agents: " . implode(', ', $adaptiveService->getRegisteredAgents()) . "\n\n";

// =================================================================
// Example 1: Math Problem (Should select React or CoT agent)
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 1: Mathematical Calculation\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$task1 = "Calculate the result of (25 * 17) + (100 / 4) - 12";
echo "Task: {$task1}\n\n";

$result1 = $adaptiveService->run($task1);

if ($result1->isSuccess()) {
    echo "✓ SUCCESS\n\n";
    echo "Answer: {$result1->getAnswer()}\n\n";

    $metadata = $result1->getMetadata();
    echo "Selected Agent: {$metadata['final_agent']}\n";
    echo "Quality Score: {$metadata['final_quality']}/10\n";
    echo "Attempts: {$result1->getIterations()}\n";
    echo "Duration: {$metadata['total_duration']}s\n\n";

    if (!empty($metadata['attempts'])) {
        echo "Attempt History:\n";
        foreach ($metadata['attempts'] as $attempt) {
            echo "  - Attempt {$attempt['attempt']}: {$attempt['agent_type']} agent ";
            echo "(quality: {$attempt['validation']['quality_score']}/10)\n";
        }
    }
} else {
    echo "✗ FAILED: {$result1->getError()}\n";
}

echo "\n";

// =================================================================
// Example 2: Knowledge Question (Should select RAG agent)
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 2: Knowledge-Based Question\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$task2 = "What are the key features of PHP and how do you define variables?";
echo "Task: {$task2}\n\n";

$result2 = $adaptiveService->run($task2);

if ($result2->isSuccess()) {
    echo "✓ SUCCESS\n\n";
    echo "Answer: " . substr($result2->getAnswer(), 0, 200) . "...\n\n";

    $metadata = $result2->getMetadata();
    echo "Selected Agent: {$metadata['final_agent']}\n";
    echo "Quality Score: {$metadata['final_quality']}/10\n";
    echo "Attempts: {$result2->getIterations()}\n";
} else {
    echo "✗ FAILED: {$result2->getError()}\n";
}

echo "\n";

// =================================================================
// Example 3: Complex Reasoning (Should select CoT agent)
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 3: Logical Reasoning Problem\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$task3 = "If all bloops are razzies and all razzies are lazzies, are all bloops definitely lazzies?";
echo "Task: {$task3}\n\n";

$result3 = $adaptiveService->run($task3);

if ($result3->isSuccess()) {
    echo "✓ SUCCESS\n\n";
    echo "Answer: {$result3->getAnswer()}\n\n";

    $metadata = $result3->getMetadata();
    echo "Selected Agent: {$metadata['final_agent']}\n";
    echo "Quality Score: {$metadata['final_quality']}/10\n";
} else {
    echo "✗ FAILED: {$result3->getError()}\n";
}

echo "\n";

// =================================================================
// Example 4: Quality-Critical Task (Should select Reflection agent)
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 4: Quality-Critical Writing Task\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$task4 = "Write a professional email to a client explaining a project delay, showing empathy and proposing solutions.";
echo "Task: {$task4}\n\n";

$result4 = $adaptiveService->run($task4);

if ($result4->isSuccess()) {
    echo "✓ SUCCESS\n\n";
    echo "Answer Preview:\n";
    echo substr($result4->getAnswer(), 0, 300) . "...\n\n";

    $metadata = $result4->getMetadata();
    echo "Selected Agent: {$metadata['final_agent']}\n";
    echo "Quality Score: {$metadata['final_quality']}/10\n";
    echo "Attempts: {$result4->getIterations()}\n";
} else {
    echo "✗ FAILED: {$result4->getError()}\n";
}

echo "\n";

// =================================================================
// Performance Summary
// =================================================================

echo "=================================================================\n";
echo "Performance Summary\n";
echo "=================================================================\n\n";

$performance = $adaptiveService->getPerformance();

foreach ($performance as $agentId => $stats) {
    if ($stats['attempts'] > 0) {
        $successRate = round(($stats['successes'] / $stats['attempts']) * 100, 1);
        $avgQuality = round($stats['average_quality'], 1);
        $avgDuration = round($stats['total_duration'] / $stats['attempts'], 2);

        echo "{$agentId} Agent:\n";
        echo "  Attempts: {$stats['attempts']}\n";
        echo "  Success Rate: {$successRate}%\n";
        echo "  Average Quality: {$avgQuality}/10\n";
        echo "  Average Duration: {$avgDuration}s\n\n";
    }
}

// =================================================================
// Key Takeaways
// =================================================================

echo "=================================================================\n";
echo "Key Features Demonstrated:\n";
echo "=================================================================\n\n";

echo "✓ Intelligent agent selection based on task requirements\n";
echo "✓ Automatic quality validation of results\n";
echo "✓ Adaptive retry with different agents if quality is low\n";
echo "✓ Task reframing to improve results\n";
echo "✓ Performance tracking across agents\n";
echo "✓ Learning from successes and failures\n\n";

echo "This service acts as a meta-layer that ensures you always get\n";
echo "the best possible result by selecting the right agent and\n";
echo "validating output quality!\n\n";
