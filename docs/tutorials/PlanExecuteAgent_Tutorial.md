# Plan-Execute Agent Tutorial

Welcome to this comprehensive tutorial on using the `PlanExecuteAgent`! The plan-execute pattern is a powerful approach for handling complex, multi-step tasks by systematically breaking them down into manageable pieces.

## Table of Contents

1. [Introduction](#introduction)
2. [What is the Plan-Execute Pattern?](#what-is-the-plan-execute-pattern)
3. [Setup](#setup)
4. [Tutorial 1: Your First Plan-Execute Agent](#tutorial-1-your-first-plan-execute-agent)
5. [Tutorial 2: Understanding the Execution Flow](#tutorial-2-understanding-the-execution-flow)
6. [Tutorial 3: Adding Tools to Plans](#tutorial-3-adding-tools-to-plans)
7. [Tutorial 4: Adaptive Replanning](#tutorial-4-adaptive-replanning)
8. [Tutorial 5: Multi-Tool Coordination](#tutorial-5-multi-tool-coordination)
9. [Tutorial 6: Complex Workflows](#tutorial-6-complex-workflows)
10. [Tutorial 7: Production Best Practices](#tutorial-7-production-best-practices)
11. [Common Patterns](#common-patterns)
12. [Troubleshooting](#troubleshooting)
13. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to leverage the plan-execute pattern to solve complex tasks systematically. By the end, you'll be able to:

- Understand when and why to use plan-execute agents
- Create detailed execution plans automatically
- Coordinate multiple tools in sequence
- Handle adaptive replanning based on results
- Build production-ready planning systems

## What is the Plan-Execute Pattern?

The plan-execute pattern separates task completion into distinct phases:

### Traditional Direct Execution

```
User: "Create a marketing report with sales analysis"
Agent: [Attempts everything at once] → Result
```

### Plan-Execute Approach

```
User: "Create a marketing report with sales analysis"
Agent: 
  Phase 1 - Planning:
    1. Gather sales data for the period
    2. Analyze trends and patterns
    3. Create visualizations
    4. Write executive summary
    5. Compile final report
    
  Phase 2 - Execution:
    Execute Step 1 → Execute Step 2 → ... → Execute Step 5
    
  Phase 3 - Synthesis:
    Combine all results → Final comprehensive report
```

### Key Benefits

- **Visibility**: See exactly what steps will be taken
- **Reliability**: Systematic execution reduces errors
- **Context**: Each step gets results from previous steps
- **Adaptability**: Can revise plan based on results
- **Debugging**: Easy to identify where things went wrong

## Setup

First, install the package and set up your API key:

```bash
composer require claude-php-agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

Or create a `.env` file:

```env
ANTHROPIC_API_KEY=your-api-key-here
```

## Tutorial 1: Your First Plan-Execute Agent

Let's create a simple plan-execute agent to handle a multi-step task.

### Step 1: Basic Setup

Create a file `my_first_planner.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a plan-execute agent
$agent = new PlanExecuteAgent($client);

// Define a multi-step task
$task = "Write a brief product announcement email for a new productivity app. " .
        "Include: catchy subject line, brief intro, 3 key features, " .
        "call-to-action, and closing.";

// Run the agent
$result = $agent->run($task);

// Check the results
if ($result->isSuccess()) {
    echo "=== FINAL EMAIL ===\n";
    echo $result->getAnswer() . "\n\n";
    
    // View execution details
    $metadata = $result->getMetadata();
    echo "Execution Summary:\n";
    echo "- Steps in plan: {$metadata['plan_steps']}\n";
    echo "- Total iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "- Tokens used: {$usage['total']}\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}
```

### What Happens Behind the Scenes

1. **Planning**: Agent creates a structured plan
   ```
   1. Create a compelling subject line (under 50 characters)
   2. Write an engaging opening paragraph
   3. List and explain 3 key features
   4. Write a clear call-to-action
   5. Add a professional closing
   ```

2. **Execution**: Each step is executed with context
   - Step 1 creates the subject line
   - Step 2 uses the subject line for context
   - Step 3 builds on the intro
   - And so on...

3. **Synthesis**: All pieces are assembled into the final email

### Run It

```bash
php my_first_planner.php
```

### Expected Output

You'll see a complete email with all components, plus metadata about how it was created.

## Tutorial 2: Understanding the Execution Flow

Let's examine what happens during each phase in detail.

### Step 1: Inspecting Step Results

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new PlanExecuteAgent($client, [
    'name' => 'detailed_planner',
]);

$task = "Create a simple 3-step morning routine guide for busy professionals.";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "=== FINAL GUIDE ===\n";
    echo $result->getAnswer() . "\n\n";
    
    // Detailed step breakdown
    $metadata = $result->getMetadata();
    
    echo "=== EXECUTION BREAKDOWN ===\n\n";
    foreach ($metadata['step_results'] as $step) {
        echo "Step {$step['step']}: {$step['description']}\n";
        echo str_repeat('-', 70) . "\n";
        echo $step['result'] . "\n";
        echo str_repeat('=', 70) . "\n\n";
    }
}
```

### Understanding Token Usage

```php
$usage = $result->getTokenUsage();

echo "Token Analysis:\n";
echo "- Input tokens: {$usage['input']}\n";
echo "- Output tokens: {$usage['output']}\n";
echo "- Total: {$usage['total']}\n\n";

// Cost estimation (approximate rates)
$inputCost = ($usage['input'] / 1000) * 0.003;
$outputCost = ($usage['output'] / 1000) * 0.015;
$totalCost = $inputCost + $outputCost;

echo "Estimated Cost: $" . number_format($totalCost, 4) . "\n";
```

## Tutorial 3: Adding Tools to Plans

Tools make plan-execute agents much more powerful by providing factual capabilities.

### Step 1: Creating Useful Tools

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a calculator tool
$calculator = Tool::create('calculator')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'The math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Safe evaluation for basic math
        $expr = preg_replace('/[^0-9+\-*\/\(\)\.\s]/', '', $expr);
        
        try {
            $result = eval("return {$expr};");
            return "Result: " . number_format($result, 2);
        } catch (\Throwable $e) {
            return "Error: Invalid expression";
        }
    });

// Create a date tool
$dateInfo = Tool::create('date_info')
    ->description('Get current date and time information')
    ->stringParam('info_type', 'Type of info: date, time, day, month, year')
    ->handler(function (array $input): string {
        $type = $input['info_type'] ?? 'date';
        
        switch ($type) {
            case 'date':
                return date('Y-m-d');
            case 'time':
                return date('H:i:s');
            case 'day':
                return date('l'); // Monday, Tuesday, etc.
            case 'month':
                return date('F'); // January, February, etc.
            case 'year':
                return date('Y');
            default:
                return date('Y-m-d H:i:s');
        }
    });

// Create the agent with tools
$agent = new PlanExecuteAgent($client);
$agent->addTool($calculator);
$agent->addTool($dateInfo);

// Task that requires tools
$task = "Create a financial summary: Calculate quarterly revenue ($45,000 + $52,000 + $48,000), " .
        "calculate average per month, and create a summary report with today's date.";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "=== FINANCIAL SUMMARY ===\n";
    echo $result->getAnswer() . "\n";
}
```

### What Happens

The agent's plan will include tool usage:
```
1. Use calculator to sum quarterly revenue
2. Use calculator to find monthly average
3. Use date_info to get current date
4. Compile summary report with all information
```

## Tutorial 4: Adaptive Replanning

Enable the agent to revise its plan if steps don't go as expected.

### Step 1: Enable Replanning

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a tool that might "fail"
$dataFetch = Tool::create('fetch_sales_data')
    ->description('Fetch sales data from database')
    ->stringParam('month', 'Month to fetch data for')
    ->handler(function (array $input): string {
        $month = $input['month'];
        
        // Simulate data not being available for future months
        $currentMonth = date('n');
        $requestedMonth = date('n', strtotime($month));
        
        if ($requestedMonth > $currentMonth) {
            return "Error: Data not available for future months";
        }
        
        // Simulated data
        return "Sales for {$month}: $" . rand(10000, 50000);
    });

$agent = new PlanExecuteAgent($client, [
    'allow_replan' => true, // Enable adaptive replanning
]);

$agent->addTool($dataFetch);

$task = "Analyze sales trends: Fetch data for the past 3 months and calculate growth rate.";

echo "Running with adaptive replanning enabled...\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "=== ANALYSIS RESULT ===\n";
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    
    // Check if replanning occurred
    $originalSteps = $metadata['plan_steps'];
    $actualSteps = count($metadata['step_results']);
    
    if ($actualSteps != $originalSteps) {
        echo "⚠️  Note: Plan was adapted during execution\n";
        echo "Original plan had {$originalSteps} steps\n";
        echo "Actually executed {$actualSteps} steps\n";
    }
}
```

### How Replanning Works

1. Agent executes a step
2. If result contains failure indicators ("error", "unable", "failed")
3. Agent creates a revised plan for remaining steps
4. Continues with new plan

```
Original Plan:
1. Fetch January data
2. Fetch February data  ← Fails
3. Calculate growth

After Replanning:
1. Fetch January data [completed]
2. Fetch February data [failed]
3. Use January data to estimate February  ← New approach
4. Calculate estimated growth
```

## Tutorial 5: Multi-Tool Coordination

Coordinate multiple tools in a complex workflow.

### Step 1: Create a Tool Suite

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Data collection tool
$collectData = Tool::create('collect_survey_data')
    ->description('Collect survey response data')
    ->stringParam('survey_id', 'Survey identifier')
    ->handler(function (array $input): string {
        return "Survey responses: 150 total, 120 complete, 30 partial";
    });

// Analysis tool
$analyze = Tool::create('analyze_responses')
    ->description('Analyze survey responses')
    ->stringParam('data', 'Data to analyze')
    ->handler(function (array $input): string {
        return "Analysis: 85% satisfaction rate, top theme: product quality";
    });

// Visualization tool
$visualize = Tool::create('create_chart')
    ->description('Create data visualization')
    ->stringParam('chart_type', 'Type of chart: bar, pie, line')
    ->stringParam('data', 'Data to visualize')
    ->handler(function (array $input): string {
        $type = $input['chart_type'];
        return "Created {$type} chart: chart_image_url.png";
    });

// Report generation tool
$generateReport = Tool::create('generate_pdf')
    ->description('Generate PDF report')
    ->stringParam('content', 'Report content')
    ->handler(function (array $input): string {
        return "PDF generated: survey_report_2024.pdf";
    });

// Create agent with all tools
$agent = new PlanExecuteAgent($client, [
    'name' => 'survey_analyzer',
    'max_tokens' => 4096,
]);

$agent->addTool($collectData)
      ->addTool($analyze)
      ->addTool($visualize)
      ->addTool($generateReport);

$task = "Complete survey analysis workflow: " .
        "1) Collect data from survey_2024_q4, " .
        "2) Analyze responses for key insights, " .
        "3) Create bar chart of satisfaction ratings, " .
        "4) Generate PDF report with findings and chart.";

echo "Running multi-tool workflow...\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "=== WORKFLOW COMPLETE ===\n";
    echo $result->getAnswer() . "\n\n";
    
    // Show tool usage
    $metadata = $result->getMetadata();
    echo "=== EXECUTION STEPS ===\n";
    foreach ($metadata['step_results'] as $step) {
        echo "• Step {$step['step']}: {$step['description']}\n";
    }
}
```

### Expected Workflow

```
Plan Created:
1. Use collect_survey_data tool to gather responses
2. Use analyze_responses tool to identify insights
3. Use create_chart tool to visualize satisfaction
4. Use generate_pdf tool to compile final report

Execution:
Step 1: collect_survey_data(survey_2024_q4) → "150 responses..."
Step 2: analyze_responses(previous data) → "85% satisfaction..."
Step 3: create_chart(bar, satisfaction data) → "Created bar chart..."
Step 4: generate_pdf(all content) → "PDF generated..."

Synthesis: "Survey analysis complete. Key findings: ..."
```

## Tutorial 6: Complex Workflows

Handle sophisticated multi-phase projects.

### Step 1: Project Planning Example

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

// Simple console logger
class ConsoleLogger implements LoggerInterface
{
    public function log($level, $message, array $context = []): void
    {
        echo "[{$level}] {$message}\n";
    }
    
    public function emergency($message, array $context = []): void
    { $this->log('EMERGENCY', $message, $context); }
    public function alert($message, array $context = []): void
    { $this->log('ALERT', $message, $context); }
    public function critical($message, array $context = []): void
    { $this->log('CRITICAL', $message, $context); }
    public function error($message, array $context = []): void
    { $this->log('ERROR', $message, $context); }
    public function warning($message, array $context = []): void
    { $this->log('WARNING', $message, $context); }
    public function notice($message, array $context = []): void
    { $this->log('NOTICE', $message, $context); }
    public function info($message, array $context = []): void
    { $this->log('INFO', $message, $context); }
    public function debug($message, array $context = []): void
    { $this->log('DEBUG', $message, $context); }
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new PlanExecuteAgent($client, [
    'name' => 'project_planner',
    'max_tokens' => 4096,
    'allow_replan' => true,
    'logger' => new ConsoleLogger(),
]);

$task = "Create a comprehensive launch plan for a new mobile app. " .
        "Include: market research summary, target audience personas (2-3), " .
        "marketing channel strategy, launch timeline (12 weeks), " .
        "success metrics, and risk mitigation strategies.";

echo "Creating comprehensive project plan...\n";
echo str_repeat('=', 70) . "\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "=== LAUNCH PLAN ===\n";
    echo str_repeat('=', 70) . "\n\n";
    echo $result->getAnswer() . "\n\n";
    
    // Detailed metrics
    $metadata = $result->getMetadata();
    $usage = $result->getTokenUsage();
    
    echo str_repeat('=', 70) . "\n";
    echo "=== EXECUTION METRICS ===\n";
    echo str_repeat('=', 70) . "\n";
    echo "Plan Steps: {$metadata['plan_steps']}\n";
    echo "Iterations: {$result->getIterations()}\n";
    echo "Tokens: {$usage['total']} ({$usage['input']} in, {$usage['output']} out)\n";
    echo "Est. Cost: $" . number_format(
        ($usage['input'] * 0.003 + $usage['output'] * 0.015) / 1000,
        4
    ) . "\n";
}
```

### Output Structure

The agent will create a detailed multi-section document:

1. Market Research Summary
2. Target Audience Personas
3. Marketing Channel Strategy
4. 12-Week Launch Timeline
5. Success Metrics
6. Risk Mitigation

Each section builds on previous ones for coherence.

## Tutorial 7: Production Best Practices

Build robust, production-ready plan-execute systems.

### Step 1: Comprehensive Error Handling

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

class ProductionPlanner
{
    private PlanExecuteAgent $agent;
    private array $stats = [
        'total_runs' => 0,
        'successful' => 0,
        'failed' => 0,
        'total_tokens' => 0,
    ];
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new PlanExecuteAgent($client, [
            'name' => 'production_planner',
            'max_tokens' => 4096,
            'allow_replan' => true,
        ]);
    }
    
    public function executeTask(string $task, int $retries = 3): ?string
    {
        $this->stats['total_runs']++;
        
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $retries) {
            try {
                $result = $this->agent->run($task);
                
                if ($result->isSuccess()) {
                    $this->stats['successful']++;
                    
                    $usage = $result->getTokenUsage();
                    $this->stats['total_tokens'] += $usage['total'];
                    
                    return $result->getAnswer();
                }
                
                $lastError = $result->getError();
                $attempt++;
                
                if ($attempt < $retries) {
                    // Exponential backoff
                    sleep(pow(2, $attempt));
                }
                
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $attempt++;
                
                if ($attempt < $retries) {
                    sleep(pow(2, $attempt));
                }
            }
        }
        
        $this->stats['failed']++;
        error_log("Task failed after {$retries} attempts: {$lastError}");
        
        return null;
    }
    
    public function getStats(): array
    {
        return $this->stats;
    }
}

// Usage
$planner = new ProductionPlanner($client);

$tasks = [
    "Create a product feature comparison table",
    "Write a technical blog post outline",
    "Generate FAQ content for customer support",
];

echo "Processing tasks in production mode...\n\n";

foreach ($tasks as $i => $task) {
    echo "Task " . ($i + 1) . ": " . substr($task, 0, 50) . "...\n";
    
    $result = $planner->executeTask($task);
    
    if ($result) {
        echo "✅ Success\n";
        echo substr($result, 0, 100) . "...\n\n";
    } else {
        echo "❌ Failed\n\n";
    }
}

// Show statistics
$stats = $planner->getStats();
echo str_repeat('=', 70) . "\n";
echo "=== SESSION STATISTICS ===\n";
echo "Total tasks: {$stats['total_runs']}\n";
echo "Successful: {$stats['successful']}\n";
echo "Failed: {$stats['failed']}\n";
echo "Total tokens: {$stats['total_tokens']}\n";
echo "Success rate: " . round(($stats['successful'] / $stats['total_runs']) * 100, 1) . "%\n";
```

### Step 2: Monitoring and Metrics

```php
class PlanExecuteMonitor
{
    private array $executions = [];
    
    public function trackExecution(string $taskId, AgentResult $result): void
    {
        $metadata = $result->getMetadata();
        $usage = $result->getTokenUsage();
        
        $this->executions[] = [
            'task_id' => $taskId,
            'success' => $result->isSuccess(),
            'plan_steps' => $metadata['plan_steps'] ?? 0,
            'actual_steps' => count($metadata['step_results'] ?? []),
            'iterations' => $result->getIterations(),
            'tokens' => $usage['total'],
            'cost' => ($usage['input'] * 0.003 + $usage['output'] * 0.015) / 1000,
            'timestamp' => time(),
        ];
    }
    
    public function generateReport(): string
    {
        $total = count($this->executions);
        $successful = count(array_filter($this->executions, fn($e) => $e['success']));
        $avgTokens = array_sum(array_column($this->executions, 'tokens')) / $total;
        $totalCost = array_sum(array_column($this->executions, 'cost'));
        
        return "Plan-Execute Report\n" .
               "Total executions: {$total}\n" .
               "Success rate: " . round(($successful / $total) * 100, 1) . "%\n" .
               "Avg tokens per task: " . round($avgTokens) . "\n" .
               "Total cost: $" . number_format($totalCost, 4) . "\n";
    }
}
```

## Common Patterns

### Pattern 1: Research and Summarize

```php
$agent = new PlanExecuteAgent($client);

$task = "Research topic: 'Benefits of microservices architecture'. " .
        "Create: 1) Key benefits list, 2) Common challenges, " .
        "3) Best practices, 4) Executive summary.";

$result = $agent->run($task);
```

### Pattern 2: Data Pipeline

```php
$agent = new PlanExecuteAgent($client, [
    'tools' => [$fetchTool, $transformTool, $analyzeTool, $exportTool],
]);

$task = "Execute data pipeline: fetch user data, transform to standard format, " .
        "analyze for insights, export results to CSV.";

$result = $agent->run($task);
```

### Pattern 3: Content Creation Workflow

```php
$task = "Create blog post: Topic 'Getting Started with PHP 8.3'. " .
        "1) Outline with 5 sections, 2) Write intro, " .
        "3) Write each section, 4) Add code examples, " .
        "5) Write conclusion with CTAs.";

$result = $agent->run($task);
```

### Pattern 4: Report Generation

```php
$task = "Generate monthly report: 1) Collect metrics, " .
        "2) Calculate trends vs last month, 3) Identify top 5 insights, " .
        "4) Create executive summary, 5) List recommendations.";

$result = $agent->run($task);
```

## Troubleshooting

### Issue: Plans are too vague

**Solution:** Provide more specific task descriptions:

```php
// ❌ Too vague
$task = "Make a report about sales";

// ✅ Specific
$task = "Create sales report with: 1) Q4 revenue summary, " .
        "2) Month-over-month comparison, 3) Top 5 products, " .
        "4) Regional breakdown, 5) Forecasts for Q1.";
```

### Issue: Steps don't coordinate well

**Solution:** Enable replanning and trust the synthesis phase:

```php
$agent = new PlanExecuteAgent($client, [
    'allow_replan' => true, // Let agent adapt
    'max_tokens' => 4096,   // Allow detailed synthesis
]);
```

### Issue: High token usage

**Solution:** Optimize configuration:

```php
$agent = new PlanExecuteAgent($client, [
    'max_tokens' => 1024,       // Shorter responses
    'allow_replan' => false,    // No replanning overhead
]);

// Break large tasks into smaller ones
$result1 = $agent->run("Part 1: Create outline");
$result2 = $agent->run("Part 2: Write sections based on: " . $result1->getAnswer());
```

### Issue: Slow execution

**Solution:** Plan-execute involves multiple API calls. For speed:

```php
// Use regular Agent for simple tasks
$simpleAgent = Agent::create($client);

// Use PlanExecuteAgent only for complex multi-step tasks
if ($isComplex) {
    $result = $planExecuteAgent->run($task);
} else {
    $result = $simpleAgent->run($task);
}
```

## Next Steps

### Explore Advanced Features

1. **Combine with other agents:**
   ```php
   // Use CoT for planning, Plan-Execute for execution
   $planExecute->run("Using chain-of-thought, create detailed plan for: " . $task);
   ```

2. **Create specialized planners:**
   ```php
   class ContentPlanner extends PlanExecuteAgent { /* ... */ }
   class DataPipeline extends PlanExecuteAgent { /* ... */ }
   ```

3. **Build workflow libraries:**
   ```php
   class WorkflowLibrary
   {
       public static function contentCreation(): string { /* ... */ }
       public static function dataAnalysis(): string { /* ... */ }
       public static function reportGeneration(): string { /* ... */ }
   }
   ```

### Learn More

- Read the [PlanExecuteAgent Documentation](../PlanExecuteAgent.md)
- Explore the [Complete Example](../../examples/plan_execute_example.php)
- Check out the [Agent Selection Guide](../agent-selection-guide.md)

### Practice Projects

1. **Blog Post Generator:** Plan and create complete blog posts with research
2. **Event Planner:** Create comprehensive event plans with timelines
3. **Data Pipeline:** Build multi-step data processing workflows
4. **Report System:** Generate complex analytical reports

## Conclusion

You now have a comprehensive understanding of the PlanExecuteAgent! The plan-execute pattern is perfect for:

- ✅ Complex multi-step tasks
- ✅ Sequential tool coordination
- ✅ Tasks requiring visible planning
- ✅ Workflows needing adaptation

Start building systematic, reliable task completion systems today!

## License

This tutorial is part of the claude-php-agent package and is licensed under the MIT License.

