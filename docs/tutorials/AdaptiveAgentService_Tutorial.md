# Adaptive Agent Service Tutorial

Welcome to this comprehensive tutorial on the `AdaptiveAgentService`! This powerful meta-agent automatically selects the best agent for your tasks, validates results, and adapts when needed.

## Table of Contents

1. [Introduction](#introduction)
2. [What is the Adaptive Agent Service?](#what-is-the-adaptive-agent-service)
3. [Setup](#setup)
4. [Tutorial 1: Your First Adaptive Service](#tutorial-1-your-first-adaptive-service)
5. [Tutorial 2: Registering Multiple Agents](#tutorial-2-registering-multiple-agents)
6. [Tutorial 3: Understanding Agent Selection](#tutorial-3-understanding-agent-selection)
7. [Tutorial 4: Quality Validation and Thresholds](#tutorial-4-quality-validation-and-thresholds)
8. [Tutorial 5: Adaptive Retry and Reframing](#tutorial-5-adaptive-retry-and-reframing)
9. [Tutorial 6: Performance Tracking](#tutorial-6-performance-tracking)
10. [Tutorial 7: Building a Production System](#tutorial-7-building-a-production-system)
11. [Common Patterns](#common-patterns)
12. [Troubleshooting](#troubleshooting)
13. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to build intelligent, self-optimizing AI systems using the Adaptive Agent Service. By the end, you'll be able to:

- Understand when and why to use adaptive agent selection
- Set up and configure multiple agents with profiles
- Implement quality assurance and validation
- Build production-ready adaptive systems
- Monitor and optimize agent performance

## What is the Adaptive Agent Service?

The Adaptive Agent Service is a **meta-agent** that manages other agents. Think of it as a smart manager that:

1. **Analyzes** incoming tasks to understand requirements
2. **Selects** the best agent from your team based on capabilities
3. **Validates** results to ensure quality standards are met
4. **Adapts** by trying different approaches if the first attempt isn't good enough
5. **Learns** from experience to make better selections over time

**Without Adaptive Service:**

```php
// You manually decide which agent to use
if (needsCalculation($task)) {
    $result = $reactAgent->run($task);
} elseif (needsQuality($task)) {
    $result = $reflectionAgent->run($task);
} elseif (needsKnowledge($task)) {
    $result = $ragAgent->run($task);
}

// You manually validate
if (!isGoodEnough($result)) {
    // Try again? Different agent? You have to code this...
}
```

**With Adaptive Service:**

```php
// Service handles everything automatically
$result = $adaptiveService->run($task);
// ✓ Right agent selected
// ✓ Quality validated
// ✓ Adapted if needed
```

### Benefits

- **🎯 Automatic Selection**: No manual routing logic needed
- **✅ Quality Assurance**: Built-in validation with scoring
- **🔄 Self-Correcting**: Retries with different approaches
- **📊 Learning**: Gets smarter with each use
- **🚀 Production-Ready**: Error handling, logging, metrics

## Setup

First, install the package:

```bash
composer require claude-php/agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

Or create a `.env` file:

```env
ANTHROPIC_API_KEY=your-api-key-here
```

## Tutorial 1: Your First Adaptive Service

Let's create a simple adaptive service with two agents.

### Step 1: Create the Agents

Create a file `my_first_adaptive.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

// Create a calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function (array $input): string {
        $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $input['expression']);
        return (string) eval("return {$expr};");
    });

// Agent 1: React Agent (good with tools)
$reactAgent = new ReactAgent($client, [
    'tools' => [$calculator],
    'max_iterations' => 5,
]);

// Agent 2: Chain of Thought Agent (good at reasoning)
$cotAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

echo "Agents created!\n\n";
```

### Step 2: Create the Adaptive Service

Add this to your file:

```php
// Create the adaptive service
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,           // Try up to 3 times
    'quality_threshold' => 7.0,    // Require 7/10 quality
    'enable_reframing' => true,    // Reframe on failure
]);

echo "Adaptive service created!\n\n";
```

### Step 3: Register the Agents

```php
// Register React agent with its profile
$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'strengths' => ['tool usage', 'calculations', 'iterative solving'],
    'best_for' => ['math problems', 'data processing', 'API calls'],
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

// Register Chain of Thought agent
$service->registerAgent('cot', $cotAgent, [
    'type' => 'cot',
    'strengths' => ['step-by-step reasoning', 'explanations'],
    'best_for' => ['logic problems', 'reasoning tasks'],
    'complexity_level' => 'medium',
    'speed' => 'fast',
    'quality' => 'standard',
]);

echo "Agents registered!\n\n";
```

### Step 4: Run a Task

```php
// Test with a calculation task
$task = "Calculate (25 * 17) + 100 and explain the steps";

echo "Task: {$task}\n\n";

$result = $service->run($task);

if ($result->isSuccess()) {
    echo "✓ SUCCESS!\n\n";
    echo "Answer:\n{$result->getAnswer()}\n\n";
    
    $meta = $result->getMetadata();
    echo "Selected Agent: {$meta['final_agent']}\n";
    echo "Quality Score: {$meta['final_quality']}/10\n";
    echo "Attempts: {$result->getIterations()}\n";
} else {
    echo "✗ FAILED: {$result->getError()}\n";
}
```

### Step 5: Run It!

```bash
php my_first_adaptive.php
```

**Expected Output:**

```
Agents created!
Adaptive service created!
Agents registered!

Task: Calculate (25 * 17) + 100 and explain the steps

✓ SUCCESS!

Answer:
[The calculation result with explanation]

Selected Agent: react
Quality Score: 8.5/10
Attempts: 1
```

### 🎉 Congratulations!

You've created your first adaptive agent service! The service:
- ✅ Analyzed the task (calculation + explanation)
- ✅ Selected the React agent (has calculator tool)
- ✅ Validated the result (scored 8.5/10)
- ✅ Returned success on first attempt

---

## Tutorial 2: Registering Multiple Agents

Let's build a more comprehensive system with 4 different agents.

### Step 1: Create the Agents

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\RAGAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

// 1. React Agent - for tool-using tasks
$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression')
    ->handler(function ($input) {
        $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $input['expression']);
        return (string) eval("return {$expr};");
    });

$reactAgent = new ReactAgent($client, [
    'tools' => [$calculator],
    'max_iterations' => 5,
]);

// 2. Reflection Agent - for quality-critical tasks
$reflectionAgent = new ReflectionAgent($client, [
    'max_refinements' => 2,
    'quality_threshold' => 7,
]);

// 3. Chain of Thought - for reasoning
$cotAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
]);

// 4. RAG Agent - for knowledge-based tasks
$ragAgent = new RAGAgent($client);
$ragAgent->addDocument('PHP Basics', 
    'PHP is a server-side scripting language. Variables start with $. ' .
    'Functions use the function keyword. Arrays use array() or [].'
);
$ragAgent->addDocument('Laravel Framework',
    'Laravel is a PHP framework with routing, eloquent ORM, blade templates, ' .
    'artisan CLI, and built-in authentication.'
);

echo "✓ All 4 agents created\n\n";
```

### Step 2: Create and Configure Service

```php
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 7.5,  // Higher quality bar
    'enable_reframing' => true,
]);

echo "✓ Adaptive service created\n\n";
```

### Step 3: Register All Agents with Detailed Profiles

```php
// Register React agent
$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'strengths' => [
        'tool orchestration',
        'iterative problem solving',
        'calculations',
        'API integration'
    ],
    'best_for' => [
        'math problems',
        'data processing',
        'multi-step tasks',
        'tool-requiring tasks'
    ],
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

// Register Reflection agent
$service->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'strengths' => [
        'quality refinement',
        'self-improvement',
        'critical thinking',
        'polish and detail'
    ],
    'best_for' => [
        'code generation',
        'professional writing',
        'critical outputs',
        'high-quality content'
    ],
    'complexity_level' => 'medium',
    'speed' => 'slow',
    'quality' => 'high',
]);

// Register Chain of Thought agent
$service->registerAgent('cot', $cotAgent, [
    'type' => 'cot',
    'strengths' => [
        'step-by-step reasoning',
        'logical thinking',
        'transparent process',
        'explanations'
    ],
    'best_for' => [
        'logic problems',
        'reasoning tasks',
        'educational content',
        'analysis'
    ],
    'complexity_level' => 'medium',
    'speed' => 'fast',
    'quality' => 'standard',
]);

// Register RAG agent
$service->registerAgent('rag', $ragAgent, [
    'type' => 'rag',
    'strengths' => [
        'knowledge grounding',
        'source attribution',
        'factual accuracy',
        'document-based'
    ],
    'best_for' => [
        'Q&A systems',
        'documentation queries',
        'fact-based tasks',
        'knowledge retrieval'
    ],
    'complexity_level' => 'simple',
    'speed' => 'fast',
    'quality' => 'high',
]);

echo "✓ All agents registered with profiles\n\n";
```

### Step 4: Test with Different Task Types

```php
$tasks = [
    // Should select React agent (needs calculator)
    [
        'task' => 'Calculate the compound interest on $1000 at 5% for 3 years',
        'expected_agent' => 'react'
    ],
    
    // Should select Reflection agent (quality-critical)
    [
        'task' => 'Write a professional apology email to a client for a project delay',
        'expected_agent' => 'reflection'
    ],
    
    // Should select CoT agent (reasoning)
    [
        'task' => 'If all As are Bs and all Bs are Cs, are all As definitely Cs?',
        'expected_agent' => 'cot'
    ],
    
    // Should select RAG agent (knowledge-based)
    [
        'task' => 'What are the key features of the Laravel framework?',
        'expected_agent' => 'rag'
    ],
];

foreach ($tasks as $i => $test) {
    echo "═══════════════════════════════════════════════════════\n";
    echo "Test " . ($i + 1) . ": " . substr($test['task'], 0, 50) . "...\n";
    echo "═══════════════════════════════════════════════════════\n\n";
    
    $result = $service->run($test['task']);
    
    if ($result->isSuccess()) {
        $meta = $result->getMetadata();
        $selected = $meta['final_agent'];
        $quality = $meta['final_quality'];
        
        echo "✓ SUCCESS\n";
        echo "Selected: {$selected} ";
        
        if ($selected === $test['expected_agent']) {
            echo "✓ (correct!)\n";
        } else {
            echo "(expected: {$test['expected_agent']})\n";
        }
        
        echo "Quality: {$quality}/10\n";
        echo "Attempts: {$result->getIterations()}\n\n";
        
        // Show answer preview
        $answer = $result->getAnswer();
        echo "Answer: " . substr($answer, 0, 100) . "...\n\n";
    } else {
        echo "✗ FAILED: {$result->getError()}\n\n";
    }
}
```

### Step 5: Show Performance Summary

```php
echo "═══════════════════════════════════════════════════════\n";
echo "PERFORMANCE SUMMARY\n";
echo "═══════════════════════════════════════════════════════\n\n";

$performance = $service->getPerformance();

foreach ($performance as $agentId => $stats) {
    if ($stats['attempts'] > 0) {
        $successRate = round(($stats['successes'] / $stats['attempts']) * 100, 1);
        $avgQuality = round($stats['average_quality'], 1);
        $avgDuration = round($stats['total_duration'] / $stats['attempts'], 2);
        
        echo "{$agentId} Agent:\n";
        echo "  Attempts: {$stats['attempts']}\n";
        echo "  Success Rate: {$successRate}%\n";
        echo "  Avg Quality: {$avgQuality}/10\n";
        echo "  Avg Duration: {$avgDuration}s\n\n";
    }
}
```

### 🎓 What You Learned

- ✅ How to create and register multiple agents
- ✅ How to write detailed agent profiles
- ✅ How the service selects different agents for different tasks
- ✅ How to track performance across agents

---

## Tutorial 3: Understanding Agent Selection

Let's dive deep into how the service selects agents.

### The Selection Algorithm

The service scores each agent using multiple factors:

```php
<?php
// This tutorial explains the scoring system

echo "=== AGENT SELECTION SCORING ===\n\n";

// Factor 1: Complexity Matching (0-10 points)
echo "1. COMPLEXITY MATCHING (0-10 points)\n";
echo "   Task: Simple → Agent: Simple = 10 points\n";
echo "   Task: Medium → Agent: Medium = 10 points\n";
echo "   Task: Complex → Agent: Complex = 10 points\n";
echo "   Mismatches get lower scores\n\n";

// Factor 2: Quality Matching (0-10 points)
echo "2. QUALITY MATCHING (0-10 points)\n";
echo "   High quality task → High quality agent = 10 points\n";
echo "   Standard task → Standard agent = 10 points\n\n";

// Factor 3: Performance History (0-8 points)
echo "3. PERFORMANCE HISTORY (0-8 points)\n";
echo "   Success Rate: 0-5 points\n";
echo "   Average Quality: 0-3 points\n";
echo "   Agents get better scores as they prove themselves\n\n";

// Factor 4: Capability Bonuses (0-7 points each)
echo "4. CAPABILITY BONUSES (0-7 points each)\n";
echo "   ✓ Task needs tools → React agent (+5)\n";
echo "   ✓ High quality needed → Reflection agent (+5)\n";
echo "   ✓ Extreme quality → Maker agent (+7)\n";
echo "   ✓ Needs knowledge → RAG agent (+5)\n";
echo "   ✓ Needs reasoning → CoT/ToT agent (+5)\n";
echo "   ✓ Conversational → Dialog agent (+5)\n\n";

// Factor 5: Retry Penalty
echo "5. RETRY PENALTY (-10 points)\n";
echo "   Already tried agents lose points\n";
echo "   (unless all agents have been tried)\n\n";

echo "TOTAL: Agent with highest score wins!\n\n";
```

### Practical Example: Tracing Selection

Let's trace how a task gets scored:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

// Create logger to see decision-making
$logger = new Logger('adaptive');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$service = new AdaptiveAgentService($client, [
    'logger' => $logger,  // This will show scoring!
]);

// Register agents
$reactAgent = new ReactAgent($client, ['tools' => []]);
$reflectionAgent = new ReflectionAgent($client);

$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

$service->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'complexity_level' => 'medium',
    'quality' => 'high',
]);

// Run a task - watch the logs!
$task = "Write a professional cover letter for a software engineering position";

echo "Task: {$task}\n\n";
echo "Watch how agents are scored:\n\n";

$result = $service->run($task);

// The logs will show:
// - Task analysis results
// - Agent scores for each candidate
// - Which agent won and why
```

### 💡 Key Insights

1. **Better profiles = Better selection**: Accurate profiles help the service choose correctly
2. **Performance matters**: Agents that succeed get higher scores over time
3. **Specialization wins**: Specialized agents beat generalists for their domain
4. **History helps**: The more you use it, the smarter it gets

---

## Tutorial 4: Quality Validation and Thresholds

Understanding how quality validation works and setting appropriate thresholds.

### How Validation Works

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudePhp\ClaudePhp;

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

echo "=== QUALITY VALIDATION TUTORIAL ===\n\n";

// The service validates every result on 4 criteria:
echo "Validation Criteria:\n";
echo "1. Correctness - Is the answer factually correct?\n";
echo "2. Completeness - Is it thorough and complete?\n";
echo "3. Clarity - Is it well-structured and clear?\n";
echo "4. Relevance - Does it address the actual task?\n\n";

echo "Each gets scored, combined into 0-10 overall score\n\n";
```

### Setting Quality Thresholds

```php
// Experiment with different thresholds

// Low threshold (6.0) - More lenient
$lax_service = new AdaptiveAgentService($client, [
    'quality_threshold' => 6.0,
    'max_attempts' => 2,
]);

// Medium threshold (7.0) - Balanced
$balanced_service = new AdaptiveAgentService($client, [
    'quality_threshold' => 7.0,
    'max_attempts' => 3,
]);

// High threshold (8.5) - Strict
$strict_service = new AdaptiveAgentService($client, [
    'quality_threshold' => 8.5,
    'max_attempts' => 5,
]);

$reactAgent = new ReactAgent($client, ['tools' => []]);

foreach ([$lax_service, $balanced_service, $strict_service] as $service) {
    $service->registerAgent('react', $reactAgent, [
        'type' => 'react',
        'complexity_level' => 'medium',
        'quality' => 'standard',
    ]);
}

$task = "Explain the concept of dependency injection";

echo "Testing same task with different thresholds:\n\n";

// Test with lax threshold
echo "1. LAX (6.0 threshold):\n";
$result = $lax_service->run($task);
$meta = $result->getMetadata();
echo "   Attempts: {$result->getIterations()}\n";
echo "   Final Quality: {$meta['final_quality']}/10\n\n";

// Test with balanced threshold
echo "2. BALANCED (7.0 threshold):\n";
$result = $balanced_service->run($task);
$meta = $result->getMetadata();
echo "   Attempts: {$result->getIterations()}\n";
echo "   Final Quality: {$meta['final_quality']}/10\n\n";

// Test with strict threshold
echo "3. STRICT (8.5 threshold):\n";
$result = $strict_service->run($task);
$meta = $result->getMetadata();
echo "   Attempts: {$result->getIterations()}\n";
echo "   Final Quality: {$meta['final_quality']}/10\n\n";

echo "Notice:\n";
echo "- Higher thresholds may require more attempts\n";
echo "- Balance quality needs vs. cost/time\n";
echo "- Production systems often use 7.0-7.5\n";
```

### Inspecting Validation Details

```php
$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 7.0,
]);

$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

$result = $service->run("What is polymorphism in OOP?");

if ($result->isSuccess()) {
    $meta = $result->getMetadata();
    
    echo "=== VALIDATION DETAILS ===\n\n";
    
    // Look at each attempt's validation
    foreach ($meta['attempts'] as $attempt) {
        echo "Attempt {$attempt['attempt']}:\n";
        echo "  Agent: {$attempt['agent_type']}\n";
        
        $validation = $attempt['validation'];
        echo "  Quality Score: {$validation['quality_score']}/10\n";
        echo "  Correct: " . ($validation['is_correct'] ? 'Yes' : 'No') . "\n";
        echo "  Complete: " . ($validation['is_complete'] ? 'Yes' : 'No') . "\n";
        
        if (!empty($validation['issues'])) {
            echo "  Issues:\n";
            foreach ($validation['issues'] as $issue) {
                echo "    - {$issue}\n";
            }
        }
        
        if (!empty($validation['strengths'])) {
            echo "  Strengths:\n";
            foreach ($validation['strengths'] as $strength) {
                echo "    + {$strength}\n";
            }
        }
        
        echo "\n";
    }
}
```

### 🎯 Recommendations

**For Development:**
```php
'quality_threshold' => 6.0,  // More lenient, faster iteration
'max_attempts' => 2,
```

**For Production:**
```php
'quality_threshold' => 7.5,  // Good balance
'max_attempts' => 3,
```

**For Critical Systems:**
```php
'quality_threshold' => 8.5,  // Very high quality
'max_attempts' => 5,
```

---

## Tutorial 5: Adaptive Retry and Reframing

Learn how the service adapts when initial attempts fail.

### Understanding Adaptive Retry

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

echo "=== ADAPTIVE RETRY TUTORIAL ===\n\n";

$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 8.0,  // High bar to trigger retries
    'enable_reframing' => true,
]);

// Register multiple agents so service has options
$reactAgent = new ReactAgent($client, ['tools' => []]);
$reflectionAgent = new ReflectionAgent($client);
$cotAgent = new ChainOfThoughtAgent($client);

$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

$service->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'complexity_level' => 'medium',
    'quality' => 'high',
]);

$service->registerAgent('cot', $cotAgent, [
    'type' => 'cot',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

// Use a somewhat vague task to trigger adaptation
$task = "Explain MVC";  // Vague - might need reframing

echo "Original Task: {$task}\n\n";
echo "Running with high quality threshold (8.0)...\n\n";

$result = $service->run($task);

$meta = $result->getMetadata();

echo "=== ADAPTATION HISTORY ===\n\n";

foreach ($meta['attempts'] as $i => $attempt) {
    echo "Attempt " . ($i + 1) . ":\n";
    echo "  Agent: {$attempt['agent_type']}\n";
    echo "  Quality: {$attempt['validation']['quality_score']}/10\n";
    
    if (!empty($attempt['validation']['issues'])) {
        echo "  Issues:\n";
        foreach ($attempt['validation']['issues'] as $issue) {
            echo "    - {$issue}\n";
        }
    }
    
    echo "  Duration: {$attempt['duration']}s\n\n";
    
    // Show if task was reframed
    if ($i < count($meta['attempts']) - 1) {
        echo "  → Trying different approach...\n\n";
    }
}

if ($result->isSuccess()) {
    echo "✓ Final Success!\n";
    echo "Final Agent: {$meta['final_agent']}\n";
    echo "Final Quality: {$meta['final_quality']}/10\n";
} else {
    echo "✗ Could not meet quality threshold\n";
    echo "Best attempt: {$meta['best_attempt']['validation']['quality_score']}/10\n";
}
```

### Request Reframing in Action

```php
echo "\n=== REQUEST REFRAMING ===\n\n";

// Reframing happens when quality is significantly below threshold
// Let's see it in action

$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 7.0,
    'enable_reframing' => true,
]);

$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
]);

