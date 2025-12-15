<?php

/**
 * Utility-Based Agent Example
 * 
 * Demonstrates how to use the UtilityBasedAgent for decision-making
 * based on utility functions, objectives, and constraints.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\UtilityBasedAgent;
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

$client = new ClaudePhp($apiKey);

echo "Utility-Based Agent Demo\n";
echo "========================\n\n";

// Example 1: Simple Decision Making
echo "1. Simple Decision Making\n";
echo "-------------------------\n";

$agent = new UtilityBasedAgent($client, [
    'name' => 'decision_maker',
]);

$result = $agent->run("Choose the best programming language for a web application");
echo $result->getAnswer() . "\n\n";
echo "Actions evaluated: {$result->getMetadata()['actions_evaluated']}\n";
echo "Best utility: " . number_format($result->getMetadata()['best_utility'], 3) . "\n\n";

// Example 2: Multi-Objective Decision Making
echo "2. Multi-Objective Optimization\n";
echo "--------------------------------\n";

$multiObjectiveAgent = new UtilityBasedAgent($client, [
    'name' => 'optimizer',
]);

// Add objectives with different weights
$multiObjectiveAgent->addObjective(
    'value',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.5
);

$multiObjectiveAgent->addObjective(
    'cost_efficiency',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.3
);

$multiObjectiveAgent->addObjective(
    'risk_tolerance',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 100,
        'medium' => 60,
        'high' => 30,
    },
    weight: 0.2
);

$result = $multiObjectiveAgent->run("Choose the best cloud infrastructure provider");
echo $result->getAnswer() . "\n\n";

// Example 3: Constrained Decision Making
echo "3. Constrained Decision Making\n";
echo "-------------------------------\n";

$constrainedAgent = new UtilityBasedAgent($client, [
    'name' => 'constrained_optimizer',
]);

// Add objectives
$constrainedAgent->addObjective(
    'performance',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.6
);

$constrainedAgent->addObjective(
    'affordability',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.4
);

// Add constraints
$constrainedAgent->addConstraint(
    'budget_limit',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 70
);

$constrainedAgent->addConstraint(
    'acceptable_risk',
    fn($action) => in_array($action['risk'] ?? 'high', ['low', 'medium'])
);

$result = $constrainedAgent->run("Choose a database system for a startup with limited budget");
echo $result->getAnswer() . "\n\n";

// Example 4: Custom Utility Function
echo "4. Custom Utility Function\n";
echo "---------------------------\n";

$customAgent = new UtilityBasedAgent($client);

// Set custom utility function that balances value and cost
$customAgent->setUtilityFunction(function($action) {
    $value = $action['estimated_value'] ?? 50;
    $cost = $action['estimated_cost'] ?? 50;
    
    // Higher utility for high value, low cost
    $valueScore = $value / 100;
    $costScore = (100 - $cost) / 100;
    
    // Weighted combination
    return ($valueScore * 0.7) + ($costScore * 0.3);
});

$result = $customAgent->run("Select a CI/CD tool for our development team");
echo $result->getAnswer() . "\n\n";

// Example 5: Technology Stack Selection
echo "5. Technology Stack Selection\n";
echo "------------------------------\n";

$stackAgent = new UtilityBasedAgent($client, [
    'name' => 'stack_selector',
]);

// Define objectives for tech stack selection
$stackAgent->addObjective(
    'developer_productivity',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.3
);

$stackAgent->addObjective(
    'scalability',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 90,
        'medium' => 70,
        'high' => 40,
    },
    weight: 0.3
);

$stackAgent->addObjective(
    'cost_effectiveness',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.2
);

$stackAgent->addObjective(
    'team_familiarity',
    fn($action) => 75, // Would normally be based on action data
    weight: 0.2
);

// Add constraint: must be production-ready
$stackAgent->addConstraint(
    'maturity',
    fn($action) => ($action['risk'] ?? 'high') !== 'high'
);

$result = $stackAgent->run("Choose the best technology stack for an e-commerce platform");
echo $result->getAnswer() . "\n\n";

// Example 6: Feature Prioritization
echo "6. Feature Prioritization\n";
echo "-------------------------\n";

$featureAgent = new UtilityBasedAgent($client);

// Objectives for feature selection
$featureAgent->addObjective(
    'business_value',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.4
);

$featureAgent->addObjective(
    'development_effort',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.3
);

$featureAgent->addObjective(
    'user_impact',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 50,
        'medium' => 75,
        'high' => 90,
    },
    weight: 0.3
);

// Constraint: development time must be reasonable
$featureAgent->addConstraint(
    'time_constraint',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 80
);

$result = $featureAgent->run("Which feature should we build next: user authentication, payment integration, or social sharing?");
echo $result->getAnswer() . "\n\n";

echo "Demo complete!\n";

