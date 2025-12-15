#!/usr/bin/env php
<?php
/**
 * Multi-Tool Agent Example
 *
 * Demonstrates an agent with multiple tools that can reason about
 * which tool to use for different tasks.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
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
echo "║                    Multi-Tool Agent Example                                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Tool 1: Calculator
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression";
        }
        return (string) eval("return {$expr};");
    });

// Tool 2: String operations
$stringTool = Tool::create('string_operation')
    ->description('Perform string operations like length, reverse, uppercase, lowercase')
    ->stringParam('text', 'The text to operate on')
    ->stringParam('operation', 'Operation to perform', true, ['length', 'reverse', 'upper', 'lower'])
    ->handler(function (array $input): string {
        $text = $input['text'];
        return match ($input['operation']) {
            'length' => (string) strlen($text),
            'reverse' => strrev($text),
            'upper' => strtoupper($text),
            'lower' => strtolower($text),
            default => "Unknown operation",
        };
    });

// Tool 3: Date/time
$dateTool = Tool::create('get_datetime')
    ->description('Get current date and time, optionally in a specific timezone')
    ->stringParam('timezone', 'IANA timezone (e.g., America/New_York)', false)
    ->stringParam('format', 'PHP date format string', false)
    ->handler(function (array $input): string {
        $tz = $input['timezone'] ?? 'UTC';
        $format = $input['format'] ?? 'Y-m-d H:i:s T';
        
        try {
            $dt = new DateTime('now', new DateTimeZone($tz));
            return $dt->format($format);
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Tool 4: Random number generator
$randomTool = Tool::create('random_number')
    ->description('Generate a random number within a range')
    ->numberParam('min', 'Minimum value (inclusive)')
    ->numberParam('max', 'Maximum value (inclusive)')
    ->handler(function (array $input): string {
        $min = (int) $input['min'];
        $max = (int) $input['max'];
        return (string) random_int($min, $max);
    });

// Create agent with all tools
$agent = Agent::create($client)
    ->withTools([$calculator, $stringTool, $dateTool, $randomTool])
    ->withSystemPrompt('You are a helpful assistant with access to various tools. Use the appropriate tool for each task.')
    ->maxIterations(10)
    ->onToolExecution(function (string $tool, array $input, $result) {
        echo "  🔧 {$tool}: " . json_encode($input) . " → {$result->getContent()}\n";
    });

// Test cases
$tasks = [
    "What is 123 * 456?",
    "Reverse the string 'Hello World' and tell me its length",
    "What time is it in Tokyo?",
    "Generate a random number between 1 and 100",
    "Calculate (50 * 2) + (100 / 4), then tell me the current date in New York",
];

foreach ($tasks as $i => $task) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Task " . ($i + 1) . ": {$task}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $result = $agent->run($task);
    
    if ($result->isSuccess()) {
        echo "\n✅ Answer:\n{$result->getAnswer()}\n";
        echo "\n📊 Stats: {$result->getIterations()} iterations, ";
        $usage = $result->getTokenUsage();
        echo "{$usage['total']} tokens\n";
    } else {
        echo "\n❌ Error: {$result->getError()}\n";
    }
    
    echo "\n";
}

echo str_repeat("═", 80) . "\n";
echo "Multi-tool agent example completed!\n";