// Very vague task likely to get reframed
$vagueTask = "Tell me about it";

echo "Very vague task: '{$vagueTask}'\n\n";

$result = $service->run($vagueTask);

// Check if reframing occurred by looking at metadata
$meta = $result->getMetadata();

echo "Task Analysis:\n";
print_r($meta['task_analysis']);
echo "\n";

// Note: In real implementation, you'd see the reframed
// task in logs. The service makes it more specific.
```

### Controlling Reframing Behavior

```php
// Disable reframing
$noReframe = new AdaptiveAgentService($client, [
    'enable_reframing' => false,  // Just try different agents
]);

// Enable reframing (default)
$withReframe = new AdaptiveAgentService($client, [
    'enable_reframing' => true,
]);

echo "Without reframing:\n";
echo "- Tries different agents\n";
echo "- Doesn't modify the task\n";
echo "- Faster, fewer API calls\n\n";

echo "With reframing:\n";
echo "- Tries different agents\n";
echo "- Can clarify vague tasks\n";
echo "- Higher success rate\n";
echo "- Slightly higher cost\n";
```

### 💡 Key Concepts

1. **Retry != Simple Repeat**: Service tries *different* agents, not the same one
2. **Reframing**: Makes vague tasks more specific
3. **Learning**: Failed attempts inform better choices
4. **Graceful Degradation**: Returns best attempt if threshold not met

---

## Tutorial 6: Performance Tracking

Monitor and optimize your agent fleet.

### Basic Performance Metrics

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;

$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

$service = new AdaptiveAgentService($client);

$reactAgent = new ReactAgent($client, ['tools' => []]);
$reflectionAgent = new ReflectionAgent($client);
$cotAgent = new ChainOfThoughtAgent($client);

$service->registerAgent('react', $reactAgent, [...]);
$service->registerAgent('reflection', $reflectionAgent, [...]);
$service->registerAgent('cot', $cotAgent, [...]);

// Run a bunch of tasks
$tasks = [
    "Calculate 15% of $250",
    "Write a professional email",
    "If A > B and B > C, what about A and C?",
    "Explain polymorphism",
    "Calculate compound interest",
    "Write a project proposal",
];

echo "Running {count($tasks)} tasks...\n\n";

foreach ($tasks as $task) {
    $service->run($task);
}

// Get performance metrics
$performance = $service->getPerformance();

echo "=== PERFORMANCE REPORT ===\n\n";

foreach ($performance as $agentId => $stats) {
    if ($stats['attempts'] == 0) continue;
    
    echo "{$agentId} Agent:\n";
    echo "  Total Attempts: {$stats['attempts']}\n";
    echo "  Successes: {$stats['successes']}\n";
    echo "  Failures: {$stats['failures']}\n";
    
    $successRate = round(($stats['successes'] / $stats['attempts']) * 100, 1);
    echo "  Success Rate: {$successRate}%\n";
    
    $avgQuality = round($stats['average_quality'], 2);
    echo "  Avg Quality: {$avgQuality}/10\n";
    
    $avgDuration = round($stats['total_duration'] / $stats['attempts'], 2);
    echo "  Avg Duration: {$avgDuration}s\n";
    
    echo "\n";
}
```

