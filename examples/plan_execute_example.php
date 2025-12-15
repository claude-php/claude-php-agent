#!/usr/bin/env php
<?php
/**
 * Plan-Execute Agent Example
 *
 * Demonstrates the plan-and-execute pattern for systematic task completion.
 * The agent first creates a detailed plan, then executes each step, with optional
 * plan revision based on results.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Tools\Tool;
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
echo "║                    Plan-Execute Agent Example                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Simple Planning Without Tools
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Simple Task Planning\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$simpleAgent = new PlanExecuteAgent($client, [
    'name' => 'simple_planner',
    'allow_replan' => false,
]);

$task1 = "Write a brief product description for a new eco-friendly water bottle. " .
         "It should highlight the key features, target audience, and environmental benefits.";

echo "Task: {$task1}\n\n";
echo "Running plan-execute agent...\n\n";

$result = $simpleAgent->run($task1);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Execution Stats:\n";
    echo "  • Plan Steps: {$metadata['plan_steps']}\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens: {$usage['total']} total ({$usage['input']} input, {$usage['output']} output)\n\n";
    
    // Show step breakdown
    if (isset($metadata['step_results'])) {
        echo "📝 Step-by-Step Results:\n";
        foreach ($metadata['step_results'] as $step) {
            echo "  Step {$step['step']}: {$step['description']}\n";
            echo "    → " . substr($step['result'], 0, 70) . "...\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo str_repeat("═", 80) . "\n\n";

// Example 2: Planning with Tools
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Task Planning with Tools\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create some useful tools
$calculatorTool = Tool::create('calculator')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'The mathematical expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Simple safe evaluation for basic math
        $expr = preg_replace('/[^0-9+\-*\/\(\)\.\s]/', '', $expr);
        
        try {
            $result = eval("return {$expr};");
            return "Result: " . $result;
        } catch (\Throwable $e) {
            return "Error: Invalid expression";
        }
    });

$dateTool = Tool::create('get_date')
    ->description('Get the current date and time')
    ->stringParam('format', 'The date format (e.g., "Y-m-d H:i:s")', required: false)
    ->handler(function (array $input): string {
        $format = $input['format'] ?? 'Y-m-d H:i:s';
        return date($format);
    });

$textAnalysisTool = Tool::create('text_analysis')
    ->description('Analyze text and provide word count, character count, and sentence count')
    ->stringParam('text', 'The text to analyze')
    ->handler(function (array $input): string {
        $text = $input['text'];
        
        $wordCount = str_word_count($text);
        $charCount = strlen($text);
        $sentenceCount = preg_match_all('/[.!?]+/', $text, $matches);
        
        return "Analysis:\n" .
               "- Words: {$wordCount}\n" .
               "- Characters: {$charCount}\n" .
               "- Sentences: {$sentenceCount}";
    });

$plannerWithTools = new PlanExecuteAgent($client, [
    'name' => 'tool_planner',
    'allow_replan' => true,
]);

$plannerWithTools
    ->addTool($calculatorTool)
    ->addTool($dateTool)
    ->addTool($textAnalysisTool);

$task2 = "Calculate the total cost of hosting a small event: " .
         "Venue rental $500, catering for 20 people at $25 per person, " .
         "decorations $150. Then analyze the final summary text.";

echo "Task: {$task2}\n\n";
echo "Running plan-execute agent with tools...\n\n";

$result = $plannerWithTools->run($task2);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Execution Stats:\n";
    echo "  • Plan Steps: {$metadata['plan_steps']}\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens: {$usage['total']} total\n\n";
    
    // Show step breakdown
    if (isset($metadata['step_results'])) {
        echo "📝 Step-by-Step Execution:\n";
        foreach ($metadata['step_results'] as $step) {
            echo "\n  Step {$step['step']}: {$step['description']}\n";
            $lines = explode("\n", $step['result']);
            foreach (array_slice($lines, 0, 3) as $line) {
                echo "    " . substr($line, 0, 76) . "\n";
            }
            if (count($lines) > 3) {
                echo "    ...\n";
            }
        }
        echo "\n";
    }
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo str_repeat("═", 80) . "\n\n";

// Example 3: Complex Multi-Step Planning
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Complex Multi-Step Planning\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$complexPlanner = new PlanExecuteAgent($client, [
    'name' => 'complex_planner',
    'max_tokens' => 4096,
    'allow_replan' => true,
]);

$task3 = "Create a comprehensive content strategy for launching a new tech blog. " .
         "Include: target audience definition, content pillars (3-4 topics), " .
         "posting frequency recommendation, and 5 initial blog post titles with brief descriptions.";

echo "Task: {$task3}\n\n";
echo "Running complex planning task...\n\n";

$result = $complexPlanner->run($task3);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Execution Summary:\n";
    echo "  • Total Steps in Plan: {$metadata['plan_steps']}\n";
    echo "  • Total Iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Total Tokens: {$usage['total']}\n";
    echo "  • Cost Estimate: $" . number_format(($usage['input'] * 0.003 + $usage['output'] * 0.015) / 1000, 4) . "\n\n";
    
    echo "📋 Plan Execution Details:\n";
    if (isset($metadata['step_results'])) {
        foreach ($metadata['step_results'] as $step) {
            echo "  {$step['step']}. {$step['description']}\n";
        }
    }
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// Example 4: Plan-Execute with Replanning
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Plan-Execute with Adaptive Replanning\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$adaptivePlanner = new PlanExecuteAgent($client, [
    'name' => 'adaptive_planner',
    'allow_replan' => true, // Enable replanning if steps fail or need adjustment
]);

$task4 = "Design a simple home workout routine for a beginner who wants to improve fitness. " .
         "Consider: no equipment needed, 30 minutes per day, 5 days a week. " .
         "Include warm-up, main exercises, and cool-down.";

echo "Task: {$task4}\n\n";
echo "Running with adaptive replanning enabled...\n";
echo "(Agent can revise plan if needed based on step results)\n\n";

$result = $adaptivePlanner->run($task4);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Stats:\n";
    echo "  • Original Plan Steps: {$metadata['plan_steps']}\n";
    echo "  • Actual Steps Executed: " . count($metadata['step_results']) . "\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    
    if (count($metadata['step_results']) > $metadata['plan_steps']) {
        echo "  ⚠️  Plan was adapted during execution!\n";
    }
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens Used: {$usage['total']}\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Plan-Execute agent examples completed!\n\n";

echo "💡 Key Takeaways:\n";
echo "  • Plan-Execute separates planning from execution for systematic completion\n";
echo "  • Works well with or without tools\n";
echo "  • Enable replanning for adaptive behavior based on step results\n";
echo "  • Great for multi-step tasks requiring coordination\n";
echo "  • Each step gets context from previous steps\n";
echo "  • Final synthesis provides comprehensive answer\n\n";

