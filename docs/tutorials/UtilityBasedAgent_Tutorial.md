# UtilityBasedAgent Tutorial

## Introduction

Welcome to the UtilityBasedAgent tutorial! In this comprehensive guide, you'll learn how to build intelligent decision-making systems that can evaluate options, balance competing objectives, and make optimal choices under constraints.

### What You'll Learn

1. Creating basic utility-based decision agents
2. Defining and using utility functions
3. Multi-objective optimization
4. Adding constraints to filter options
5. Real-world business decision scenarios
6. Advanced patterns and best practices
7. Production deployment strategies

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key (for live examples)
- Basic understanding of PHP and the Claude API
- Familiarity with decision theory concepts (helpful but not required)

### Time Required

Approximately 45-60 minutes

---

## Chapter 1: Understanding Utility-Based Decisions

### What is Utility?

**Utility** is a measure of the value or usefulness of an action. In decision-making:

- Higher utility = Better choice
- Utility can combine multiple factors (cost, benefit, risk)
- Agents maximize utility to find optimal decisions

### Real-World Examples

```
Choosing a Restaurant:
- Utility = (food quality × 0.5) + (price value × 0.3) + (convenience × 0.2)

Selecting a Cloud Provider:
- Utility = (performance × 0.4) + (cost-efficiency × 0.3) + (reliability × 0.3)

Hiring a Candidate:
- Utility = (technical skills × 0.35) + (culture fit × 0.30) + (experience × 0.35)
```

### Your First Utility-Based Agent

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\UtilityBasedAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the agent
$agent = new UtilityBasedAgent($client, [
    'name' => 'simple_decision_maker',
]);

echo "Agent: {$agent->getName()}\n";

// Make a decision
$result = $agent->run('Choose the best programming language for web development');

if ($result->isSuccess()) {
    echo "\n" . $result->getAnswer() . "\n";
}
```

### What Just Happened?

1. **Agent Created**: We initialized a utility-based agent
2. **Task Given**: We asked it to make a choice
3. **Actions Generated**: Claude generated possible options (Python, JavaScript, PHP, etc.)
4. **Evaluation**: Each option was scored using the default utility function
5. **Selection**: The highest-utility option was chosen
6. **Report**: A detailed decision report was generated

---

## Chapter 2: Custom Utility Functions

### The Basics

A utility function takes an action and returns a numeric score:

```php
$utilityFunction = function($action) {
    // Extract action properties
    $value = $action['estimated_value'] ?? 50;  // Benefit
    $cost = $action['estimated_cost'] ?? 50;     // Cost
    
    // Calculate utility (higher is better)
    return $value - $cost;  // Simple: benefit minus cost
};
```

### Setting a Custom Utility Function

```php
$agent = new UtilityBasedAgent($client);

// Define a custom utility function
$agent->setUtilityFunction(function($action) {
    $value = $action['estimated_value'] ?? 50;
    $cost = $action['estimated_cost'] ?? 50;
    
    // Normalize to 0-1 range
    $valueScore = $value / 100;
    $costScore = (100 - $cost) / 100;  // Invert cost (lower is better)
    
    // Weighted combination: 70% value, 30% cost
    return ($valueScore * 0.7) + ($costScore * 0.3);
});