### Advanced Analytics

```php
// Build a performance dashboard

function analyzePerformance(AdaptiveAgentService $service): array {
    $perf = $service->getPerformance();
    $analysis = [];
    
    foreach ($perf as $agentId => $stats) {
        if ($stats['attempts'] == 0) continue;
        
        $analysis[$agentId] = [
            'success_rate' => ($stats['successes'] / $stats['attempts']) * 100,
            'avg_quality' => $stats['average_quality'],
            'avg_speed' => $stats['total_duration'] / $stats['attempts'],
            'reliability' => ($stats['successes'] / $stats['attempts']) * $stats['average_quality'],
        ];
    }
    
    return $analysis;
}

$analysis = analyzePerformance($service);

echo "=== ADVANCED ANALYTICS ===\n\n";

// Find best overall agent
$bestAgent = null;
$bestScore = 0;

foreach ($analysis as $agentId => $metrics) {
    $score = $metrics['reliability'];
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestAgent = $agentId;
    }
}

echo "Best Overall Agent: {$bestAgent}\n";
echo "  Reliability Score: " . round($bestScore, 2) . "/10\n\n";

// Find fastest agent
$fastestAgent = null;
$fastestTime = PHP_FLOAT_MAX;

foreach ($analysis as $agentId => $metrics) {
    if ($metrics['avg_speed'] < $fastestTime) {
        $fastestTime = $metrics['avg_speed'];
        $fastestAgent = $agentId;
    }
}

echo "Fastest Agent: {$fastestAgent}\n";
echo "  Avg Duration: " . round($fastestTime, 2) . "s\n\n";

// Find highest quality agent
$qualityAgent = null;
$highestQuality = 0;

foreach ($analysis as $agentId => $metrics) {
    if ($metrics['avg_quality'] > $highestQuality) {
        $highestQuality = $metrics['avg_quality'];
        $qualityAgent = $agentId;
    }
}

echo "Highest Quality Agent: {$qualityAgent}\n";
echo "  Avg Quality: " . round($highestQuality, 2) . "/10\n";
```

