# AutonomousAgent Documentation

## Overview

The `AutonomousAgent` is a goal-oriented agent that can operate across multiple sessions while maintaining persistent state. It tracks progress toward a defined goal, manages conversation history, and persists its state to disk for recovery and resumption.

## Key Features

- **Goal-Oriented Execution**: Define clear goals and track progress
- **State Persistence**: Save and restore agent state across sessions
- **Multi-Session Support**: Continue working toward goals across multiple runs
- **Progress Tracking**: Monitor percentage completion and subgoals
- **Action History**: Complete log of all actions taken
- **Safety Limits**: Configurable maximum actions per session
- **Flexible State Management**: Custom state file locations

## Architecture

```
┌─────────────────────────────────────┐
│      AutonomousAgent                │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - AgentState                        │
│ - StateManager                      │
│ - Goal tracking                     │
└──────────┬──────────────────────────┘
           │
           ├─── AgentState
           │    ├─── Goal
           │    ├─── Conversation History
           │    ├─── Action History
           │    └─── Metadata
           │
           └─── StateManager
                ├─── Save to JSON
                ├─── Load from JSON
                └─── Delete state
```

## Classes

### AutonomousAgent

The main agent class that orchestrates goal-oriented execution.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

### AgentState

Represents the complete state of an autonomous agent.

**Namespace**: `ClaudeAgents\State`

**Properties**:
- `sessionNumber` - Current session number
- `goal` - The agent's goal
- `conversationHistory` - All conversation messages
- `actionHistory` - All actions taken
- `metadata` - Custom metadata
- `createdAt` - Creation timestamp
- `updatedAt` - Last update timestamp

### Goal

Represents a goal with progress tracking.

**Namespace**: `ClaudeAgents\State`

**Properties**:
- `description` - Goal description
- `status` - Current status (not_started, in_progress, completed)
- `progressPercentage` - Completion percentage (0-100)
- `completedSubgoals` - List of completed subgoals
- `metadata` - Additional metadata

### StateManager

Manages persistence of agent state to disk.

**Namespace**: `ClaudeAgents\State`

**Methods**:
- `load()` - Load state from file
- `save(AgentState)` - Save state to file
- `delete()` - Delete state file
- `exists()` - Check if state file exists

## Usage

### Basic Usage

```php
use ClaudeAgents\Agents\AutonomousAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new AutonomousAgent($client, [
    'goal' => 'Plan and design a microservices architecture',
    'state_file' => './agent_state.json',
]);

// Run a single session
$result = $agent->runSession('Start by identifying the key components');

if ($result->isSuccess()) {
    echo "Progress: {$agent->getProgress()}%\n";
    echo "Response: {$result->getAnswer()}\n";
}
```

### Configuration Options

```php
$agent = new AutonomousAgent($client, [
    // Required
    'goal' => 'Description of the goal to achieve',
    
    // Optional
    'state_file' => './agent_state.json',  // Path to state file
    'name' => 'my_agent',                   // Agent name
    'max_actions_per_session' => 50,       // Safety limit
    'logger' => $psrLogger,                 // PSR-3 logger
]);
```

### Multi-Session Execution

```php
// Session 1
$result1 = $agent->runSession('Start planning');
echo "Session 1 Progress: {$agent->getProgress()}%\n";

// Session 2
$agent->getState()->incrementSession();
$result2 = $agent->runSession('Continue with detailed design');
echo "Session 2 Progress: {$agent->getProgress()}%\n";

// Run until complete
$results = $agent->runUntilComplete(maxSessions: 10);
echo "Completed in " . count($results) . " sessions\n";
```

### State Management

```php
// Get current state
$state = $agent->getState();

echo "Session: {$state->getSessionNumber()}\n";
echo "Messages: " . count($state->getConversationHistory()) . "\n";
echo "Actions: " . count($state->getActionHistory()) . "\n";

// Access goal information
$goal = $state->getGoal();
echo "Goal: {$goal->getDescription()}\n";
echo "Status: {$goal->getStatus()}\n";
echo "Progress: {$goal->getProgressPercentage()}%\n";
echo "Subgoals: " . implode(', ', $goal->getCompletedSubgoals()) . "\n";

// Add metadata
$state->setMetadataValue('environment', 'production');
$state->setMetadataValue('priority', 'high');
```

### State Persistence

