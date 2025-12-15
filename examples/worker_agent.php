#!/usr/bin/env php
<?php
/**
 * Worker Agent Example
 *
 * Demonstrates the WorkerAgent class as a specialized agent
 * that can be used standalone or as part of hierarchical systems.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\WorkerAgent;
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
echo "║                        Worker Agent Example                                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Example 1: Simple Worker - Math Specialist
// ============================================================================

echo "Example 1: Math Specialist Worker\n";
echo str_repeat("─", 80) . "\n";

$mathWorker = new WorkerAgent($client, [
    'name' => 'math_expert',
    'specialty' => 'mathematical calculations, statistics, and numerical analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations and clear explanations of mathematical concepts. Always show your work.',
]);

echo "Worker Name: {$mathWorker->getName()}\n";
echo "Specialty: {$mathWorker->getSpecialty()}\n\n";

$mathTask = "Calculate the average, median, and standard deviation of this dataset: 15, 23, 27, 19, 32, 28, 22, 25, 30, 21";

echo "Task: {$mathTask}\n\n";
echo "Processing...\n\n";

$result = $mathWorker->run($mathTask);

if ($result->isSuccess()) {
    echo "✅ Result:\n";
    echo str_repeat("─", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("─", 80) . "\n\n";
    
    $usage = $result->getTokenUsage();
    echo "📊 Stats: {$result->getIterations()} iterations, {$usage['total']} tokens\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// ============================================================================
// Example 2: Writing Specialist
// ============================================================================

echo "Example 2: Writing Specialist Worker\n";
echo str_repeat("─", 80) . "\n";

$writingWorker = new WorkerAgent($client, [
    'name' => 'content_writer',
    'specialty' => 'creative writing, content creation, and storytelling',
    'system' => 'You are a professional writer. Create engaging, clear, and well-structured content that resonates with readers.',
]);

echo "Worker Name: {$writingWorker->getName()}\n";
echo "Specialty: {$writingWorker->getSpecialty()}\n\n";

$writingTask = "Write a compelling 3-paragraph introduction for a blog post about the benefits of morning exercise routines.";

echo "Task: {$writingTask}\n\n";
echo "Processing...\n\n";

$result = $writingWorker->run($writingTask);

if ($result->isSuccess()) {
    echo "✅ Result:\n";
    echo str_repeat("─", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("─", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Worker: {$metadata['worker']}\n";
    echo "📊 Specialty: {$metadata['specialty']}\n";
    
    $usage = $result->getTokenUsage();
    echo "📊 Tokens: {$usage['total']} total ({$usage['input']} in, {$usage['output']} out)\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// ============================================================================
// Example 3: Code Analysis Specialist
// ============================================================================

echo "Example 3: Code Analysis Specialist Worker\n";
echo str_repeat("─", 80) . "\n";

$codeWorker = new WorkerAgent($client, [
    'name' => 'code_reviewer',
    'specialty' => 'code review, security analysis, and best practices',
    'system' => 'You are a senior software engineer. Review code for bugs, security issues, performance problems, and adherence to best practices. Provide specific, actionable feedback.',
    'max_tokens' => 3000,
]);

echo "Worker Name: {$codeWorker->getName()}\n";
echo "Specialty: {$codeWorker->getSpecialty()}\n\n";

$codeToReview = <<<'PHP'
function processUserInput($input) {
    $query = "SELECT * FROM users WHERE username = '" . $input . "'";
    $result = mysql_query($query);
    while ($row = mysql_fetch_array($result)) {
        echo $row['username'] . ": " . $row['email'] . "<br>";
    }
}
PHP;

$codeTask = "Review this PHP function and identify security issues, deprecated functions, and suggest improvements:\n\n{$codeToReview}";

echo "Task: Reviewing PHP code for security issues...\n\n";
echo "Processing...\n\n";

$result = $codeWorker->run($codeTask);

if ($result->isSuccess()) {
    echo "✅ Review Results:\n";
    echo str_repeat("─", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("─", 80) . "\n\n";
    
    $usage = $result->getTokenUsage();
    echo "📊 Analysis completed with {$usage['total']} tokens\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// ============================================================================
// Example 4: Research Specialist
// ============================================================================

echo "Example 4: Research Specialist Worker\n";
echo str_repeat("─", 80) . "\n";

$researchWorker = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research, fact-finding, and information synthesis',
    'system' => 'You are a research specialist. Gather relevant information, synthesize data, and provide well-structured insights. Always explain your reasoning.',
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
]);

echo "Worker Name: {$researchWorker->getName()}\n";
echo "Specialty: {$researchWorker->getSpecialty()}\n\n";

$researchTask = "Explain the key differences between REST and GraphQL APIs, including when to use each approach.";

echo "Task: {$researchTask}\n\n";
echo "Processing...\n\n";

$result = $researchWorker->run($researchTask);

if ($result->isSuccess()) {
    echo "✅ Research Findings:\n";
    echo str_repeat("─", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("─", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Research by: {$metadata['worker']}\n";
    
    $usage = $result->getTokenUsage();
    echo "📊 Tokens used: {$usage['total']}\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// ============================================================================
// Example 5: Multiple Workers Comparison
// ============================================================================

echo "Example 5: Comparing Different Worker Specialties\n";
echo str_repeat("─", 80) . "\n";

$topic = "the importance of software testing";

$workers = [
    new WorkerAgent($client, [
        'name' => 'technical_writer',
        'specialty' => 'technical documentation and explanation',
        'system' => 'You are a technical writer. Explain concepts clearly with proper technical terminology.',
    ]),
    new WorkerAgent($client, [
        'name' => 'sales_writer',
        'specialty' => 'persuasive writing and marketing',
        'system' => 'You are a marketing copywriter. Write persuasive, benefit-focused content.',
    ]),
    new WorkerAgent($client, [
        'name' => 'educator',
        'specialty' => 'educational content and teaching',
        'system' => 'You are an educator. Teach concepts using clear examples and analogies.',
    ]),
];

$task = "Write a brief paragraph about {$topic}";

echo "Same task given to different specialists:\n";
echo "Task: \"{$task}\"\n\n";

foreach ($workers as $worker) {
    echo "Worker: {$worker->getName()} ({$worker->getSpecialty()})\n";
    echo str_repeat("─", 80) . "\n";
    
    $result = $worker->run($task);
    
    if ($result->isSuccess()) {
        echo $result->getAnswer() . "\n";
        
        $usage = $result->getTokenUsage();
        echo "\n💡 Tokens: {$usage['total']}\n";
    } else {
        echo "❌ Error: {$result->getError()}\n";
    }
    
    echo "\n";
}

echo str_repeat("═", 80) . "\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "Summary:\n";
echo str_repeat("─", 80) . "\n";
echo "✅ Demonstrated 5 worker agent examples:\n";
echo "   1. Math Specialist - Precise calculations and analysis\n";
echo "   2. Writing Specialist - Creative content creation\n";
echo "   3. Code Review Specialist - Security and best practices\n";
echo "   4. Research Specialist - Information synthesis\n";
echo "   5. Multiple Specialists - Same task, different approaches\n\n";
echo "Key Features:\n";
echo "   • Each worker has a specific specialty and system prompt\n";
echo "   • Workers can use different models and token limits\n";
echo "   • Same task produces different results based on specialty\n";
echo "   • Workers can be used standalone or in hierarchical systems\n\n";
echo "Next Steps:\n";
echo "   • Try creating your own specialized workers\n";
echo "   • Combine workers in a HierarchicalAgent for complex tasks\n";
echo "   • Adjust system prompts to fine-tune behavior\n";
echo "   • Monitor token usage for cost optimization\n";
echo "\n" . str_repeat("═", 80) . "\n";
echo "Worker agent examples completed!\n";