$result = $agent->run('Choose a task management tool for our team');
echo $result->getAnswer();
```

### Utility Function Patterns

#### Pattern 1: Simple Subtraction

```php
// Good for: Direct cost-benefit analysis
$agent->setUtilityFunction(fn($a) => 
    ($a['benefit'] ?? 0) - ($a['cost'] ?? 0)
);
```

#### Pattern 2: Weighted Sum

```php
// Good for: Multiple factors with different importance
$agent->setUtilityFunction(function($action) {
    $performance = ($action['performance'] ?? 50) / 100;
    $cost = (100 - ($action['cost'] ?? 50)) / 100;
    $ease = ($action['ease_of_use'] ?? 50) / 100;
    
    return ($performance * 0.5) + ($cost * 0.3) + ($ease * 0.2);
});
```

#### Pattern 3: Risk-Adjusted

```php
// Good for: Accounting for uncertainty
$agent->setUtilityFunction(function($action) {
    $expectedValue = $action['value'] ?? 50;
    $risk = $action['risk'] ?? 'medium';
    
    // Adjust value based on risk
    $riskMultiplier = match($risk) {
        'low' => 1.0,
        'medium' => 0.8,
        'high' => 0.6,
    };
    
    return $expectedValue * $riskMultiplier;
});
```

#### Pattern 4: Threshold-Based

```php
// Good for: Minimum requirements
$agent->setUtilityFunction(function($action) {
    $value = $action['value'] ?? 0;
    
    // Penalty for not meeting minimum
    if ($value < 50) {
        return $value * 0.5;  // 50% penalty
    }
    
    return $value;
});
```

### Try It Yourself

Create a utility function for choosing a laptop:

```php
$laptopAgent = new UtilityBasedAgent($client);

$laptopAgent->setUtilityFunction(function($action) {
    // TODO: Calculate utility based on:
    // - Performance (40%)
    // - Battery life (30%)
    // - Portability (20%)
    // - Price (10%)
    
    $performance = ($action['estimated_value'] ?? 50) / 100;
    $battery = 0.8;  // Would extract from action
    $portable = 0.7;  // Would extract from action
    $price = (100 - ($action['estimated_cost'] ?? 50)) / 100;
    
    return ($performance * 0.4) + 
           ($battery * 0.3) + 
           ($portable * 0.2) + 
           ($price * 0.1);
});

$result = $laptopAgent->run('Recommend a laptop for a software developer');
echo $result->getAnswer();
```

---

## Chapter 3: Multi-Objective Optimization

### Why Multiple Objectives?

Real-world decisions involve multiple, often conflicting goals:

- **Cost vs. Quality**: Lower cost usually means lower quality
- **Speed vs. Reliability**: Fast development may sacrifice stability
- **Innovation vs. Safety**: New solutions carry more risk

### Adding Objectives

Instead of a single utility function, define multiple objectives:

```php
$agent = new UtilityBasedAgent($client, [
    'name' => 'multi_objective_optimizer',
]);

// Add objective 1: Maximize business value (50% weight)
$agent->addObjective(
    'business_value',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.5
);

// Add objective 2: Minimize cost (30% weight)
$agent->addObjective(
    'cost_efficiency',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.3
);

// Add objective 3: Minimize risk (20% weight)
$agent->addObjective(
    'risk_mitigation',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 100,
        'medium' => 60,
        'high' => 20,
    },
    weight: 0.2
);

$result = $agent->run('Choose the best database system for our startup');
```

### How It Works

The agent calculates a weighted sum of all objectives:

```
Total Utility = (Objective1 × Weight1) + (Objective2 × Weight2) + ...

Example:
Action: PostgreSQL
- Business Value: 85 × 0.5 = 42.5
- Cost Efficiency: 70 × 0.3 = 21.0
- Risk Mitigation: 90 × 0.2 = 18.0
Total: 81.5
```

### Balancing Objectives

#### Equal Weights (Democratic)

```php
$agent->addObjective('speed', fn($a) => ..., 0.33);
$agent->addObjective('quality', fn($a) => ..., 0.33);
$agent->addObjective('cost', fn($a) => ..., 0.34);
```

#### Prioritized (One Goal Dominates)

```php
$agent->addObjective('security', fn($a) => ..., 0.6);  // Critical
$agent->addObjective('usability', fn($a) => ..., 0.25);
$agent->addObjective('aesthetics', fn($a) => ..., 0.15);
```

#### Tiered (Major + Minor Goals)

```php
$agent->addObjective('compliance', fn($a) => ..., 0.4);  // Must-have
$agent->addObjective('performance', fn($a) => ..., 0.35); // Important
$agent->addObjective('cost', fn($a) => ..., 0.15);       // Nice-to-have
$agent->addObjective('features', fn($a) => ..., 0.10);   // Bonus
```

### Real-World Example: Hiring Decision

```php
$hiringAgent = new UtilityBasedAgent($client, [
    'name' => 'hiring_manager',
]);

