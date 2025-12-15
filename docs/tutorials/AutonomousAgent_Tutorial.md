# AutonomousAgent Tutorial

## Introduction

Welcome to the AutonomousAgent tutorial! In this guide, you'll learn how to build goal-oriented agents that can maintain state across multiple sessions, track progress, and work autonomously toward defined objectives.

### What You'll Learn

1. Creating basic autonomous agents
2. Setting and tracking goals
3. Managing state persistence
4. Running multi-session workflows
5. Monitoring progress and completion
6. Advanced patterns and best practices

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key (for live examples)
- Basic understanding of PHP and the Claude API

### Time Required

Approximately 30-45 minutes

---

## Chapter 1: Your First Autonomous Agent

Let's start with a simple autonomous agent that helps plan a project.

### Step 1: Set Up Your Environment

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\AutonomousAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create Your First Agent

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Plan a simple website project with frontend and backend',
]);

echo "Agent Goal: {$agent->getGoal()}\n";
echo "Initial Progress: {$agent->getProgress()}%\n";
```

### Step 3: Run Your First Session

```php
$result = $agent->runSession(
    'Start by defining the main components and technologies to use'
);

if ($result->isSuccess()) {
    echo "\n✓ Session completed!\n";
    echo "Progress: {$result->getMetadata()['goal_progress']}%\n";
    echo "\nAgent's Plan:\n{$result->getAnswer()}\n";
} else {
    echo "\n✗ Session failed: {$result->getError()}\n";
}
```

### What Just Happened?

1. **Agent Created**: We created an autonomous agent with a clear goal
2. **Session Executed**: The agent started working on the goal
3. **Progress Tracked**: The agent automatically tracks progress (usually starts at 10%)
4. **State Updated**: All actions and conversations are recorded

### Try It Yourself

Create an agent with a different goal:

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Create a meal plan for the week including breakfast, lunch, and dinner',
]);

$result = $agent->runSession('Start with Monday and Tuesday');
```

---

## Chapter 2: State Persistence

One of the most powerful features is state persistence—your agent can pause and resume work.

### Step 1: Add State File

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Design a microservices architecture',
    'state_file' => './my_agent_state.json',  // State will be saved here
    'name' => 'architecture_planner',
]);
```

### Step 2: Run Multiple Sessions

```php
// Session 1: Initial planning
echo "=== Session 1 ===\n";
$result1 = $agent->runSession('Start by listing the main services we need');

echo "Progress after session 1: {$agent->getProgress()}%\n";
echo "State saved to: my_agent_state.json\n\n";

// Session 2: Detailed design
echo "=== Session 2 ===\n";
$agent->getState()->incrementSession();
$result2 = $agent->runSession('Now design the API gateway and authentication');

echo "Progress after session 2: {$agent->getProgress()}%\n";
```

### Step 3: Resume from Saved State

```php
// This could be in a completely different script or run later
$resumedAgent = new AutonomousAgent($client, [
    'goal' => 'Design a microservices architecture',  // Same goal
    'state_file' => './my_agent_state.json',          // Same state file
]);

echo "Resumed agent progress: {$resumedAgent->getProgress()}%\n";
echo "Session number: {$resumedAgent->getState()->getSessionNumber()}\n";

// Continue from where we left off
$result3 = $resumedAgent->runSession('Continue with the deployment strategy');
```

### Understanding the State File

Let's look at what's saved:

```php
// Read and display the state
$stateData = json_decode(file_get_contents('./my_agent_state.json'), true);

echo "Session: {$stateData['session_number']}\n";
echo "Goal Status: {$stateData['goal']['status']}\n";
echo "Progress: {$stateData['goal']['progress_percentage']}%\n";
echo "Messages: " . count($stateData['conversation_history']) . "\n";
echo "Actions: " . count($stateData['action_history']) . "\n";
```

### Practice Exercise

Create an agent that works on a multi-day project:

```php
// Day 1
$agent = new AutonomousAgent($client, [
    'goal' => 'Write a comprehensive blog post about PHP 8.4 features',
    'state_file' => './blog_post_agent.json',
]);

