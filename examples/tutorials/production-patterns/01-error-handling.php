<?php

/**
 * Production Patterns Tutorial 1: Error Handling
 * 
 * Run: php examples/tutorials/production-patterns/01-error-handling.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;

echo "=== Production Patterns Tutorial 1: Error Handling ===\n\n";

function runAgentSafely(string $query): ?string
{
    try {
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY') ?: 'demo-key');
        $agent = Agent::create($client);
        
        $result = $agent->run($query);
        return $result->getAnswer();
        
    } catch (\ClaudePhp\Exceptions\ApiException $e) {
        error_log("API Error: " . $e->getMessage());
        return null;
    } catch (\Exception $e) {
        error_log("Error: " . $e->getMessage());
        return null;
    }
}

echo "Testing error handling patterns...\n\n";

$answer = runAgentSafely('What is 2+2?');

if ($answer !== null) {
    echo "✓ Got answer: $answer\n";
} else {
    echo "✗ Failed to get answer (error handled gracefully)\n";
}

echo "\n✓ Example complete!\n";