// Technical ability (35%)
$hiringAgent->addObjective(
    'technical_skills',
    fn($action) => $action['estimated_value'] ?? 50,
    0.35
);

// Cultural fit (30%)
$hiringAgent->addObjective(
    'culture_fit',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 90,   // Perfect fit
        'medium' => 65, // Good fit
        'high' => 30,   // Poor fit
    },
    0.30
);

// Experience level (20%)
$hiringAgent->addObjective(
    'experience',
    fn($action) => $action['estimated_value'] ?? 50,
    0.20
);

// Salary expectations (15%)
$hiringAgent->addObjective(
    'compensation_fit',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    0.15
);

$result = $hiringAgent->run('Select the best candidate for Senior Engineer position');
echo $result->getAnswer();

// Review the decision
$metadata = $result->getMetadata();
echo "\nObjective Scores:\n";
foreach ($metadata['all_evaluations'][0]['objective_scores'] ?? [] as $obj => $score) {
    echo "  $obj: " . number_format($score, 1) . "\n";
}
```

---

## Chapter 4: Adding Constraints

### What Are Constraints?

Constraints are **hard requirements** that filter out unacceptable options:

- ✅ **Constraint**: "Budget MUST be under $50,000"
- ❌ **Not a constraint**: "We prefer lower budgets"

### Adding Constraints

```php
$agent = new UtilityBasedAgent($client);

// Constraint 1: Budget limit
$agent->addConstraint(
    'budget_limit',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 70
);

// Constraint 2: Risk tolerance
$agent->addConstraint(
    'acceptable_risk',
    fn($action) => in_array($action['risk'] ?? 'high', ['low', 'medium'])
);

// Constraint 3: Minimum quality
$agent->addConstraint(
    'quality_threshold',
    fn($action) => ($action['estimated_value'] ?? 0) >= 60
);

$result = $agent->run('Choose a cloud provider within our constraints');
```

### Constraints vs. Objectives

| Aspect | Constraints | Objectives |
|--------|-------------|------------|
| **Type** | Boolean (pass/fail) | Numeric (scoring) |
| **Purpose** | Filter options | Rank options |
| **Example** | "Must be GDPR compliant" | "Prefer higher security" |
| **Violation** | Option eliminated | Score reduced |

### When to Use Constraints

Use constraints for:

```php
// ✅ Legal requirements
$agent->addConstraint('legal', fn($a) => $a['gdpr_compliant'] ?? false);

// ✅ Hard deadlines
$agent->addConstraint('deadline', fn($a) => ($a['estimated_cost'] ?? 100) <= 30);

// ✅ Technical limitations
$agent->addConstraint('compatibility', fn($a) => $a['supports_php8'] ?? false);

// ✅ Budget limits
$agent->addConstraint('budget', fn($a) => ($a['cost'] ?? 1000) <= 500);

// ✅ Resource availability
$agent->addConstraint('team_size', fn($a) => ($a['required_devs'] ?? 10) <= 5);
```

### Common Constraint Patterns

#### Pattern 1: Numeric Threshold

```php
$agent->addConstraint('max_cost', fn($a) => 
    ($a['estimated_cost'] ?? 100) <= 75
);

$agent->addConstraint('min_quality', fn($a) => 
    ($a['estimated_value'] ?? 0) >= 60
);
```

#### Pattern 2: Categorical Filter

```php
$agent->addConstraint('risk_level', fn($a) => 
    in_array($a['risk'] ?? 'high', ['low', 'medium'])
);