$agent->runSession('Outline the main sections and topics to cover');

// Day 2 (simulated - could be next day)
$agent = new AutonomousAgent($client, [
    'goal' => 'Write a comprehensive blog post about PHP 8.4 features',
    'state_file' => './blog_post_agent.json',
]);

$agent->runSession('Write the introduction and first two sections');
```

---

## Chapter 3: Goal Tracking and Subgoals

Learn how to track detailed progress with goals and subgoals.

### Step 1: Access Goal Information

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Build a REST API with authentication',
    'state_file' => './api_project.json',
]);

// Run a session
$result = $agent->runSession('Start planning the API structure');

// Access goal details
$goal = $agent->getState()->getGoal();

echo "Goal: {$goal->getDescription()}\n";
echo "Status: {$goal->getStatus()}\n";
echo "Progress: {$goal->getProgressPercentage()}%\n";
```

### Step 2: Track Subgoals

```php
// After completing specific tasks, mark subgoals
$goal->completeSubgoal('API structure designed');
$goal->completeSubgoal('Authentication endpoints defined');
$goal->completeSubgoal('Database schema created');

echo "Completed Subgoals:\n";
foreach ($goal->getCompletedSubgoals() as $subgoal) {
    echo "  ✓ {$subgoal}\n";
}
```

### Step 3: Check Completion

```php
if ($agent->isGoalComplete()) {
    echo "🎉 Goal achieved!\n";

    $state = $agent->getState();
    echo "Total Sessions: {$state->getSessionNumber()}\n";
    echo "Total Actions: " . count($state->getActionHistory()) . "\n";
} else {
    echo "Still working... {$agent->getProgress()}% complete\n";
}
```

### Goal Status Lifecycle

```
not_started  →  in_progress  →  completed
   (0%)           (1-99%)        (100%)
```

### Exercise: Build a Learning Agent

Create an agent that tracks learning progress:

```php
$learningAgent = new AutonomousAgent($client, [
    'goal' => 'Master PHP design patterns',
    'state_file' => './learning_agent.json',
]);

$result = $learningAgent->runSession('Start with Singleton pattern');

// Track what's learned
$goal = $learningAgent->getState()->getGoal();
$goal->completeSubgoal('Singleton pattern');
$goal->completeSubgoal('Factory pattern');
$goal->completeSubgoal('Observer pattern');

echo "Patterns learned: " . count($goal->getCompletedSubgoals()) . "\n";
```

---

## Chapter 4: Running Until Complete

Sometimes you want the agent to keep working until the goal is achieved.

### Basic Usage

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Create a deployment checklist for a web application',
    'state_file' => './deployment_agent.json',
    'max_actions_per_session' => 10,
]);

// Run up to 5 sessions or until complete
$results = $agent->runUntilComplete(maxSessions: 5);

echo "Completed in " . count($results) . " sessions\n";
echo "Final progress: {$agent->getProgress()}%\n";
echo "Goal complete: " . ($agent->isGoalComplete() ? 'Yes' : 'No') . "\n";
```

### Analyzing Results

```php
foreach ($results as $i => $result) {
    $sessionNum = $i + 1;

    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        echo "Session {$sessionNum}: {$metadata['goal_progress']}% complete\n";
    } else {
        echo "Session {$sessionNum}: Failed - {$result->getError()}\n";
        break;
    }
}
```

### With Progress Monitoring

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Design a database schema for an e-commerce platform',
    'state_file' => './ecommerce_schema.json',
]);

$progressMilestones = [25, 50, 75, 100];
$reachedMilestones = [];

$results = $agent->runUntilComplete(maxSessions: 10);

foreach ($results as $result) {
    if ($result->isSuccess()) {
        $progress = $result->getMetadata()['goal_progress'];

        foreach ($progressMilestones as $milestone) {
            if ($progress >= $milestone && !in_array($milestone, $reachedMilestones)) {
                echo "🎯 Milestone reached: {$milestone}%\n";
                $reachedMilestones[] = $milestone;
            }
        }
    }
}
```

