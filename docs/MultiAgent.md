# Multi-Agent Collaboration System

The Multi-Agent system enables multiple AI agents to work together, communicate, and coordinate to solve complex tasks. Inspired by AutoGen and other multi-agent frameworks, it provides message passing, protocols, shared memory, and collaboration orchestration.

## Table of Contents

- [Overview](#overview)
- [Core Components](#core-components)
- [Quick Start](#quick-start)
- [Agent Communication](#agent-communication)
- [Protocols](#protocols)
- [Shared Memory](#shared-memory)
- [Collaboration Patterns](#collaboration-patterns)
- [Advanced Usage](#advanced-usage)

## Overview

The multi-agent system allows you to:

- **Create collaborative agents** that can send and receive messages
- **Orchestrate multi-agent workflows** with automatic task routing
- **Define communication protocols** for structured interactions
- **Share state** between agents via blackboard/shared memory
- **Track and monitor** agent interactions and performance

### Key Features

✅ True message passing between agents  
✅ Protocol validation (request-response, broadcast, contract-net, auction)  
✅ Shared memory/blackboard for coordination  
✅ Automatic agent selection based on capabilities  
✅ Metrics and observability  
✅ Support for custom collaborative agents  

## Core Components

### 1. CollaborationManager

Orchestrates multi-agent collaborations.

```php
use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp($apiKey);
$manager = new CollaborationManager($client, [
    'max_rounds' => 10,
    'enable_message_passing' => true,
    'protocol' => Protocol::requestResponse(),
]);
```

### 2. CollaborativeAgent

Base class for agents that can communicate.

```php
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;

$agent = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research', 'analysis'],
    options: [
        'name' => 'Research Agent',
        'system_prompt' => 'You are a research specialist...',
    ]
);
```

### 3. Message

Represents communication between agents.

```php
use ClaudeAgents\MultiAgent\Message;

$message = new Message(
    from: 'agent1',
    to: 'agent2',
    content: 'Can you analyze this data?',
    type: 'request',
    metadata: ['priority' => 'high']
);
```

### 4. Protocol

Defines communication rules.

```php
use ClaudeAgents\MultiAgent\Protocol;

$protocol = Protocol::requestResponse(); // Request-response pattern
$protocol = Protocol::broadcast();        // Broadcast messages
$protocol = Protocol::contractNet();      // Contract negotiation
$protocol = Protocol::auction();          // Auction-based
```

### 5. SharedMemory

Shared state for agent coordination.

```php
$memory = $manager->getSharedMemory();

// Write data
$memory->write('results', $data, 'agent1');

// Read data
$value = $memory->read('results', 'agent2');

// Atomic operations
$memory->increment('counter', 'agent3');
$memory->append('list', $item, 'agent4');
```

## Quick Start

### Basic Collaboration

```php
<?php

use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);

// Create collaboration manager
$manager = new CollaborationManager($client, [
    'max_rounds' => 5,
]);

// Create specialized agents
$researcher = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research', 'information gathering'],
    options: [
        'system_prompt' => 'You are a research specialist who gathers factual information.',
    ]
);

$analyst = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'analyst',
    capabilities: ['analysis', 'evaluation'],
    options: [
        'system_prompt' => 'You are an analyst who evaluates and synthesizes information.',
    ]
);

// Register agents
$manager->registerAgent('researcher', $researcher, ['research', 'information gathering']);
$manager->registerAgent('analyst', $analyst, ['analysis', 'evaluation']);

// Run collaboration
$result = $manager->collaborate('Research and analyze the benefits of microservices architecture');

echo "Result: {$result->getAnswer()}\n";
echo "Rounds: {$result->getIterations()}\n";
echo "Agents involved: " . implode(', ', $result->getMetadata()['agents_involved']) . "\n";
```

## Agent Communication

### Sending Messages

Collaborative agents can send messages to each other:

```php
// Direct message
$message = new Message(
    from: 'agent1',
    to: 'agent2',
    content: 'Please process this data',
    type: 'request'
);
$agent1->sendMessage($message);

// Broadcast to all agents
$agent1->broadcast('Task complete, results available in shared memory');
```

### Receiving Messages

Agents automatically receive messages via the `receiveMessage()` method:

```php
class MyAgent extends CollaborativeAgent
{
    protected function processMessage(Message $message): void
    {
        // Handle the message
        $content = $message->getContent();
        $from = $message->getFrom();
        
        // Process and reply
        if ($message->getType() === 'request') {
            $response = $this->processRequest($content);
            $this->reply($message, $response);
        }
    }
}
```

### Auto-Reply

`SimpleCollaborativeAgent` supports auto-reply for requests:

```php
$agent = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'helper',
    capabilities: ['assistance'],
    options: ['auto_reply' => true] // Automatically replies to requests
);
```

## Protocols

Protocols define rules for agent communication.

### Request-Response

Traditional request-response pattern:

```php
$protocol = Protocol::requestResponse();
$manager = new CollaborationManager($client, [
    'protocol' => $protocol,
]);

// Only 'request' and 'response' message types are allowed
```

### Broadcast

All messages must be broadcast:

```php
$protocol = Protocol::broadcast();

$message = new Message('agent1', 'broadcast', 'Announcement');
$manager->sendMessage($message); // Delivered to all agents
```

### Contract Net

Contract negotiation protocol (CFP → Proposals → Award/Reject):

```php
$protocol = Protocol::contractNet();

// Manager sends Call For Proposals
$cfp = new Message('manager', 'broadcast', 'Need data analysis', 'cfp');

// Agents send proposals
$proposal = new Message('agent1', 'manager', 'I can do it for 100 tokens', 'proposal');

// Manager awards contract
$award = new Message('manager', 'agent1', 'You got it', 'award');
```

### Auction

Auction-based task allocation:

```php
$protocol = Protocol::auction();

// Agents bid on tasks
$bid = new Message('agent1', 'auctioneer', 'Bid: $100', 'bid');

// Auctioneer accepts/rejects
$accept = new Message('auctioneer', 'agent1', 'Accepted', 'accept');
```

## Shared Memory

Agents can coordinate via shared memory (blackboard pattern).

### Basic Operations

```php
$memory = $manager->getSharedMemory();

// Write data
$memory->write('task_status', 'in_progress', 'agent1');

// Read data
$status = $memory->read('task_status', 'agent2');

// Check existence
if ($memory->has('task_status')) {
    // ...
}

// Delete
$memory->delete('task_status', 'agent1');
```

### Array Operations

```php
// Append to list
$memory->append('completed_tasks', 'task1', 'agent1');
$memory->append('completed_tasks', 'task2', 'agent2');

$tasks = $memory->read('completed_tasks', 'agent3');
// ['task1', 'task2']
```

### Atomic Operations

```php
// Increment counter
$memory->write('task_count', 0, 'system');
$newCount = $memory->increment('task_count', 'agent1'); // Returns 1

// Compare-and-swap (prevents race conditions)
$success = $memory->compareAndSwap(
    key: 'lock',
    expected: 'unlocked',
    new: 'locked',
    agentId: 'agent1'
);
```

### Metadata and Versioning

Every write includes metadata:

```php
$memory->write('data', 'value', 'agent1', ['source' => 'API']);

$metadata = $memory->getMetadata('data');
// [
//     'written_by' => 'agent1',
//     'written_at' => 1234567890.123,
//     'version' => 1,
//     'metadata' => ['source' => 'API']
// ]
```

### Statistics

```php
$stats = $memory->getStatistics();
// [
//     'total_keys' => 5,
//     'total_operations' => 23,
//     'reads' => 10,
//     'writes' => 12,
//     'deletes' => 1,
//     'unique_agents' => 3
// ]
```

## Collaboration Patterns

### Master-Worker Pattern

One coordinator delegates to specialized workers:

```php
$coordinator = new SimpleCollaborativeAgent($client, 'coordinator', ['coordination']);
$worker1 = new SimpleCollaborativeAgent($client, 'worker1', ['task_a']);
$worker2 = new SimpleCollaborativeAgent($client, 'worker2', ['task_b']);

$manager->registerAgent('coordinator', $coordinator, ['coordination']);
$manager->registerAgent('worker1', $worker1, ['task_a']);
$manager->registerAgent('worker2', $worker2, ['task_b']);

$result = $manager->collaborate('Complex task requiring multiple specialists');
```

### Peer-to-Peer Collaboration

Agents communicate directly:

```php
$memory = $manager->getSharedMemory();

// Agent1 posts initial research
$agent1->run('Research topic X');
$memory->write('research', $results, 'agent1');

// Agent2 analyzes the research
$research = $memory->read('research', 'agent2');
$analysis = $agent2->run("Analyze: {$research}");
$memory->write('analysis', $analysis, 'agent2');

// Agent3 creates final report
$analysis = $memory->read('analysis', 'agent3');
$report = $agent3->run("Create report from: {$analysis}");
```

### Debate/Consensus

Multiple agents discuss and reach consensus:

```php
$manager = new CollaborationManager($client, [
    'protocol' => Protocol::broadcast(),
    'max_rounds' => 10,
]);

$pro = new SimpleCollaborativeAgent($client, 'pro', ['advocacy']);
$con = new SimpleCollaborativeAgent($client, 'con', ['criticism']);
$moderator = new SimpleCollaborativeAgent($client, 'moderator', ['moderation']);

$manager->registerAgent('pro', $pro, ['advocacy']);
$manager->registerAgent('con', $con, ['criticism']);
$manager->registerAgent('moderator', $moderator, ['moderation']);

$result = $manager->collaborate('Should we adopt microservices?');
```

## Advanced Usage

### Custom Collaborative Agents

Extend `CollaborativeAgent` for custom behavior:

```php
use ClaudeAgents\MultiAgent\CollaborativeAgent;
use ClaudeAgents\AgentResult;

class CustomAgent extends CollaborativeAgent
{
    public function run(string $task): AgentResult
    {
        // Custom task processing
        $context = $this->buildMessageContext();
        
        // Access shared memory
        $sharedData = $this->manager->getSharedMemory()->read('data', $this->agentId);
        
        // Process with Claude
        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => 'Custom system prompt',
            'messages' => [['role' => 'user', 'content' => $task]],
        ]);
        
        return AgentResult::success(
            answer: $this->extractTextContent($response->content ?? []),
            messages: [],
            iterations: 1
        );
    }
    
    public function getName(): string
    {
        return 'CustomAgent';
    }
    
    protected function processMessage(Message $message): void
    {
        // Custom message handling
        parent::processMessage($message);
        
        if ($message->getType() === 'custom_type') {
            $this->handleCustomMessage($message);
        }
    }
    
    private function handleCustomMessage(Message $message): void
    {
        // Process custom message type
    }
}
```

### Monitoring and Metrics

```php
// Get collaboration metrics
$metrics = $manager->getMetrics();

echo "Agents: {$metrics['agents_registered']}\n";
echo "Messages routed: {$metrics['messages_routed']}\n";
echo "Queue size: {$metrics['messages_in_queue']}\n";

// Shared memory statistics
$memStats = $metrics['shared_memory_stats'];
echo "Memory keys: {$memStats['total_keys']}\n";
echo "Unique agents: {$memStats['unique_agents']}\n";

// Performance metrics
$perf = $metrics['performance'];
echo "Success rate: {$perf['success_rate']}\n";
```

### Export/Import State

```php
// Export shared memory state
$state = $memory->export();
file_put_contents('memory_state.json', json_encode($state));

// Import state in another session
$state = json_decode(file_get_contents('memory_state.json'), true);
$memory->import($state);
```

### Access Logs

```php
// Enable access tracking
$memory = new SharedMemory(['track_access' => true]);

// View access log
$log = $memory->getAccessLog();
foreach ($log as $entry) {
    echo "{$entry['agent_id']} {$entry['operation']} {$entry['key']} at {$entry['timestamp']}\n";
}
```

## Best Practices

1. **Define Clear Capabilities**: Give agents specific, non-overlapping capabilities for better task routing
2. **Use Protocols**: Enforce communication patterns with protocols to maintain order
3. **Leverage Shared Memory**: Use shared memory for complex coordination instead of message chains
4. **Monitor Metrics**: Track agent performance and message volume
5. **Set Reasonable Limits**: Use `max_rounds` to prevent infinite loops
6. **Handle Errors Gracefully**: Check `AgentResult::isSuccess()` and handle failures
7. **Clear State**: Clear inbox/outbox and shared memory between sessions if needed

## See Also

- [CollaborativeInterface](./contracts.md#collaborativeinterface)
- [CoordinatorAgent](./CoordinatorAgent.md)
- [HierarchicalAgent](./HierarchicalAgent.md)
- [Tutorial: Multi-Agent Collaboration](./tutorials/MultiAgent_Tutorial.md)