$agent->addConstraint('compliance', fn($a) => 
    in_array($a['status'] ?? 'pending', ['approved', 'certified'])
);
```

#### Pattern 3: Boolean Check

```php
$agent->addConstraint('tested', fn($a) => 
    $a['has_tests'] ?? false
);

$agent->addConstraint('documented', fn($a) => 
    $a['has_docs'] ?? false
);
```

#### Pattern 4: Complex Logic

```php
$agent->addConstraint('viable', function($action) {
    $cost = $action['estimated_cost'] ?? 100;
    $value = $action['estimated_value'] ?? 0;
    $risk = $action['risk'] ?? 'high';
    
    // Must meet all conditions
    return $cost <= 80 && $value >= 50 && $risk !== 'high';
});
```

### Real-World Example: Product Selection

```php
$productAgent = new UtilityBasedAgent($client);

// Objectives (what we want to maximize)
$productAgent->addObjective('features', fn($a) => $a['estimated_value'] ?? 50, 0.4);
$productAgent->addObjective('user_experience', fn($a) => 80, 0.35);
$productAgent->addObjective('support_quality', fn($a) => 75, 0.25);

// Constraints (deal-breakers)
$productAgent->addConstraint('budget', fn($a) => 
    ($a['estimated_cost'] ?? 100) <= 70
);

$productAgent->addConstraint('sla', fn($a) => 
    ($a['risk'] ?? 'high') !== 'high'  // Must have good reliability
);

$productAgent->addConstraint('integration', fn($a) => 
    ($a['estimated_value'] ?? 0) >= 60  // Must meet integration needs
);

$result = $productAgent->run('Select a CRM system for our sales team');
echo $result->getAnswer();

echo "\nFiltering Summary:\n";
$metadata = $result->getMetadata();
echo "Actions after constraint filtering: {$metadata['actions_evaluated']}\n";
```

---

## Chapter 5: Real-World Business Scenarios

### Scenario 1: Technology Stack Selection

```php
$techStackAgent = new UtilityBasedAgent($client);

// Objectives
$techStackAgent->addObjective('developer_productivity', 
    fn($a) => $a['estimated_value'] ?? 50, 0.30);

$techStackAgent->addObjective('scalability', 
    fn($a) => match($a['risk'] ?? 'medium') {
        'low' => 90, 'medium' => 70, 'high' => 40
    }, 0.30);

$techStackAgent->addObjective('ecosystem_maturity', 
    fn($a) => $a['estimated_value'] ?? 50, 0.20);

$techStackAgent->addObjective('hiring_availability', 
    fn($a) => 75, 0.20);

// Constraints
$techStackAgent->addConstraint('production_ready', 
    fn($a) => ($a['risk'] ?? 'high') !== 'high');

$techStackAgent->addConstraint('team_learning_curve', 
    fn($a) => ($a['estimated_cost'] ?? 100) <= 70);

$result = $techStackAgent->run(
    'Choose technology stack for e-commerce platform: Node.js+React, PHP+Vue, or Python+Django'
);
echo $result->getAnswer();
```

### Scenario 2: Marketing Channel Selection

```php
$marketingAgent = new UtilityBasedAgent($client);

// Objectives
$marketingAgent->addObjective('reach', 
    fn($a) => $a['estimated_value'] ?? 50, 0.35);

$marketingAgent->addObjective('conversion_rate', 
    fn($a) => match($a['risk'] ?? 'medium') {
        'low' => 50, 'medium' => 75, 'high' => 90
    }, 0.30);

$marketingAgent->addObjective('cost_per_acquisition', 
    fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.25);

$marketingAgent->addObjective('brand_alignment', 
    fn($a) => 70, 0.10);

// Constraints
$marketingAgent->addConstraint('budget', 
    fn($a) => ($a['estimated_cost'] ?? 100) <= 65);

