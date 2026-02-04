<?php

/**
 * Tutorial 2: Instantiating Agents from Templates
 * 
 * Learn to create live agents from templates.
 * 
 * Time: ~10 minutes
 * Level: Beginner
 * 
 * Topics:
 * - Basic instantiation
 * - Configuration overrides
 * - Adding tools to templated agents
 * - Working with different agent types
 * - Running instantiated agents
 * 
 * Prerequisites:
 * - ANTHROPIC_API_KEY environment variable set
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Tools\Tool;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 2: Instantiating Agents                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Check prerequisites
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY environment variable not set\n";
    echo "   Please set it: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

$manager = TemplateManager::getInstance();

// Step 1: Basic Instantiation
echo "Step 1: Basic Instantiation\n";
echo str_repeat('─', 80) . "\n";

echo "Creating a calculator tool...\n";
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->parameter('expression', 'string', 'Math expression to evaluate')
    ->required('expression')
    ->handler(function (array $input): string {
        $expression = $input['expression'];
        if (preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expression)) {
            return (string) eval("return {$expression};");
        }
        return "Invalid expression";
    });
echo "✓ Calculator tool created\n\n";

echo "Instantiating 'Basic Agent' template...\n";
$agent = $manager->instantiate('Basic Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$calculator]
]);
echo "✓ Agent instantiated successfully\n";
echo "  Type: " . get_class($agent) . "\n\n";

echo "Running agent with simple calculation...\n";
$result = $agent->run('What is 25 × 17?');
echo "✓ Agent completed\n";
echo "  Result: {$result->getAnswer()}\n\n";

// Step 2: Configuration Overrides
echo "Step 2: Configuration Overrides\n";
echo str_repeat('─', 80) . "\n";

echo "Instantiating 'ReAct Agent' with custom configuration...\n";
$reactAgent = $manager->instantiate('ReAct Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'system_prompt' => 'You are a friendly math tutor who explains step-by-step.',
    'max_iterations' => 5,  // Override default
    'tools' => [$calculator]
]);
echo "✓ Agent instantiated with custom config\n\n";

echo "Running agent with teaching request...\n";
$result = $reactAgent->run('Explain how to calculate 144 ÷ 12');
echo "✓ Agent completed\n";
echo "  Result preview: " . substr($result->getAnswer(), 0, 150) . "...\n\n";

// Step 3: Different Agent Types
echo "Step 3: Working with Different Agent Types\n";
echo str_repeat('─', 80) . "\n";

echo "a) Dialog Agent (Conversational)\n";
$dialogAgent = $manager->instantiate('Dialog Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);
echo "   ✓ Dialog Agent instantiated\n";

$result = $dialogAgent->run('Hi! My name is Alice.');
echo "   Agent: " . substr($result->getAnswer(), 0, 100) . "...\n\n";

echo "b) Chain-of-Thought Agent (Reasoning)\n";
$cotAgent = $manager->instantiate('Chain-of-Thought Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);
echo "   ✓ Chain-of-Thought Agent instantiated\n";

$result = $cotAgent->run('A farmer has 17 sheep. All but 9 die. How many are left?');
echo "   Result preview: " . substr($result->getAnswer(), 0, 100) . "...\n\n";

// Step 4: Instantiate from Template Object
echo "Step 4: Instantiate from Template Object\n";
echo str_repeat('─', 80) . "\n";

echo "Finding 'Reflex Agent' template...\n";
$template = $manager->getByName('Reflex Agent');
echo "✓ Template found\n";
echo "  Name: {$template->getName()}\n";
echo "  Category: {$template->getCategory()}\n";
echo "  Difficulty: {$template->getMetadata('difficulty')}\n\n";

echo "Instantiating from template object...\n";
$reflexAgent = $manager->instantiateFromTemplate($template, [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);
echo "✓ Agent instantiated from template object\n";
echo "  Type: " . get_class($reflexAgent) . "\n\n";

// Step 5: Multiple Tools
echo "Step 5: Adding Multiple Tools\n";
echo str_repeat('─', 80) . "\n";

$timeOfDay = Tool::create('get_time_of_day')
    ->description('Get current time of day')
    ->handler(function (): string {
        $hour = (int) date('H');
        if ($hour < 12) return 'morning';
        if ($hour < 18) return 'afternoon';
        return 'evening';
    });

echo "Creating agent with multiple tools...\n";
$multiToolAgent = $manager->instantiate('Basic Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$calculator, $timeOfDay]
]);
echo "✓ Agent created with 2 tools\n\n";

echo "Testing both tools...\n";
$result = $multiToolAgent->run('What time of day is it, and what is 50 + 50?');
echo "✓ Result: " . substr($result->getAnswer(), 0, 120) . "...\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 2 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ How to instantiate agents from templates\n";
echo "  ✓ Overriding template configurations\n";
echo "  ✓ Working with different agent types\n";
echo "  ✓ Adding tools to templated agents\n";
echo "  ✓ Instantiating from template objects\n\n";

echo "Key Concepts:\n";
echo "  💡 Templates provide sensible defaults\n";
echo "  💡 You can override any configuration value\n";
echo "  💡 Tools must be provided when instantiating\n";
echo "  💡 Each agent type has specific capabilities\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 3: php 03-template-metadata.php\n";
echo "  → Learn about template metadata and requirements\n";
