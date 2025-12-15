# State Management System

Comprehensive state persistence system for autonomous agents with goal tracking, history management, and atomic operations.

## Quick Start

```php
use ClaudeAgents\State\{AgentState, Goal, GoalStatus, StateConfig, StateManager};

// Create a goal
$goal = new Goal('Build amazing features', GoalStatus::IN_PROGRESS);

// Create state with config
$state = new AgentState(
    sessionNumber: 1,
    goal: $goal,
    config: StateConfig::production()
);

// Add conversation
$state->addMessage(['role' => 'user', 'content' => 'Hello']);

// Save with automatic backup
$manager = new StateManager('agent_state.json');
$manager->save($state, createBackup: true);

// Load later
$loadedState = $manager->load();
```

## Components

### 1. Goal (`Goal.php`)
Tracks goal progress with status management.

**Status Values** (via `GoalStatus` enum):
- `NOT_STARTED` - Goal not yet begun
- `IN_PROGRESS` - Actively working
- `COMPLETED` - Successfully finished
- `PAUSED` - Temporarily stopped
- `CANCELLED` - Abandoned
- `FAILED` - Did not succeed

**Key Methods**:
```php
$goal->start();                    // Begin goal
$goal->setProgressPercentage(50);  // Update progress
$goal->completeSubgoal('Phase 1'); // Mark subgoal
$goal->pause();                    // Pause execution
$goal->complete();                 // Mark as done
```

### 2. AgentState (`AgentState.php`)
Complete agent state with conversation and action history.

**Key Methods**:
```php
// Conversation
$state->addMessage(['role' => 'user', 'content' => 'Hi']);
$state->getRecentMessages(10);
$state->clearConversationHistory();

// Actions
$state->recordAction(['tool' => 'calculator', 'result' => 42]);
$state->getRecentActions(10);
$state->clearActionHistory();

// Sessions
$state->incrementSession();
$state->getSessionNumber();
$state->getSessionDuration();

// Statistics
$stats = $state->getStatistics();
```

### 3. StateConfig (`StateConfig.php`)
Configuration for state management behavior.

**Presets**:
```php
StateConfig::default();     // Standard: 1000 history, atomic writes
StateConfig::production();  // Optimized: 500 history, compression, 10 backups
StateConfig::unlimited();   // No history limits
```

**Custom Config**:
```php
new StateConfig(
    maxConversationHistory: 500,
    maxActionHistory: 500,
    compressHistory: true,
    atomicWrites: true,
    backupRetention: 10,
    version: 1
);
```

### 4. StateManager (`StateManager.php`)
Handles state persistence with atomic writes and backups.

**Basic Operations**:
```php
$manager = new StateManager('state.json');

// Save/Load
$manager->save($state);
$loadedState = $manager->load();

// Check existence
if ($manager->exists()) {
    // State file found
}

// Delete
$manager->delete();
```

**Backup & Restore**:
```php
// Create backup
$manager->createBackup();

// List all backups
$backups = $manager->listBackups();

// Restore from latest
$state = $manager->restoreLatest();

// Restore from specific backup
$state = $manager->restore($backupFile);

// Clean up old backups
$deleted = $manager->deleteAllBackups();
```

### 5. GoalStatus (`GoalStatus.php`)
Type-safe enumeration of goal statuses.

**Usage**:
```php
// Create goal with enum
$goal = new Goal('Task', GoalStatus::IN_PROGRESS);

// Check status
if ($goal->getStatusEnum() === GoalStatus::COMPLETED) {
    // Goal is complete
}

// Validate strings
GoalStatus::isValid('in_progress');  // true
GoalStatus::isValid('invalid');      // false

// Safe conversion
$status = GoalStatus::fromString('completed'); // Returns enum
```

## Features

### ✅ Type Safety
- Enum-based status values
- Strict type declarations
- Input validation

### ✅ Data Integrity
- Atomic file writes (temp + rename)
- Automatic history limits
- Input validation
- Immutable after creation

### ✅ Persistence
- JSON serialization
- Automatic backups
- Configurable retention
- State versioning

### ✅ History Management
- Recent message/action retrieval
- Configurable limits
- Clear operations
- Automatic truncation

### ✅ Time Tracking
- Lifetime duration
- Session duration
- Idle time
- Timestamps on all events

### ✅ Statistics
- Comprehensive state stats
- Size tracking
- Progress monitoring

## Advanced Usage

### Custom Configuration
```php
$config = new StateConfig(
    maxConversationHistory: 100,  // Keep last 100 messages
    maxActionHistory: 50,         // Keep last 50 actions
    atomicWrites: true,           // Prevent corruption
    backupRetention: 5            // Keep 5 backups
);

$state = new AgentState(
    sessionNumber: 1,
    goal: $goal,
    config: $config
);
```

### Serialization
```php
// To JSON
$json = $state->toJson();

// From JSON
$newState = AgentState::createFromJson($json);

// To Array
$array = $state->toArray();

// From Array
$newState = AgentState::createFromArray($array);
```

### Goal Management
```php
$goal = new Goal('Complete project');

// Track progress
$goal->start();
$goal->setProgressPercentage(25);
$goal->completeSubgoal('Phase 1');
$goal->setProgressPercentage(50);
$goal->completeSubgoal('Phase 2');

// Handle interruptions
if ($needsPause) {
    $goal->pause();
}

// Resume
$goal->start();

// Finish
$goal->complete();

// Check status
if ($goal->isComplete()) {
    echo "Goal achieved!";
}
```

### Recovery from Backups
```php
$manager = new StateManager('state.json');

try {
    $state = $manager->load();
} catch (\Exception $e) {
    // Restore from backup on corruption
    $state = $manager->restoreLatest();
    
    if ($state === null) {
        // No backups available, start fresh
        $state = new AgentState(
            sessionNumber: 1,
            goal: new Goal('Recovery')
        );
    }
}
```

## Validation Rules

### Goal
- Description cannot be empty or whitespace
- Status must be valid GoalStatus value
- Progress percentage clamped to 0-100
- Subgoals must be non-empty strings

### AgentState
- Session number must be >= 1
- Timestamps must be non-negative
- Updated timestamp >= created timestamp
- History respects configured limits

### StateConfig
- All numeric values must be non-negative
- Version must be >= 1
- Limits of 0 mean unlimited

## Error Handling

All classes throw appropriate exceptions:

- `InvalidArgumentException` - Invalid input parameters
- `BadMethodCallException` - Immutability violations
- `RuntimeException` - I/O or serialization failures

Example:
```php
try {
    $goal = new Goal('');  // Empty description
} catch (\InvalidArgumentException $e) {
    // Handle: "Goal description cannot be empty"
}
```

## Testing

Comprehensive test suite with 97 tests and 253 assertions:

```bash
./vendor/bin/phpunit tests/Unit/State
```

**Coverage**:
- AgentState: 28 tests
- Goal: 30 tests
- GoalStatus: 6 tests
- StateConfig: 9 tests
- StateManager: 24 tests

All tests passing ✅

## Files

- `AgentState.php` - Main state container
- `Goal.php` - Goal tracking
- `GoalStatus.php` - Status enumeration
- `StateConfig.php` - Configuration
- `StateManager.php` - Persistence layer

## Version

Current version: **1.0**

Schema versioning supported for future migrations.