$result = $marketingAgent->run(
    'Choose primary marketing channel for B2B SaaS launch: LinkedIn Ads, Google Ads, or Content Marketing'
);
echo $result->getAnswer();
```

### Scenario 3: Vendor Selection

```php
$vendorAgent = new UtilityBasedAgent($client);

// Multiple competing objectives
$vendorAgent->addObjective('feature_completeness', fn($a) => $a['estimated_value'] ?? 50, 0.25);
$vendorAgent->addObjective('pricing', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.25);
$vendorAgent->addObjective('reliability', fn($a) => match($a['risk'] ?? 'medium') {
    'low' => 95, 'medium' => 70, 'high' => 40
}, 0.20);
$vendorAgent->addObjective('support_quality', fn($a) => 80, 0.15);
$vendorAgent->addObjective('integration_ease', fn($a) => 75, 0.15);

// Critical constraints
$vendorAgent->addConstraint('uptime_sla', fn($a) => ($a['risk'] ?? 'high') !== 'high');
$vendorAgent->addConstraint('budget_ceiling', fn($a) => ($a['estimated_cost'] ?? 100) <= 80);
$vendorAgent->addConstraint('data_privacy', fn($a) => true);  // Would check actual compliance

$result = $vendorAgent->run('Select customer support platform: Zendesk, Intercom, or Freshdesk');
echo $result->getAnswer();

// Detailed analysis
if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    echo "\n\nDetailed Decision Analysis:\n";
    echo str_repeat('=', 50) . "\n";
    
    foreach ($metadata['all_evaluations'] as $i => $eval) {
        echo "\nOption " . ($i + 1) . ": {$eval['action']['description']}\n";
        echo "Overall Utility: " . number_format($eval['utility'], 2) . "\n";
        
        if (!empty($eval['objective_scores'])) {
            echo "Breakdown:\n";
            foreach ($eval['objective_scores'] as $objective => $score) {
                echo "  - $objective: " . number_format($score, 1) . "\n";
            }
        }
    }
}
```

### Scenario 4: Feature Prioritization

```php
$featurePrioritizer = new UtilityBasedAgent($client);

// Business-focused objectives
$featurePrioritizer->addObjective('business_value', 
    fn($a) => $a['estimated_value'] ?? 50, 0.40);

$featurePrioritizer->addObjective('user_impact', 
    fn($a) => match($a['risk'] ?? 'medium') {
        'low' => 50, 'medium' => 75, 'high' => 90
    }, 0.30);

$featurePrioritizer->addObjective('implementation_effort', 
    fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.30);

// Resource constraints
$featurePrioritizer->addConstraint('time_to_market', 
    fn($a) => ($a['estimated_cost'] ?? 100) <= 70);

$featurePrioritizer->addConstraint('team_capacity', 
    fn($a) => ($a['estimated_cost'] ?? 100) <= 80);

$result = $featurePrioritizer->run(
    'Prioritize next feature: advanced analytics dashboard, mobile app, or API integrations'
);
echo $result->getAnswer();
```

### Scenario 5: Infrastructure Migration

```php
$migrationAgent = new UtilityBasedAgent($client);

// Migration-specific objectives
$migrationAgent->addObjective('performance_improvement', 
    fn($a) => $a['estimated_value'] ?? 50, 0.30);

$migrationAgent->addObjective('cost_reduction', 
    fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.25);

$migrationAgent->addObjective('migration_complexity', 
    fn($a) => match($a['risk'] ?? 'high') {
        'low' => 90, 'medium' => 60, 'high' => 30
    }, 0.25);

$migrationAgent->addObjective('downtime_minimization', 
    fn($a) => 80, 0.20);

// Critical constraints
$migrationAgent->addConstraint('zero_high_risk', 
    fn($a) => ($a['risk'] ?? 'high') !== 'high');

$migrationAgent->addConstraint('budget_approved', 
    fn($a) => ($a['estimated_cost'] ?? 100) <= 85);

