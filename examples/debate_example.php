#!/usr/bin/env php
<?php
/**
 * Multi-Agent Debate Example
 *
 * Demonstrates collaborative reasoning through multi-agent debates.
 * Shows Pro/Con patterns and Round Table discussions for exploring
 * different perspectives on complex topics.
 *
 * Usage: php examples/debate_example.php
 * Requires: ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\ProConDebate;
use ClaudeAgents\Debate\Patterns\RoundTableDebate;
use ClaudeAgents\Debate\Patterns\ConsensusBuilder;
use ClaudeAgents\Debate\Patterns\DevilsAdvocate;
use ClaudePhp\ClaudePhp;

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set.\n";
    echo "Please set it before running this example.\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Multi-Agent Debate Examples ===\n\n";

// Example 1: Pro/Con Debate
echo "--- Example 1: Pro/Con Debate ---\n";
echo "Topic: Should remote work become the default?\n\n";

try {
    $debate = ProConDebate::create($client, 'Should remote work become the default?', rounds: 2);
    $result = $debate->debate('Should remote work become the default?');

    echo "Debate Synthesis:\n";
    echo $result->getSynthesis() . "\n";
    echo "Agreement Score: " . round($result->getAgreementScore() * 100) . "%\n";
    echo "Rounds: " . $result->getRoundCount() . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 2: Round Table Discussion
echo "--- Example 2: Round Table Discussion ---\n";
echo "Topic: What should our tech stack be?\n\n";

try {
    $roundTable = RoundTableDebate::create($client, rounds: 2);
    $result2 = $roundTable->debate('What should our tech stack be for a new microservice?');

    echo "Debate Synthesis:\n";
    echo substr($result2->getSynthesis(), 0, 500) . "...\n";
    echo "Agreement Score: " . round($result2->getAgreementScore() * 100) . "%\n";
    echo "Rounds: " . $result2->getRoundCount() . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 3: Consensus Builder
echo "--- Example 3: Consensus Builder ---\n";
echo "Topic: Technical debt vs new features\n\n";

try {
    $consensus = ConsensusBuilder::create($client, rounds: 2);
    $result3 = $consensus->debate('How should we balance technical debt vs new features?');

    echo "Debate Synthesis:\n";
    echo substr($result3->getSynthesis(), 0, 400) . "...\n";
    echo "Agreement Score: " . round($result3->getAgreementScore() * 100) . "%\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 4: Devil's Advocate
echo "--- Example 4: Devil's Advocate ---\n";
echo "Topic: Microservices migration\n\n";

try {
    $devilsAdvocate = DevilsAdvocate::create($client, rounds: 2);
    $result4 = $devilsAdvocate->debate('We should migrate to microservices architecture');

    echo "Debate Synthesis:\n";
    echo substr($result4->getSynthesis(), 0, 400) . "...\n";
    echo "Agreement Score: " . round($result4->getAgreementScore() * 100) . "%\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 5: Custom Debate System
echo "--- Example 5: Custom Debate System ---\n";
echo "Topic: Code review practices\n\n";

try {
    $juniorDev = new DebateAgent(
        $client,
        'Junior Developer',
        'beginner',
        'You represent a junior developer perspective. Focus on learning, clarity, and practical implementation.'
    );

    $seniorDev = new DebateAgent(
        $client,
        'Senior Developer',
        'expert',
        'You represent a senior developer perspective. Focus on best practices, architecture, and long-term maintainability.'
    );

    $customSystem = DebateSystem::create($client)
        ->addAgent('junior', $juniorDev)
        ->addAgent('senior', $seniorDev)
        ->rounds(1);

    $result5 = $customSystem->debate('What makes a good code review?');

    echo "Debate Synthesis:\n";
    echo substr($result5->getSynthesis(), 0, 400) . "...\n";
    echo "Agreement Score: " . round($result5->getAgreementScore() * 100) . "%\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "\n=== All Examples Complete ===\n";