### Exporting Metrics for Monitoring

```php
// Export for external monitoring tools

function exportMetrics(AdaptiveAgentService $service, string $format = 'json'): string {
    $perf = $service->getPerformance();
    $timestamp = time();
    
    $export = [
        'timestamp' => $timestamp,
        'date' => date('Y-m-d H:i:s', $timestamp),
        'agents' => [],
    ];
    
    foreach ($perf as $agentId => $stats) {
        $export['agents'][$agentId] = [
            'attempts' => $stats['attempts'],
            'successes' => $stats['successes'],
            'failures' => $stats['failures'],
            'success_rate' => $stats['attempts'] > 0 
                ? ($stats['successes'] / $stats['attempts']) * 100 
                : 0,
            'average_quality' => $stats['average_quality'],
            'total_duration' => $stats['total_duration'],
            'average_duration' => $stats['attempts'] > 0
                ? $stats['total_duration'] / $stats['attempts']
                : 0,
        ];
    }
    
    if ($format === 'json') {
        return json_encode($export, JSON_PRETTY_PRINT);
    }
    
    // CSV format
    $csv = "Agent,Attempts,Successes,Failures,Success Rate,Avg Quality,Avg Duration\n";
    foreach ($export['agents'] as $agentId => $data) {
        $csv .= sprintf(
            "%s,%d,%d,%d,%.1f%%,%.2f,%.2fs\n",
            $agentId,
            $data['attempts'],
            $data['successes'],
            $data['failures'],
            $data['success_rate'],
            $data['average_quality'],
            $data['average_duration']
        );
    }
    
    return $csv;
}

// Export as JSON
echo "JSON Export:\n";
echo exportMetrics($service, 'json');
echo "\n\n";

// Export as CSV
echo "CSV Export:\n";
echo exportMetrics($service, 'csv');
```