---

## Chapter 5: Advanced Configuration

### Custom Configuration

```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Develop an API integration strategy',
    'state_file' => './api_strategy.json',
    'name' => 'api_strategist',
    'max_actions_per_session' => 20,
    'logger' => $customLogger,  // PSR-3 compatible logger
]);
```

### Adding Metadata

```php
$state = $agent->getState();

// Add custom metadata
$state->setMetadataValue('project_id', 'proj_12345');
$state->setMetadataValue('priority', 'high');
$state->setMetadataValue('deadline', '2025-01-31');
$state->setMetadataValue('team', 'backend');

// Retrieve metadata
$projectId = $state->getMetadataValue('project_id');
$priority = $state->getMetadataValue('priority', 'medium');  // with default
```

### Custom Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('autonomous_agent');
$logger->pushHandler(new StreamHandler('./logs/agent.log', Logger::INFO));

$agent = new AutonomousAgent($client, [
    'goal' => 'Plan a security audit',
    'logger' => $logger,
]);

// Logger will automatically record:
// - Agent initialization
// - Session starts
// - Session completions
// - Goal progress
// - Errors
```

### Practice: Production-Ready Agent

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Production logging
$logger = new Logger('production_agent');
$logger->pushHandler(
    new RotatingFileHandler('./logs/agent.log', 7, Logger::INFO)
);

// Production agent
$agent = new AutonomousAgent($client, [
    'goal' => 'Create a comprehensive test strategy',
    'state_file' => '/var/app/data/agents/test_strategy.json',
    'name' => 'test_strategist',
    'max_actions_per_session' => 50,
    'logger' => $logger,
]);

// Add production metadata
$state = $agent->getState();
$state->setMetadataValue('environment', 'production');
$state->setMetadataValue('version', '1.0.0');
$state->setMetadataValue('created_by', 'user_' . $userId);

// Run with error handling
try {
    $result = $agent->runSession('Begin test strategy development');

    if ($result->isSuccess()) {
        $logger->info('Agent session completed', [
            'progress' => $agent->getProgress(),
            'session' => $agent->getState()->getSessionNumber(),
        ]);
    } else {
        $logger->error('Agent session failed', [
            'error' => $result->getError(),
        ]);
    }
} catch (\Exception $e) {
    $logger->critical('Agent crashed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
```

---

## Chapter 6: Real-World Examples

### Example 1: Project Planner

```php
class ProjectPlannerAgent
{
    private AutonomousAgent $agent;
    private string $projectName;

    public function __construct(ClaudePhp $client, string $projectName)
    {
        $this->projectName = $projectName;
        $this->agent = new AutonomousAgent($client, [
            'goal' => "Create a complete project plan for: {$projectName}",
            'state_file' => "./projects/{$projectName}/plan_agent.json",
            'name' => 'project_planner',
        ]);
    }

    public function planPhase(string $phase): string
    {
        $result = $this->agent->runSession(
            "Plan the {$phase} phase of the project"
        );

        if ($result->isSuccess()) {
            $this->agent->getState()->getGoal()
                ->completeSubgoal("{$phase} phase planned");
        }

        return $result->getAnswer();
    }

    public function getProgress(): int
    {
        return $this->agent->getProgress();
    }

    public function isComplete(): bool
    {
        return $this->agent->isGoalComplete();
    }
}

// Usage
$planner = new ProjectPlannerAgent($client, 'Mobile App Development');

echo $planner->planPhase('Requirements');
echo $planner->planPhase('Design');
echo $planner->planPhase('Implementation');
echo $planner->planPhase('Testing');
echo $planner->planPhase('Deployment');

echo "Project plan {$planner->getProgress()}% complete\n";
```

### Example 2: Learning Assistant

