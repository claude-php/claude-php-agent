<?php

/**
 * Multi-Agent Collaboration Example
 * 
 * Demonstrates CollaborationManager, SimpleCollaborativeAgent, and message passing
 * in a multi-agent system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudeAgents\MultiAgent\Message;
use ClaudeAgents\MultiAgent\Protocol;
use ClaudeAgents\Agents\TaskPrioritizationAgent;
use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudePhp\ClaudePhp;

if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $_ENV['ANTHROPIC_API_KEY'] = $env['ANTHROPIC_API_KEY'] ?? '';
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (empty($apiKey)) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp($apiKey);

echo "Multi-Agent Collaboration Demo\n";
echo "===============================\n\n";

// 1. Collaboration Manager with CollaborativeAgents
echo "1. Collaboration Manager\n";
echo "------------------------\n";

$manager = new CollaborationManager($client, [
    'max_rounds' => 5,
    'enable_message_passing' => true,
]);

// Register specialized collaborative agents
$researcher = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research', 'information gathering'],
    options: ['system_prompt' => 'You are a research specialist. Gather factual information.']
);

$analyst = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'analyst',
    capabilities: ['analysis', 'evaluation'],
    options: ['system_prompt' => 'You are an analyst. Evaluate and synthesize information.']
);

$manager->registerAgent('researcher', $researcher, ['research', 'information gathering']);
$manager->registerAgent('analyst', $analyst, ['analysis', 'evaluation']);

$result = $manager->collaborate("Research and analyze the benefits of serverless architecture");
echo "Collaboration result:\n";
echo substr($result->getAnswer(), 0, 300) . "...\n";
echo "Rounds: {$result->getIterations()}\n";
echo "Agents involved: " . implode(', ', $result->getMetadata()['agents_involved']) . "\n\n";

// 1b. Direct message passing
echo "1b. Direct Message Passing\n";
echo "---------------------------\n";

$message = new Message('researcher', 'analyst', 'Please analyze serverless benefits', 'request');
$manager->sendMessage($message);
echo "Message sent from researcher to analyst\n";
echo "Analyst inbox count: " . $analyst->getUnreadCount() . "\n\n";

// 2. Task Prioritization Agent (BabyAGI-style)
echo "2. Task Prioritization Agent\n";
echo "-----------------------------\n";

$taskAgent = new TaskPrioritizationAgent($client, [
    'goal' => 'Launch a new mobile app',
]);

$result = $taskAgent->run("Launch a new mobile app");
echo "Tasks completed: {$result->getMetadata()['tasks_completed']}\n";
echo "Tasks remaining: {$result->getMetadata()['tasks_remaining']}\n";
echo substr($result->getAnswer(), 0, 300) . "...\n\n";

// 3. Coordinator Agent
echo "3. Coordinator Agent\n";
echo "--------------------\n";

$coordinator = new CoordinatorAgent($client);

$coder = new SimpleCollaborativeAgent($client, 'coder', ['coding', 'implementation']);
$tester = new SimpleCollaborativeAgent($client, 'tester', ['testing', 'quality assurance']);

// Register agents with capabilities
$coordinator->registerAgent('coder', $coder, ['coding', 'implementation']);
$coordinator->registerAgent('tester', $tester, ['testing', 'quality assurance']);

$result = $coordinator->run("Write unit tests for the authentication module");
echo "Delegated to: {$result->getMetadata()['delegated_to']}\n";
echo "Result: " . substr($result->getAnswer(), 0, 200) . "...\n\n";

// 4. Shared Memory Example
echo "4. Shared Memory Coordination\n";
echo "------------------------------\n";

$memory = $manager->getSharedMemory();
$memory->write('project_status', 'in_progress', 'researcher');
$status = $memory->read('project_status', 'analyst');
echo "Status written by researcher, read by analyst: {$status}\n";

$stats = $memory->getStatistics();
echo "Shared memory keys: {$stats['total_keys']}\n";
echo "Total operations: {$stats['total_operations']}\n\n";

// 5. Metrics
echo "5. Collaboration Metrics\n";
echo "------------------------\n";

$metrics = $manager->getMetrics();
echo "Agents registered: {$metrics['agents_registered']}\n";
echo "Messages routed: {$metrics['messages_routed']}\n";

echo "\nMulti-Agent Demo Complete!\n";