### 📊 Monitoring Best Practices

1. **Track Trends**: Monitor performance over time
2. **Set Alerts**: Alert if success rates drop
3. **A/B Testing**: Compare agent configurations
4. **Cost Analysis**: Balance quality vs. token usage
5. **Regular Review**: Weekly performance reviews

---

## Tutorial 7: Building a Production System

Put it all together for a production-ready implementation.

### Complete Production Example

```php
<?php
/**
 * Production Adaptive Agent Service
 * 
 * Features:
 * - Multiple specialized agents
 * - Comprehensive logging
 * - Error handling
 * - Performance monitoring
 * - Configuration management
 */

require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Agents\RAGAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Dotenv\Dotenv;

// Load configuration
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup comprehensive logging
$logger = new Logger('production_adaptive');
$logger->pushHandler(
    new RotatingFileHandler('logs/adaptive.log', 30, Logger::INFO)
);
$logger->pushHandler(
    new StreamHandler('php://stderr', Logger::ERROR)
);

$logger->info('Starting Adaptive Agent Service');

// Initialize client
$client = ClaudePhp::make($_ENV['ANTHROPIC_API_KEY']);

// Create production-grade service
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 7.5,
    'enable_reframing' => true,
    'logger' => $logger,
]);

// Build calculator tool with validation
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Mathematical expression')
    ->handler(function (array $input) use ($logger): string {
        try {
            // Validate expression
            $expr = $input['expression'];
            if (!preg_match('/^[\d+\-*\/(). ]+$/', $expr)) {
                throw new \InvalidArgumentException('Invalid expression');
            }
            
            // Calculate
            $result = eval("return {$expr};");
            $logger->info('Calculation successful', [
                'expression' => $expr,
                'result' => $result,
            ]);
            
            return (string) $result;
        } catch (\Throwable $e) {
            $logger->error('Calculation failed', [
                'expression' => $input['expression'] ?? '',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    });

// Register agents with comprehensive profiles
try {
    // React Agent
    $reactAgent = new ReactAgent($client, [
        'tools' => [$calculator],
        'max_iterations' => 5,
    ]);
    
    $service->registerAgent('react', $reactAgent, [
        'type' => 'react',
        'strengths' => ['tool usage', 'calculations', 'iterative solving'],
        'best_for' => ['math', 'data processing', 'API calls'],
        'complexity_level' => 'medium',
        'speed' => 'medium',
        'quality' => 'standard',
    ]);
    
    $logger->info('Registered React agent');
    
    // Reflection Agent
    $reflectionAgent = new ReflectionAgent($client, [
        'max_refinements' => 2,
        'quality_threshold' => 7,
    ]);
    
    $service->registerAgent('reflection', $reflectionAgent, [
        'type' => 'reflection',
        'strengths' => ['quality refinement', 'polish'],
        'best_for' => ['writing', 'code generation', 'critical content'],
        'complexity_level' => 'medium',
        'speed' => 'slow',
        'quality' => 'high',
    ]);
    
    $logger->info('Registered Reflection agent');
    
    // Chain of Thought Agent
    $cotAgent = new ChainOfThoughtAgent($client);
    
    $service->registerAgent('cot', $cotAgent, [
        'type' => 'cot',
        'strengths' => ['reasoning', 'step-by-step logic'],
        'best_for' => ['logic problems', 'analysis'],
        'complexity_level' => 'medium',
        'speed' => 'fast',
        'quality' => 'standard',
    ]);
    
    $logger->info('Registered CoT agent');
    
    // RAG Agent
    $ragAgent = new RAGAgent($client);
    $ragAgent->addDocuments([
        ['title' => 'Company Policies', 'content' => '...'],
        ['title' => 'Product Documentation', 'content' => '...'],
    ]);
    
    $service->registerAgent('rag', $ragAgent, [
        'type' => 'rag',
        'strengths' => ['knowledge grounding', 'factual accuracy'],
        'best_for' => ['Q&A', 'documentation queries'],
        'complexity_level' => 'simple',
        'speed' => 'fast',
        'quality' => 'high',
    ]);
    
    $logger->info('Registered RAG agent');
    
} catch (\Throwable $e) {
    $logger->critical('Failed to register agents', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}

// Production request handler
function handleRequest(AdaptiveAgentService $service, Logger $logger, string $task): array {
    $startTime = microtime(true);
    
    try {
        $logger->info('Processing request', ['task' => substr($task, 0, 100)]);
        
        $result = $service->run($task);
        $duration = microtime(true) - $startTime;
        
        if ($result->isSuccess()) {
            $meta = $result->getMetadata();
            
            $logger->info('Request successful', [
                'agent' => $meta['final_agent'],
                'quality' => $meta['final_quality'],
                'attempts' => $result->getIterations(),
                'duration' => round($duration, 3),
            ]);
            
            return [
                'success' => true,
                'answer' => $result->getAnswer(),
                'metadata' => [
                    'agent_used' => $meta['final_agent'],
                    'quality_score' => $meta['final_quality'],
                    'attempts' => $result->getIterations(),
                    'duration_seconds' => round($duration, 3),
                ],
            ];
        } else {
            $logger->warning('Request failed', [
                'error' => $result->getError(),
                'duration' => round($duration, 3),
            ]);
            
            return [
                'success' => false,
                'error' => $result->getError(),
                'metadata' => $result->getMetadata(),
            ];
        }
    } catch (\Throwable $e) {
        $duration = microtime(true) - $startTime;
        
        $logger->error('Request exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'duration' => round($duration, 3),
        ]);
        
        return [
            'success' => false,
            'error' => 'Internal error: ' . $e->getMessage(),
        ];
    }
}

// Example usage
$task = "Calculate the monthly payment for a $300,000 loan at 4.5% APR over 30 years";
$response = handleRequest($service, $logger, $task);

if ($response['success']) {
    echo "Success!\n";
    echo "Answer: {$response['answer']}\n";
    echo "Agent: {$response['metadata']['agent_used']}\n";
    echo "Quality: {$response['metadata']['quality_score']}/10\n";
} else {
    echo "Failed: {$response['error']}\n";
}

// Performance reporting
$performance = $service->getPerformance();
$logger->info('Performance snapshot', $performance);

echo "\nProduction system ready!\n";
```