$result = $migrationAgent->run(
    'Choose migration strategy: lift-and-shift, re-platform, or refactor to microservices'
);
echo $result->getAnswer();
```

---

## Chapter 6: Advanced Patterns

### Pattern 1: Cascading Decisions

Make a series of dependent decisions:

```php
// Step 1: Strategic decision
$strategicAgent = new UtilityBasedAgent($client);
$strategicAgent->addObjective('alignment', fn($a) => $a['estimated_value'] ?? 50, 1.0);

$strategyResult = $strategicAgent->run('Choose overall cloud strategy');
$chosenStrategy = $strategyResult->getMetadata()['best_action']['description'];

// Step 2: Tactical decision based on strategy
$tacticalAgent = new UtilityBasedAgent($client);
$tacticalAgent->addObjective('feasibility', fn($a) => $a['estimated_value'] ?? 50, 0.6);
$tacticalAgent->addObjective('timeline', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.4);

$tacticsResult = $tacticalAgent->run("Implement $chosenStrategy: choose specific tools and vendors");

echo "Strategy: $chosenStrategy\n";
echo "Tactics:\n{$tacticsResult->getAnswer()}\n";
```

### Pattern 2: Sensitivity Analysis

Test how weight changes affect decisions:

```php
function sensitivityAnalysis(string $task, array $weightSets): array
{
    global $client;
    $results = [];
    
    foreach ($weightSets as $name => $weights) {
        $agent = new UtilityBasedAgent($client);
        
        $agent->addObjective('speed', fn($a) => $a['estimated_value'] ?? 50, $weights[0]);
        $agent->addObjective('quality', fn($a) => 80, $weights[1]);
        $agent->addObjective('cost', fn($a) => 100 - ($a['estimated_cost'] ?? 50), $weights[2]);
        
        $result = $agent->run($task);
        $results[$name] = $result->getMetadata()['best_action']['description'];
    }
    
    return $results;
}

$analysis = sensitivityAnalysis('Choose development approach', [
    'speed_focused' => [0.6, 0.2, 0.2],
    'balanced' => [0.33, 0.34, 0.33],
    'quality_focused' => [0.2, 0.6, 0.2],
    'cost_focused' => [0.2, 0.2, 0.6],
]);

echo "Sensitivity Analysis Results:\n";
foreach ($analysis as $profile => $decision) {
    echo "  $profile: $decision\n";
}
```

### Pattern 3: Multi-Stakeholder Decisions

Combine preferences from different stakeholders:

```php
class MultiStakeholderAgent
{
    private array $stakeholders = [];
    
    public function addStakeholder(string $name, array $objectives, float $influence): void
    {
        $this->stakeholders[$name] = [
            'objectives' => $objectives,
            'influence' => $influence,
        ];
    }
    
    public function makeDecision(ClaudePhp $client, string $task): AgentResult
    {
        $agent = new UtilityBasedAgent($client);
        
        // Combine stakeholder preferences
        foreach ($this->stakeholders as $name => $config) {
            foreach ($config['objectives'] as $objName => $objConfig) {
                $weight = $objConfig['weight'] * $config['influence'];
                $agent->addObjective(
                    "{$name}_{$objName}",
                    $objConfig['function'],
                    $weight
                );
            }
        }
        
        return $agent->run($task);
    }
}

// Usage
$multiAgent = new MultiStakeholderAgent();

$multiAgent->addStakeholder('engineering', [
    'technical_excellence' => [
        'weight' => 0.6,
        'function' => fn($a) => $a['estimated_value'] ?? 50,
    ],
    'maintainability' => [
        'weight' => 0.4,
        'function' => fn($a) => 75,
    ],
], influence: 0.4);

$multiAgent->addStakeholder('business', [
    'time_to_market' => [
        'weight' => 0.5,
        'function' => fn($a) => 100 - ($a['estimated_cost'] ?? 50),
    ],
    'roi' => [
        'weight' => 0.5,
        'function' => fn($a) => $a['estimated_value'] ?? 50,
    ],
], influence: 0.6);

