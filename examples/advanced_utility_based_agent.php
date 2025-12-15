<?php

/**
 * Advanced Utility-Based Agent Example
 * 
 * Demonstrates advanced patterns including:
 * - Complex multi-objective optimization
 * - Dynamic constraint adjustment
 * - Real-world business scenarios
 * - Comparative analysis
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

echo "Advanced Utility-Based Agent Patterns\n";
echo "======================================\n\n";

// Pattern 1: Business Investment Decision
echo "Pattern 1: Investment Portfolio Optimization\n";
echo "---------------------------------------------\n";

$investmentAgent = new UtilityBasedAgent($client, [
    'name' => 'investment_advisor',
]);

// Multiple objectives for investment decisions
$investmentAgent->addObjective(
    'expected_return',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.35
);

$investmentAgent->addObjective(
    'risk_adjusted_return',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 60,
        'medium' => 80,
        'high' => 40,
    },
    weight: 0.25
);

$investmentAgent->addObjective(
    'liquidity',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.20
);

$investmentAgent->addObjective(
    'diversification',
    fn($action) => 70, // Would calculate based on existing portfolio
    weight: 0.20
);

// Risk constraints
$investmentAgent->addConstraint(
    'risk_tolerance',
    fn($action) => ($action['risk'] ?? 'high') !== 'high'
);

$investmentAgent->addConstraint(
    'minimum_return',
    fn($action) => ($action['estimated_value'] ?? 0) >= 40
);

$result = $investmentAgent->run("Recommend investment allocation between stocks, bonds, and real estate");
echo $result->getAnswer() . "\n\n";

// Pattern 2: Product Launch Strategy
echo "Pattern 2: Product Launch Strategy Selection\n";
echo "----------------------------------------------\n";

$launchAgent = new UtilityBasedAgent($client, [
    'name' => 'launch_strategist',
]);

// Objectives for launch strategy
$launchAgent->addObjective(
    'market_penetration',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.30
);

$launchAgent->addObjective(
    'speed_to_market',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.25
);

$launchAgent->addObjective(
    'brand_impact',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 50,
        'medium' => 70,
        'high' => 90,
    },
    weight: 0.25
);

$launchAgent->addObjective(
    'resource_efficiency',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.20
);

// Budget constraints
$launchAgent->addConstraint(
    'budget',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 75
);

$result = $launchAgent->run("Choose between soft launch, big bang launch, or phased rollout for our new SaaS product");
echo $result->getAnswer() . "\n\n";

// Pattern 3: Hiring Decision
echo "Pattern 3: Candidate Selection\n";
echo "-------------------------------\n";

$hiringAgent = new UtilityBasedAgent($client, [
    'name' => 'hiring_manager',
]);

// Candidate evaluation objectives
$hiringAgent->addObjective(
    'technical_skills',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.35
);

$hiringAgent->addObjective(
    'cultural_fit',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 90,
        'medium' => 70,
        'high' => 40,
    },
    weight: 0.25
);

$hiringAgent->addObjective(
    'experience_level',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.20
);

$hiringAgent->addObjective(
    'compensation_alignment',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.20
);

// Constraints
$hiringAgent->addConstraint(
    'salary_budget',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 80
);

$hiringAgent->addConstraint(
    'minimum_experience',
    fn($action) => ($action['estimated_value'] ?? 0) >= 60
);

$result = $hiringAgent->run("Select the best candidate for Senior Software Engineer position");
echo $result->getAnswer() . "\n\n";

// Pattern 4: Infrastructure Migration
echo "Pattern 4: Cloud Migration Strategy\n";
echo "------------------------------------\n";

$migrationAgent = new UtilityBasedAgent($client, [
    'name' => 'migration_planner',
]);

// Migration strategy objectives
$migrationAgent->addObjective(
    'performance_improvement',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.30
);

$migrationAgent->addObjective(
    'cost_reduction',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.25
);

$migrationAgent->addObjective(
    'migration_complexity',
    fn($action) => match($action['risk'] ?? 'high') {
        'low' => 90,
        'medium' => 60,
        'high' => 30,
    },
    weight: 0.25
);

$migrationAgent->addObjective(
    'downtime_minimization',
    fn($action) => 80, // Would be calculated based on strategy
    weight: 0.20
);

// Critical constraints
$migrationAgent->addConstraint(
    'no_high_risk',
    fn($action) => ($action['risk'] ?? 'high') !== 'high'
);

$migrationAgent->addConstraint(
    'budget_cap',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 85
);

$result = $migrationAgent->run("Determine the best strategy to migrate our monolith application to microservices on AWS");
echo $result->getAnswer() . "\n\n";

// Pattern 5: Marketing Channel Selection
echo "Pattern 5: Marketing Channel Optimization\n";
echo "------------------------------------------\n";

$marketingAgent = new UtilityBasedAgent($client, [
    'name' => 'marketing_optimizer',
]);

// Marketing channel objectives
$marketingAgent->addObjective(
    'reach',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.35
);

$marketingAgent->addObjective(
    'conversion_rate',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 50,
        'medium' => 75,
        'high' => 90,
    },
    weight: 0.30
);

$marketingAgent->addObjective(
    'cost_per_acquisition',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.25
);

$marketingAgent->addObjective(
    'brand_alignment',
    fn($action) => 75,
    weight: 0.10
);

// Budget constraint
$marketingAgent->addConstraint(
    'marketing_budget',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 70
);

$result = $marketingAgent->run("Choose the best marketing channels for our B2B SaaS product launch");
echo $result->getAnswer() . "\n\n";

// Pattern 6: Crisis Response Decision
echo "Pattern 6: Crisis Response Strategy\n";
echo "------------------------------------\n";

$crisisAgent = new UtilityBasedAgent($client, [
    'name' => 'crisis_manager',
]);

// Crisis response objectives (time-sensitive)
$crisisAgent->addObjective(
    'immediate_impact',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.40
);

$crisisAgent->addObjective(
    'speed_of_implementation',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.30
);

$crisisAgent->addObjective(
    'long_term_reputation',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 60,
        'medium' => 80,
        'high' => 50,
    },
    weight: 0.30
);

// Must be implementable quickly
$crisisAgent->addConstraint(
    'time_to_implement',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 60
);

$result = $crisisAgent->run("Our main server is down affecting 10,000 users. Choose the best immediate response strategy");
echo $result->getAnswer() . "\n\n";

// Pattern 7: Comparative Analysis
echo "Pattern 7: Vendor Selection with Detailed Analysis\n";
echo "---------------------------------------------------\n";

$vendorAgent = new UtilityBasedAgent($client, [
    'name' => 'vendor_selector',
]);

// Comprehensive vendor evaluation
$vendorAgent->addObjective('features', fn($a) => $a['estimated_value'] ?? 50, 0.25);
$vendorAgent->addObjective('pricing', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.25);
$vendorAgent->addObjective('reliability', fn($a) => match($a['risk'] ?? 'medium') {
    'low' => 95, 'medium' => 70, 'high' => 40
}, 0.20);
$vendorAgent->addObjective('support', fn($a) => 80, 0.15);
$vendorAgent->addObjective('integration', fn($a) => 75, 0.15);

$vendorAgent->addConstraint('sla', fn($a) => ($a['risk'] ?? 'high') !== 'high');
$vendorAgent->addConstraint('price', fn($a) => ($a['estimated_cost'] ?? 100) <= 80);

$result = $vendorAgent->run("Select a customer support platform from Zendesk, Intercom, and Freshdesk");
echo $result->getAnswer() . "\n\n";

// Display detailed metadata
if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    echo "Detailed Analysis:\n";
    echo "  Total actions evaluated: {$metadata['actions_evaluated']}\n";
    echo "  Best utility score: " . number_format($metadata['best_utility'], 4) . "\n";
    
    if (!empty($metadata['all_evaluations'])) {
        echo "\n  All Alternatives (sorted by utility):\n";
        foreach ($metadata['all_evaluations'] as $i => $eval) {
            echo "    " . ($i + 1) . ". {$eval['action']['description']} ";
            echo "(utility: " . number_format($eval['utility'], 3) . ")\n";
        }
    }
}

echo "\n\nAdvanced Demo Complete!\n";