### Configuration Management

Create `config/adaptive.php`:

```php
<?php

return [
    'service' => [
        'max_attempts' => env('ADAPTIVE_MAX_ATTEMPTS', 3),
        'quality_threshold' => env('ADAPTIVE_QUALITY_THRESHOLD', 7.5),
        'enable_reframing' => env('ADAPTIVE_ENABLE_REFRAMING', true),
    ],
    
    'agents' => [
        'react' => [
            'enabled' => true,
            'profile' => [
                'type' => 'react',
                'complexity_level' => 'medium',
                'speed' => 'medium',
                'quality' => 'standard',
            ],
        ],
        'reflection' => [
            'enabled' => true,
            'profile' => [
                'type' => 'reflection',
                'complexity_level' => 'medium',
                'speed' => 'slow',
                'quality' => 'high',
            ],
        ],
        // ... more agents
    ],
    
    'monitoring' => [
        'enabled' => true,
        'log_path' => 'logs/adaptive.log',
        'metrics_export' => 'metrics/adaptive_metrics.json',
        'alert_threshold' => 0.7, // Alert if success rate < 70%
    ],
];
```

### Health Check Endpoint

```php
<?php

function healthCheck(AdaptiveAgentService $service): array {
    $perf = $service->getPerformance();
    $agents = $service->getRegisteredAgents();
    
    $totalAttempts = 0;
    $totalSuccesses = 0;
    $unhealthyAgents = [];
    
    foreach ($perf as $agentId => $stats) {
        $totalAttempts += $stats['attempts'];
        $totalSuccesses += $stats['successes'];
        
        if ($stats['attempts'] > 10) {
            $successRate = $stats['successes'] / $stats['attempts'];
            if ($successRate < 0.7) {
                $unhealthyAgents[] = $agentId;
            }
        }
    }
    
    $overallSuccessRate = $totalAttempts > 0 
        ? $totalSuccesses / $totalAttempts 
        : 1.0;
    
    $status = empty($unhealthyAgents) && $overallSuccessRate >= 0.7
        ? 'healthy'
        : 'degraded';
    
    return [
        'status' => $status,
        'agents_registered' => count($agents),
        'total_requests' => $totalAttempts,
        'success_rate' => round($overallSuccessRate * 100, 2),
        'unhealthy_agents' => $unhealthyAgents,
        'timestamp' => time(),
    ];
}

$health = healthCheck($service);
echo json_encode($health, JSON_PRETTY_PRINT);
```