$result = $multiAgent->makeDecision($client, 'Choose architecture for new feature');
echo $result->getAnswer();
```

### Pattern 4: Decision Explanation

Generate detailed explanations for transparency:

```php
function explainDecision(AgentResult $result): string
{
    $metadata = $result->getMetadata();
    $explanation = "Decision Explanation\n";
    $explanation .= str_repeat('=', 50) . "\n\n";
    
    // Winner
    $winner = $metadata['all_evaluations'][0] ?? null;
    if ($winner) {
        $explanation .= "✓ Selected: {$winner['action']['description']}\n";
        $explanation .= "  Overall Utility: " . number_format($winner['utility'], 2) . "\n\n";
        
        // Breakdown
        $explanation .= "Why this choice?\n";
        foreach ($winner['objective_scores'] ?? [] as $obj => $score) {
            $explanation .= "  • $obj: " . number_format($score, 1) . "/100\n";
        }
    }
    
    // Alternatives
    $explanation .= "\nAlternatives considered:\n";
    foreach (array_slice($metadata['all_evaluations'], 1) as $i => $alt) {
        $explanation .= "  " . ($i + 2) . ". {$alt['action']['description']} ";
        $explanation .= "(utility: " . number_format($alt['utility'], 2) . ")\n";
    }
    
    return $explanation;
}

// Usage
$agent = new UtilityBasedAgent($client);
// ... configure agent ...
$result = $agent->run('Make a choice');

echo explainDecision($result);
```

---

## Chapter 7: Production Deployment

### Setup for Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Configure logging
$logger = new Logger('utility_agent');
$logger->pushHandler(
    new RotatingFileHandler('/var/log/agent/utility.log', 30, Logger::INFO)
);
$logger->pushHandler(
    new StreamHandler('php://stderr', Logger::ERROR)
);

// Create production agent
$prodAgent = new UtilityBasedAgent($client, [
    'name' => 'production_decision_maker',
    'logger' => $logger,
]);

// Configure for production environment
if (getenv('APP_ENV') === 'production') {
    // Stricter constraints in production
    $prodAgent->addConstraint('compliance', fn($a) => true);
    $prodAgent->addConstraint('security', fn($a) => ($a['risk'] ?? 'high') !== 'high');
}
```

### Error Handling

```php
function makeRobustDecision(UtilityBasedAgent $agent, string $task): ?array
{
    try {
        $result = $agent->run($task);
        
        if ($result->isSuccess()) {
            return [
                'decision' => $result->getMetadata()['best_action']['description'],
                'utility' => $result->getMetadata()['best_utility'],
                'alternatives' => count($result->getMetadata()['all_evaluations']),
            ];
        } else {
            error_log("Decision failed: {$result->getError()}");
            return null;
        }
    } catch (\Throwable $e) {
        error_log("Agent crashed: {$e->getMessage()}");
        
        // Fallback to default decision
        return [
            'decision' => 'fallback_option',
            'utility' => 0.0,
            'alternatives' => 0,
            'note' => 'Using fallback due to error',
        ];
    }
}
```

### Performance Monitoring

```php
class MonitoredUtilityAgent
{
    private UtilityBasedAgent $agent;
    private Logger $logger;
    private array $metrics = [];
    
    public function run(string $task): AgentResult
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->agent->run($task);
            
            $duration = microtime(true) - $startTime;
            
            $this->metrics[] = [
                'timestamp' => time(),
                'task' => substr($task, 0, 50),
                'success' => $result->isSuccess(),
                'duration' => $duration,
                'actions_evaluated' => $result->getMetadata()['actions_evaluated'] ?? 0,
            ];
            
            if ($duration > 5.0) {
                $this->logger->warning('Slow decision', [
                    'duration' => $duration,
                    'task' => $task,
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Decision failed', [
                'error' => $e->getMessage(),
                'task' => $task,
            ]);
            throw $e;
        }
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
```

