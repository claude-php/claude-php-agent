<?php

/**
 * Example 3: Instantiate Agents from Templates
 * 
 * This example demonstrates:
 * - Instantiating agents from templates
 * - Passing configuration overrides
 * - Running instantiated agents
 * 
 * NOTE: Requires ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Tools\Tool;

echo "=== Template System: Agent Instantiation ===\n\n";

// Check for API key
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY environment variable not set\n";
    echo "Please set your API key: export ANTHROPIC_API_KEY=your_key_here\n";
    exit(1);
}

$manager = TemplateManager::getInstance();

// Example 1: Instantiate a basic agent
echo "=== Example 1: Basic Agent ===\n";
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->parameter('expression', 'string', 'Math expression to evaluate')
    ->required('expression')
    ->handler(function (array $input): string {
        // Safe evaluation for demo purposes
        $expression = $input['expression'];
        // Only allow basic operations
        if (preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expression)) {
            return (string) eval("return {$expression};");
        }
        return "Invalid expression";
    });

echo "Instantiating Basic Agent...\n";
$agent = $manager->instantiate('Basic Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$calculator]
]);

echo "Running agent: What is 15 * 23?\n";
$result = $agent->run('What is 15 * 23?');
echo "Result: " . $result->getAnswer() . "\n\n";

// Example 2: Instantiate with custom configuration
echo "=== Example 2: ReAct Agent with Custom Config ===\n";
echo "Instantiating ReAct Agent with custom system prompt...\n";
$agent = $manager->instantiate('ReAct Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'system_prompt' => 'You are a friendly math tutor. Help students understand calculations step-by-step.',
    'max_iterations' => 5,
    'tools' => [$calculator]
]);

echo "Running agent: Explain how to calculate 42 divided by 7\n";
$result = $agent->run('Explain how to calculate 42 divided by 7');
echo "Result: " . substr($result->getAnswer(), 0, 200) . "...\n\n";

// Example 3: Instantiate Dialog Agent
echo "=== Example 3: Dialog Agent ===\n";
echo "Instantiating Dialog Agent...\n";
$dialogAgent = $manager->instantiate('Dialog Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

echo "Running conversation...\n";
$result = $dialogAgent->run('Hello! My name is Alice.');
echo "Agent: " . $result->getAnswer() . "\n\n";

// Example 4: Instantiate by template object
echo "=== Example 4: Instantiate from Template Object ===\n";
$template = $manager->getByName('Chain-of-Thought Agent');
echo "Using template: " . $template->getName() . "\n";
echo "Difficulty: " . $template->getMetadata('difficulty') . "\n";

$cotAgent = $manager->instantiateFromTemplate($template, [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

echo "Running agent: If it takes 2 hours to dry 2 shirts, how long does it take to dry 4 shirts?\n";
$result = $cotAgent->run('If it takes 2 hours to dry 2 shirts, how long does it take to dry 4 shirts?');
echo "Result: " . substr($result->getAnswer(), 0, 200) . "...\n\n";

echo "✅ Example completed!\n";
echo "\n💡 Tips:\n";
echo "  - Templates provide sensible defaults\n";
echo "  - Override any config value when instantiating\n";
echo "  - All agent types from the framework are supported\n";