### 🚀 Production Checklist

- [x] Comprehensive logging
- [x] Error handling and recovery
- [x] Configuration management
- [x] Performance monitoring
- [x] Health checks
- [x] Input validation
- [x] Rate limiting (if needed)
- [x] Metrics export
- [x] Alert thresholds
- [x] Documentation

---

## Common Patterns

### Pattern 1: Customer Support Router

```php
// Automatically route customer inquiries to appropriate agents

$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 7.0,
]);

// FAQ Agent - fast simple answers
$service->registerAgent('faq', $reflexAgent, [
    'type' => 'reflex',
    'complexity_level' => 'simple',
    'speed' => 'fast',
    'quality' => 'standard',
]);

// Dialog Agent - conversations
$service->registerAgent('dialog', $dialogAgent, [
    'type' => 'dialog',
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

// Technical Agent - complex issues
$service->registerAgent('technical', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'complex',
    'speed' => 'slow',
    'quality' => 'high',
]);

// Service automatically routes!
$response = $service->run($customerMessage);
```

### Pattern 2: Content Pipeline

```php
// Ensure high-quality content generation

$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 8.0,  // High quality bar
    'max_attempts' => 5,
]);

$service->registerAgent('writer', $reactAgent, [...]);
$service->registerAgent('editor', $reflectionAgent, [...]);
$service->registerAgent('polisher', $reflectionAgent, [...]);

// Will try different agents until quality is met
$article = $service->run("Write about: {$topic}");
```