```php
// State is automatically saved after each session
$result = $agent->runSession('Do some work');

// Load existing state in a new agent instance
$newAgent = new AutonomousAgent($client, [
    'goal' => 'Same goal description',
    'state_file' => './agent_state.json',  // Same file
]);

// newAgent will continue from where the previous agent left off
echo "Loaded progress: {$newAgent->getProgress()}%\n";
```

### Goal Management

```php
// Check goal completion
if ($agent->isGoalComplete()) {
    echo "Goal achieved!\n";
} else {
    echo "Current progress: {$agent->getProgress()}%\n";
}

// Get goal description
$goalDescription = $agent->getGoal();

// Access goal details
$goal = $agent->getState()->getGoal();

// Track subgoals
$goal->completeSubgoal('Design database schema');
$goal->completeSubgoal('Implement API endpoints');
$goal->completeSubgoal('Write tests');

echo "Completed: " . implode(', ', $goal->getCompletedSubgoals()) . "\n";
```

### Resetting State

```php
// Reset agent to start over
$agent->reset();

// This will:
// - Delete the state file
// - Create new state with session 1
// - Reset progress to 0
// - Keep the same goal description
```

## Agent Result

The `runSession()` method returns an `AgentResult` object with:

```php
$result = $agent->runSession('task');

// Standard properties
$result->isSuccess();      // bool
$result->getAnswer();      // string
$result->getError();       // string (if failed)
$result->getIterations();  // int

// Metadata
$metadata = $result->getMetadata();
$metadata['session_number'];        // Current session
$metadata['goal_progress'];         // Progress percentage
$metadata['actions_this_session'];  // Actions in this session
$metadata['goal_complete'];         // Is goal complete
$metadata['already_complete'];      // Was already complete
```

## State File Format

The state is saved as JSON:

```json
{
    "session_number": 3,
    "goal": {
        "description": "Plan a microservices architecture",
        "status": "in_progress",
        "progress_percentage": 45,
        "completed_subgoals": [
            "Identify services",
            "Design API gateway"
        ],
        "metadata": {}
    },
    "conversation_history": [
        {
            "role": "user",
            "content": "Start planning",
            "timestamp": 1234567890
        },
        {
            "role": "assistant",
            "content": "I'll begin by...",
            "timestamp": 1234567891
        }
    ],
    "action_history": [
        {
            "action": "Initial planning complete",
            "session": 1,
            "timestamp": 1234567891
        }
    ],
    "metadata": {
        "environment": "production"
    },
    "created_at": 1234567880,
    "updated_at": 1234567891
}
```

## Goal Completion Detection

The agent automatically detects goal completion when the response contains indicators:

- "completed"
- "finished"
- "done"
- "achieved"
- "accomplished"

When detected, the goal status is set to "completed" and progress to 100%.

## Best Practices

### 1. Define Clear Goals

```php
// ✅ Good - Specific and measurable
'goal' => 'Create a deployment plan including: infrastructure setup, CI/CD pipeline, monitoring, and rollback strategy'

// ❌ Avoid - Vague and open-ended
'goal' => 'Do some planning'
```

### 2. Use Appropriate Session Limits

```php
// For complex goals
'max_actions_per_session' => 100

// For simple goals
'max_actions_per_session' => 20
```

### 3. Monitor Progress

```php
$result = $agent->runSession($task);

if ($result->isSuccess()) {
    $progress = $result->getMetadata()['goal_progress'];
    
    if ($progress < 30) {
        echo "Still in early stages\n";
    } elseif ($progress < 70) {
        echo "Making good progress\n";
    } else {
        echo "Nearly complete\n";
    }
}
```

### 4. Handle State File Location

```php
// Production: use persistent storage
'state_file' => '/var/app/data/agent_states/agent_' . $userId . '.json'

// Development: use temp directory
'state_file' => sys_get_temp_dir() . '/agent_state.json'

// Testing: use unique files
'state_file' => sys_get_temp_dir() . '/test_agent_' . uniqid() . '.json'
```

### 5. Use Logging in Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('autonomous_agent');
$logger->pushHandler(new StreamHandler('/var/log/agent.log', Logger::INFO));

$agent = new AutonomousAgent($client, [
    'goal' => $goal,
    'logger' => $logger,
]);
```

### 6. Implement Error Handling

```php
$result = $agent->runSession($task);

if (!$result->isSuccess()) {
    $logger->error('Agent session failed', [
        'error' => $result->getError(),
        'session' => $agent->getState()->getSessionNumber(),
        'progress' => $agent->getProgress(),
    ]);
    
    // Decide whether to retry or abort
    if ($agent->getProgress() > 50) {
        // Retry if significant progress made
        $retryResult = $agent->runSession('Continue despite the error');
    } else {
        // Reset if early failure
        $agent->reset();
    }
}
```

## Advanced Patterns

### Pattern 1: Checkpointing

```php
$checkpoints = [25, 50, 75];