### Caching Decisions

```php
class CachedUtilityAgent
{
    private UtilityBasedAgent $agent;
    private array $cache = [];
    private int $cacheTtl = 3600;  // 1 hour
    
    public function run(string $task): AgentResult
    {
        $cacheKey = md5($task);
        
        // Check cache
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTtl) {
                return $cached['result'];
            }
        }
        
        // Not cached or expired
        $result = $this->agent->run($task);
        
        // Store in cache
        $this->cache[$cacheKey] = [
            'timestamp' => time(),
            'result' => $result,
        ];
        
        return $result;
    }
}
```

---

## Chapter 8: Troubleshooting

### Problem: Unexpected Decisions

**Symptoms**: Agent chooses options that seem suboptimal

**Solutions**:

```php
// 1. Review objective weights
$agent = new UtilityBasedAgent($client);
$agent->addObjective('primary', fn($a) => ..., 0.6);  // Increase weight
$agent->addObjective('secondary', fn($a) => ..., 0.4);

// 2. Check objective calculations
$testAction = ['estimated_value' => 70, 'estimated_cost' => 60];
$score = $objectiveFunction($testAction);
echo "Test score: $score\n";  // Verify it makes sense

// 3. Add constraints for deal-breakers
$agent->addConstraint('minimum_quality', fn($a) => ($a['value'] ?? 0) >= 50);
```

### Problem: All Actions Filtered

**Symptoms**: Agent returns "No valid actions"

**Solutions**:

```php
// 1. Review constraints - they may be too strict
$result = $agent->run($task);
if (!$result->isSuccess() && str_contains($result->getError(), 'No valid actions')) {
    echo "All actions were filtered by constraints!\n";
    // Relax constraints
}

// 2. Check constraint logic
$testAction = ['estimated_cost' => 75];
$passes = $constraintFunction($testAction);
echo "Constraint passes: " . ($passes ? 'yes' : 'no') . "\n";
```

### Problem: Inconsistent Results

**Symptoms**: Running the same task gives different answers

**Solutions**:

```php
// This is expected! Claude generates different actions each time.
// To make decisions more stable:

// 1. Run multiple times and aggregate
function stableDecision(UtilityBasedAgent $agent, string $task, int $runs = 3): array
{
    $decisions = [];
    
    for ($i = 0; $i < $runs; $i++) {
        $result = $agent->run($task);
        if ($result->isSuccess()) {
            $decision = $result->getMetadata()['best_action']['description'];
            $decisions[$decision] = ($decisions[$decision] ?? 0) + 1;
        }
    }
    
    arsort($decisions);
    return $decisions;
}

// 2. Cache action generation
// (See caching example in Chapter 7)
```

---

## Conclusion

Congratulations! You've completed the UtilityBasedAgent tutorial. You now know how to:

✅ Create utility-based decision agents  
✅ Define custom utility functions  
✅ Implement multi-objective optimization  
✅ Add constraints to filter options  
✅ Apply agents to real-world business scenarios  
✅ Use advanced patterns for complex decisions  
✅ Deploy utility agents to production  
✅ Troubleshoot common issues  

### Next Steps

1. **Experiment**: Try the examples in `/examples/utility_based_agent.php`
2. **Build**: Create a decision agent for your specific domain
3. **Optimize**: Fine-tune objectives and constraints for your needs
4. **Integrate**: Add utility agents to your application
5. **Share**: Contribute your learnings to the community

### Additional Resources

- [UtilityBasedAgent Documentation](../UtilityBasedAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [API Reference](../UtilityBasedAgent.md#api-reference)
- [Examples Directory](../../examples/)

### Community

Questions? Ideas? Share them with the community!

---

**Happy Decision-Making! 🎯**