### Pattern 3: Code Assistant

```php
// Generate and validate code

$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 7.5,
]);

$service->registerAgent('coder', $reactAgent, [...]);
$service->registerAgent('reviewer', $reflectionAgent, [...]);
$service->registerAgent('tester', $makerAgent, [...]);

$code = $service->run("Implement a binary search tree in PHP");
// Automatically validated for quality
```

### Pattern 4: Research Assistant

```php
// Combine knowledge and reasoning

$service = new AdaptiveAgentService($client);

$service->registerAgent('searcher', $reactAgent, [...]);
$service->registerAgent('knowledge', $ragAgent, [...]);
$service->registerAgent('synthesizer', $reflectionAgent, [...]);

$research = $service->run("Research and summarize: {$topic}");
```

---

## Troubleshooting

### Issue: Service Always Picks Wrong Agent

**Symptoms**: Wrong agent consistently selected

**Solutions**:
1. Check agent profiles - are they accurate?
2. Look at task analysis - is it understanding correctly?
3. Enable debug logging to see scoring
4. Adjust agent profiles to be more specific

```php
// Debug scoring
$logger = new Logger('debug');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$service = new AdaptiveAgentService($client, [
    'logger' => $logger,  // See decision-making
]);
```

### Issue: Quality Threshold Never Met

**Symptoms**: Service exhausts all attempts without success

**Solutions**:
1. Lower the quality threshold
2. Add more capable agents
3. Check if task is clear enough
4. Enable reframing

```php
// Before
'quality_threshold' => 9.0,  // Too strict

// After
'quality_threshold' => 7.0,  // More reasonable
'enable_reframing' => true,  // Help with vague tasks
```

### Issue: Too Slow / Too Expensive

**Symptoms**: High latency or token costs

**Solutions**:
1. Reduce max_attempts
2. Disable reframing
3. Register faster agents
4. Lower quality threshold

```php
// Optimize for speed/cost
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 2,           // Fewer retries
    'enable_reframing' => false,   // Skip reframing
    'quality_threshold' => 6.5,    // Lower bar
]);
```

### Issue: One Agent Dominates

**Symptoms**: One agent handles everything

**Solutions**:
1. Make agent profiles more specific
2. Add more specialized agents
3. Check if one agent's profile is too broad

```php
// Too broad
'best_for' => ['everything', 'all tasks']

// Better
'best_for' => ['calculations', 'data processing', 'API calls']
```

---

## Next Steps

Congratulations! You've completed the Adaptive Agent Service tutorial. Here's what to explore next:

### 1. Advanced Topics

- **Custom Validation Logic** - Extend validation for your needs
- **A/B Testing** - Compare agent configurations
- **Cost Optimization** - Balance quality vs. token usage
- **Async Operations** - Parallel agent execution

### 2. Integration Examples

- **API Endpoint** - Build REST API with adaptive routing
- **Chat Interface** - Conversational system with adaptation
- **Batch Processing** - Process many tasks efficiently
- **Workflow Automation** - Multi-step automated workflows

### 3. Related Documentation

- [Adaptive Agent Service Docs](../adaptive-agent-service.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Agent Taxonomy](../../AGENT_TAXONOMY.md)
- [Other Agent Tutorials](./README.md)

### 4. Practice Projects

1. **Smart Customer Support** - Build adaptive support system
2. **Content Generator** - Quality-assured content pipeline
3. **Code Assistant** - Adaptive coding helper
4. **Research Tool** - Multi-agent research system

### 5. Community

- Share your implementations
- Report issues and improvements
- Contribute agent profiles
- Join discussions

---

## Summary

You've learned how to:

- ✅ Create and configure the Adaptive Agent Service
- ✅ Register multiple agents with detailed profiles
- ✅ Understand the agent selection algorithm
- ✅ Set quality thresholds and validation
- ✅ Implement adaptive retry and reframing
- ✅ Track and optimize performance
- ✅ Build production-ready systems
- ✅ Apply common patterns
- ✅ Troubleshoot issues

The Adaptive Agent Service provides intelligent orchestration that ensures you always get the best possible results. Use it to build reliable, high-quality AI systems that improve over time!

---

**Happy building! 🚀**