$result = $agent->runSession($task);
$progress = $result->getMetadata()['goal_progress'];

if (in_array($progress, $checkpoints)) {
    // Backup state at checkpoint
    $backupFile = $stateFile . ".checkpoint_{$progress}";
    copy($stateFile, $backupFile);
    echo "Checkpoint saved at {$progress}%\n";
}
```

### Pattern 2: Time-Limited Execution

```php
$startTime = time();
$maxDuration = 300; // 5 minutes

while (!$agent->isGoalComplete() && (time() - $startTime) < $maxDuration) {
    $result = $agent->runSession('Continue working on goal');
    
    if (!$result->isSuccess()) {
        break;
    }
    
    $agent->getState()->incrementSession();
}

if ($agent->isGoalComplete()) {
    echo "Goal completed!\n";
} else {
    echo "Paused at {$agent->getProgress()}% - can resume later\n";
}
```

### Pattern 3: Multi-Agent Collaboration

```php
// Planner agent
$planner = new AutonomousAgent($client, [
    'goal' => 'Create high-level architecture plan',
    'state_file' => './planner_state.json',
]);

$planResult = $planner->runSession('Create architecture plan');

// Implementation agent uses planner's output
$implementer = new AutonomousAgent($client, [
    'goal' => 'Implement the architecture: ' . $planResult->getAnswer(),
    'state_file' => './implementer_state.json',
]);

$implResult = $implementer->runSession('Begin implementation');
```

## Testing

### Unit Tests

The AutonomousAgent has comprehensive unit tests:

```bash
./vendor/bin/phpunit tests/Unit/Agents/AutonomousAgentTest.php
```

### Integration Tests

```bash
# Test with state persistence
php examples/autonomous_agent_local_test.php

# Test with API (requires ANTHROPIC_API_KEY)
php examples/autonomous_example.php
```

## Troubleshooting

### State File Corruption

```php
// Validate state file before loading
if (file_exists($stateFile)) {
    $contents = file_get_contents($stateFile);
    $data = json_decode($contents, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Backup corrupted file
        rename($stateFile, $stateFile . '.corrupted');
        echo "State file corrupted, starting fresh\n";
    }
}
```

### Progress Not Updating

```php
// Ensure state is being saved
$result = $agent->runSession($task);

if ($result->isSuccess()) {
    // Force save if needed
    $stateManager = new StateManager($stateFile);
    $stateManager->save($agent->getState());
}
```

### Goal Never Completes

```php
// Check progress stalling
$previousProgress = 0;
$stallCount = 0;

for ($i = 0; $i < 10; $i++) {
    $result = $agent->runSession('Continue');
    $currentProgress = $agent->getProgress();
    
    if ($currentProgress === $previousProgress) {
        $stallCount++;
        if ($stallCount >= 3) {
            echo "Progress stalled, may need manual intervention\n";
            break;
        }
    } else {
        $stallCount = 0;
    }
    
    $previousProgress = $currentProgress;
}
```

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

**Options:**
- `goal` (required): Goal description
- `state_file` (optional): Path to state file
- `name` (optional): Agent name
- `max_actions_per_session` (optional): Safety limit
- `logger` (optional): PSR-3 logger

### Methods

#### runSession(string $task = ''): AgentResult

Run a single session.

#### run(string $task): AgentResult

Alias for `runSession()`. Implements `AgentInterface`.

#### runUntilComplete(int $maxSessions = 10): array

Run multiple sessions until goal is complete or limit reached.

Returns array of `AgentResult` objects.

#### getState(): AgentState

Get the current agent state.

#### getProgress(): int

Get progress toward goal (0-100).

#### isGoalComplete(): bool

Check if goal is complete.

#### getGoal(): string

Get the goal description.

#### getName(): string

Get the agent name.

#### reset(): void

Reset the agent state.

## Examples

See the `/examples` directory for complete working examples:

- `autonomous_example.php` - Basic autonomous agent
- `advanced_autonomous_agent.php` - Advanced features and patterns
- `autonomous_agent_local_test.php` - Local testing without API

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [AlertAgent Documentation](AlertAgent.md)
- [Examples README](../examples/README.md)

## License

MIT License - See [LICENSE](../LICENSE) file for details.