```php
class LearningAssistant
{
    private AutonomousAgent $agent;

    public function __construct(ClaudePhp $client, string $topic)
    {
        $this->agent = new AutonomousAgent($client, [
            'goal' => "Master the topic: {$topic}",
            'state_file' => "./learning/" . md5($topic) . ".json",
            'name' => 'learning_assistant',
        ]);
    }

    public function nextLesson(): array
    {
        $result = $this->agent->runSession(
            'Teach me the next concept I need to learn'
        );

        return [
            'content' => $result->getAnswer(),
            'progress' => $this->agent->getProgress(),
            'mastered' => $this->agent->isGoalComplete(),
        ];
    }

    public function quiz(): array
    {
        $result = $this->agent->runSession(
            'Give me a quiz on what I\'ve learned so far'
        );

        return [
            'questions' => $result->getAnswer(),
            'topics_covered' => $this->agent->getState()
                ->getGoal()
                ->getCompletedSubgoals(),
        ];
    }
}

// Usage
$assistant = new LearningAssistant($client, 'PHP Design Patterns');

$lesson1 = $assistant->nextLesson();
echo $lesson1['content'];
echo "\nProgress: {$lesson1['progress']}%\n";

$quiz = $assistant->quiz();
echo "\nQuiz:\n{$quiz['questions']}\n";
echo "Topics covered: " . implode(', ', $quiz['topics_covered']) . "\n";
```

### Example 3: Content Creator

```php
class ContentCreator
{
    private AutonomousAgent $agent;
    private string $contentType;

    public function __construct(ClaudePhp $client, string $contentType, string $topic)
    {
        $this->contentType = $contentType;
        $this->agent = new AutonomousAgent($client, [
            'goal' => "Create a {$contentType} about: {$topic}",
            'state_file' => "./content/" . md5($topic) . ".json",
            'name' => 'content_creator',
        ]);
    }

    public function createOutline(): string
    {
        $result = $this->agent->runSession('Create a detailed outline');

        if ($result->isSuccess()) {
            $this->agent->getState()->getGoal()->completeSubgoal('Outline created');
        }

        return $result->getAnswer();
    }

    public function writeSection(string $section): string
    {
        $result = $this->agent->runSession("Write the section: {$section}");

        if ($result->isSuccess()) {
            $this->agent->getState()->getGoal()->completeSubgoal($section);
        }

        return $result->getAnswer();
    }

    public function finalize(): string
    {
        $result = $this->agent->runSession(
            'Finalize the content with introduction and conclusion'
        );

        if ($result->isSuccess()) {
            $this->agent->getState()->getGoal()->complete();
        }

        return $result->getAnswer();
    }

    public function getStatus(): array
    {
        return [
            'progress' => $this->agent->getProgress(),
            'completed_sections' => $this->agent->getState()
                ->getGoal()
                ->getCompletedSubgoals(),
            'is_complete' => $this->agent->isGoalComplete(),
        ];
    }
}

// Usage
$creator = new ContentCreator($client, 'blog post', 'PHP 8.4 Features');

$outline = $creator->createOutline();
echo "Outline:\n{$outline}\n\n";

$intro = $creator->writeSection('Introduction');
$features = $creator->writeSection('Key Features');
$examples = $creator->writeSection('Code Examples');

$final = $creator->finalize();

$status = $creator->getStatus();
echo "Status: {$status['progress']}% complete\n";
echo "Sections: " . implode(', ', $status['completed_sections']) . "\n";
```

---

## Chapter 7: Best Practices

### 1. Always Define Clear Goals

```php
// ✅ Good: Specific and measurable
$agent = new AutonomousAgent($client, [
    'goal' => 'Create a deployment checklist including: pre-deployment steps, deployment process, post-deployment verification, and rollback procedures',
]);

// ❌ Avoid: Vague
$agent = new AutonomousAgent($client, [
    'goal' => 'Do deployment stuff',
]);
```

### 2. Use State Files in Production

```php
// ✅ Good: Organized by user/project
$stateFile = sprintf(
    '/var/app/data/agents/%s/%s.json',
    $userId,
    md5($goalDescription)
);

// ❌ Avoid: Shared or temp location
$stateFile = '/tmp/agent.json';
```

### 3. Implement Error Handling

```php
$maxRetries = 3;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    $result = $agent->runSession($task);

    if ($result->isSuccess()) {
        break;
    }

    $retryCount++;
    $logger->warning("Session failed, retry {$retryCount}/{$maxRetries}");
    sleep(2 ** $retryCount);  // Exponential backoff
}

if (!$result->isSuccess()) {
    $logger->error('All retries failed', [
        'error' => $result->getError(),
    ]);
}
```

### 4. Monitor Progress

```php
$result = $agent->runSession($task);

if ($result->isSuccess()) {
    $progress = $result->getMetadata()['goal_progress'];

    // Send progress updates
    $eventDispatcher->dispatch(new AgentProgressEvent(
        agentName: $agent->getName(),
        progress: $progress,
        goalComplete: $agent->isGoalComplete(),
    ));
}
```

### 5. Clean Up Completed Agents

```php
if ($agent->isGoalComplete()) {
    // Archive the state
    $archiveFile = str_replace('.json', '_completed.json', $stateFile);
    rename($stateFile, $archiveFile);

    // Or delete if not needed
    // unlink($stateFile);
}
```

---

## Chapter 8: Troubleshooting

### Issue 1: Agent Not Making Progress

**Symptom**: Progress stays at the same percentage

**Solution**:

```php
$previousProgress = 0;
$stallCount = 0;

for ($i = 0; $i < 5; $i++) {
    $result = $agent->runSession('Continue working on the goal');
    $currentProgress = $agent->getProgress();

    if ($currentProgress === $previousProgress) {
        $stallCount++;

        if ($stallCount >= 2) {
            // Try a more specific prompt
            $result = $agent->runSession(
                'Focus on completing the next specific task toward the goal'
            );
        }
    } else {
        $stallCount = 0;
    }

    $previousProgress = $currentProgress;
}
```

### Issue 2: State File Corruption

**Symptom**: JSON decode errors

**Solution**:

```php
function loadAgentSafely($client, $goal, $stateFile)
{
    if (file_exists($stateFile)) {
        $contents = file_get_contents($stateFile);
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Backup corrupted file
            $backup = $stateFile . '.corrupted.' . time();
            rename($stateFile, $backup);

            echo "State file corrupted, backed up to: {$backup}\n";
            echo "Starting with fresh state\n";
        }
    }

    return new AutonomousAgent($client, [
        'goal' => $goal,
        'state_file' => $stateFile,
    ]);
}
```

### Issue 3: Goal Never Completes

**Symptom**: Progress reaches 90-95% but never 100%

**Solution**:

```php
// Be explicit about completion
if ($agent->getProgress() >= 90 && !$agent->isGoalComplete()) {
    $result = $agent->runSession(
        'Review all work done and confirm the goal is complete. ' .
        'If complete, state clearly that the goal is finished.'
    );
}
```

---

## Conclusion

You've now learned how to:

✅ Create autonomous agents with clear goals  
✅ Manage state persistence across sessions  
✅ Track progress and subgoals  
✅ Run multi-session workflows  
✅ Build production-ready agent systems  
✅ Implement best practices  
✅ Troubleshoot common issues

### Next Steps

1. **Experiment**: Try different goals and observe behavior
2. **Build**: Create your own agent-based application
3. **Explore**: Check out other agent types (AlertAgent, MakerAgent, etc.)
4. **Contribute**: Share your patterns and examples

### Resources

- [AutonomousAgent Documentation](../AutonomousAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Examples](../../examples/)
- [GitHub Repository](https://github.com/your-repo/claude-php-agent)

### Getting Help

- Check the [documentation](../AutonomousAgent.md)
- Review the [examples](../../examples/)
- Open an issue on GitHub
- Join the community discussions

---

Happy building with AutonomousAgent! 🚀
