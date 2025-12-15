# Multi-Agent Collaboration Tutorial

Learn how to build multi-agent systems where AI agents work together to solve complex tasks.

## Prerequisites

- Basic understanding of agents and the ReAct pattern
- Claude PHP SDK installed
- API key configured

## Tutorial Overview

In this tutorial, you'll learn:

1. Basic agent communication
2. Collaborative problem solving
3. Shared memory coordination
4. Protocol-based interactions
5. Building a complete multi-agent system

## Part 1: Basic Agent Communication

Let's start with two agents that can send messages to each other:

```php
<?php

require_once 'vendor/autoload.php';

use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudeAgents\MultiAgent\Message;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);

// Create two agents
$alice = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'alice',
    capabilities: ['greeting'],
    options: [
        'name' => 'Alice',
        'system_prompt' => 'You are Alice, a friendly agent who likes to greet others.',
    ]
);

$bob = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'bob',
    capabilities: ['responding'],
    options: [
        'name' => 'Bob',
        'system_prompt' => 'You are Bob, a polite agent who responds to greetings.',
    ]
);

// Create collaboration manager
$manager = new CollaborationManager($client);
$manager->registerAgent('alice', $alice);
$manager->registerAgent('bob', $bob);

// Alice sends a message to Bob
$message = new Message(
    from: 'alice',
    to: 'bob',
    content: 'Hello Bob! How are you today?',
    type: 'request'
);

$manager->sendMessage($message);

// Check Bob's inbox
$bobInbox = $bob->getInbox();
echo "Bob received: {$bobInbox[0]->getContent()}\n";
```

**Output:**
```
Bob received: Hello Bob! How are you today?
```

## Part 2: Automatic Collaboration

Let the manager automatically route tasks to the right agents:

```php
<?php

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);
$manager = new CollaborationManager($client, ['max_rounds' => 5]);

// Create specialized agents
$researcher = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research', 'data_gathering'],
    options: [
        'system_prompt' => 'You are a research specialist. Gather factual information on topics.',
    ]
);

$writer = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'writer',
    capabilities: ['writing', 'documentation'],
    options: [
        'system_prompt' => 'You are a technical writer. Create clear, concise documentation.',
    ]
);

// Register agents with their capabilities
$manager->registerAgent('researcher', $researcher, ['research', 'data_gathering']);
$manager->registerAgent('writer', $writer, ['writing', 'documentation']);

// The manager will automatically select agents based on the task
$result = $manager->collaborate('Research PHP 8.3 features and write a summary');

echo "Final result:\n{$result->getAnswer()}\n\n";
echo "Collaboration stats:\n";
echo "- Rounds: {$result->getIterations()}\n";
echo "- Agents used: " . implode(', ', $result->getMetadata()['agents_involved']) . "\n";
```

## Part 3: Shared Memory Coordination

Use shared memory for agents to coordinate:

```php
<?php

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);
$manager = new CollaborationManager($client);

// Get shared memory
$memory = $manager->getSharedMemory();

// Create a data collector agent
$collector = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'collector',
    capabilities: ['collection'],
    options: ['system_prompt' => 'You collect and store data.']
);

// Create a processor agent
$processor = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'processor',
    capabilities: ['processing'],
    options: ['system_prompt' => 'You process and analyze data.']
);

$manager->registerAgent('collector', $collector);
$manager->registerAgent('processor', $processor);

// Step 1: Collector gathers data
$collectorResult = $collector->run('List 3 benefits of PHP');

// Store in shared memory
$memory->write('php_benefits', $collectorResult->getAnswer(), 'collector');
echo "Collector stored data in shared memory\n";

// Step 2: Processor reads and analyzes
$data = $memory->read('php_benefits', 'processor');
$processorResult = $processor->run("Analyze and prioritize: {$data}");

// Store analysis
$memory->write('analysis', $processorResult->getAnswer(), 'processor');
echo "Processor stored analysis\n\n";

// View shared memory statistics
$stats = $memory->getStatistics();
echo "Shared memory stats:\n";
echo "- Total keys: {$stats['total_keys']}\n";
echo "- Operations: {$stats['total_operations']}\n";
echo "- Unique agents: {$stats['unique_agents']}\n";
```

## Part 4: Broadcast Communication

Send messages to all agents at once:

```php
<?php

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);
$manager = new CollaborationManager($client, [
    'protocol' => Protocol::broadcast(),
]);

// Create multiple agents
$agents = [];
for ($i = 1; $i <= 3; $i++) {
    $agent = new SimpleCollaborativeAgent(
        client: $client,
        agentId: "agent{$i}",
        capabilities: ["task{$i}"]
    );
    $agents["agent{$i}"] = $agent;
    $manager->registerAgent("agent{$i}", $agent);
}

// Send broadcast message
$broadcast = new Message(
    from: 'coordinator',
    to: 'broadcast',
    content: 'Task distribution: Each agent should report their status'
);

$manager->sendMessage($broadcast);

// Check all agents received it
foreach ($agents as $id => $agent) {
    $count = $agent->getUnreadCount();
    echo "{$id} received {$count} message(s)\n";
}
```

## Part 5: Complete Multi-Agent System

Build a research team with multiple specialized agents:

```php
<?php

use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudeAgents\MultiAgent\Protocol;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp($_ENV['ANTHROPIC_API_KEY']);

// Create a research team
$manager = new CollaborationManager($client, [
    'max_rounds' => 10,
    'protocol' => Protocol::requestResponse(),
]);

// 1. Researcher: Gathers information
$researcher = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research', 'fact_finding'],
    options: [
        'system_prompt' => 'You are a research specialist. Find accurate, factual information.',
    ]
);

// 2. Analyst: Analyzes data
$analyst = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'analyst',
    capabilities: ['analysis', 'evaluation'],
    options: [
        'system_prompt' => 'You are a data analyst. Evaluate and find insights in information.',
    ]
);

// 3. Critic: Finds flaws and gaps
$critic = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'critic',
    capabilities: ['criticism', 'quality_assurance'],
    options: [
        'system_prompt' => 'You are a critical reviewer. Find flaws, gaps, and areas for improvement.',
    ]
);

// 4. Synthesizer: Creates final output
$synthesizer = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'synthesizer',
    capabilities: ['synthesis', 'reporting'],
    options: [
        'system_prompt' => 'You create comprehensive final reports from multiple sources.',
    ]
);

// Register all agents
$manager->registerAgent('researcher', $researcher, ['research', 'fact_finding']);
$manager->registerAgent('analyst', $analyst, ['analysis', 'evaluation']);
$manager->registerAgent('critic', $critic, ['criticism', 'quality_assurance']);
$manager->registerAgent('synthesizer', $synthesizer, ['synthesis', 'reporting']);

// Run the collaboration
echo "Starting research team collaboration...\n\n";

$result = $manager->collaborate(
    'Research the impact of AI on software development, analyze the findings, ' .
    'identify potential concerns, and create a comprehensive report.'
);

// Display results
echo "=== FINAL REPORT ===\n";
echo $result->getAnswer() . "\n\n";

echo "=== COLLABORATION METRICS ===\n";
$metrics = $manager->getMetrics();
echo "Rounds completed: {$result->getIterations()}\n";
echo "Agents involved: " . implode(', ', $result->getMetadata()['agents_involved']) . "\n";
echo "Messages routed: {$metrics['messages_routed']}\n";
echo "Success rate: {$metrics['performance']['success_rate']}\n";

// View conversation history
echo "\n=== CONVERSATION FLOW ===\n";
$history = $manager->getConversationHistory();
foreach ($history as $entry) {
    echo "Round {$entry['round']} - {$entry['agent']}:\n";
    echo substr($entry['result'], 0, 100) . "...\n\n";
}
```

## Part 6: Custom Collaborative Agent

Build your own agent with custom message handling:

```php
<?php

use ClaudeAgents\MultiAgent\CollaborativeAgent;
use ClaudeAgents\MultiAgent\Message;
use ClaudeAgents\AgentResult;

class DataProcessorAgent extends CollaborativeAgent
{
    private array $dataQueue = [];
    
    public function run(string $task): AgentResult
    {
        // Process task
        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => 'You process and transform data.',
            'messages' => [['role' => 'user', 'content' => $task]],
        ]);
        
        $result = $this->extractTextContent($response->content ?? []);
        
        // Store in shared memory if manager is available
        if ($this->manager) {
            $memory = $this->manager->getSharedMemory();
            $memory->write('last_result', $result, $this->agentId);
        }
        
        return AgentResult::success(
            answer: $result,
            messages: [],
            iterations: 1
        );
    }
    
    public function getName(): string
    {
        return 'DataProcessor';
    }
    
    protected function processMessage(Message $message): void
    {
        parent::processMessage($message);
        
        // Custom handling for 'data' type messages
        if ($message->getType() === 'data') {
            $this->dataQueue[] = $message->getContent();
            $this->logger->info("Queued data from {$message->getFrom()}");
            
            // Auto-process when queue reaches threshold
            if (count($this->dataQueue) >= 5) {
                $this->processQueue();
            }
        }
    }
    
    private function processQueue(): void
    {
        $combined = implode("\n", $this->dataQueue);
        $result = $this->run("Process this batch: {$combined}");
        
        // Clear queue
        $this->dataQueue = [];
        
        // Broadcast result
        $this->broadcast("Batch processing complete: {$result->getAnswer()}");
    }
}

// Usage
$processor = new DataProcessorAgent(
    client: $client,
    agentId: 'processor',
    capabilities: ['data_processing']
);

$manager->registerAgent('processor', $processor);

// Send data messages
for ($i = 1; $i <= 5; $i++) {
    $message = new Message('sender', 'processor', "Data item {$i}", 'data');
    $manager->sendMessage($message);
}
```

## Part 7: Atomic Operations with Shared Memory

Prevent race conditions using atomic operations:

```php
<?php

$memory = $manager->getSharedMemory();

// Initialize a distributed counter
$memory->write('task_counter', 0, 'system');

// Multiple agents increment safely
function assignTask($agentId, $memory) {
    $taskNumber = $memory->increment('task_counter', $agentId);
    echo "{$agentId} assigned task #{$taskNumber}\n";
    return $taskNumber;
}

// Simulate concurrent agents
assignTask('agent1', $memory); // Task #1
assignTask('agent2', $memory); // Task #2
assignTask('agent3', $memory); // Task #3

// Use compare-and-swap for locks
function acquireLock($agentId, $memory): bool {
    return $memory->compareAndSwap(
        key: 'resource_lock',
        expected: 'unlocked',
        new: 'locked',
        agentId: $agentId
    );
}

$memory->write('resource_lock', 'unlocked', 'system');

if (acquireLock('agent1', $memory)) {
    echo "Agent1 acquired lock\n";
    // Do critical work
    $memory->write('resource_lock', 'unlocked', 'agent1');
}
```

## Best Practices

1. **Clear Capabilities**: Define specific capabilities for each agent
   ```php
   // Good
   $agent = new SimpleCollaborativeAgent($client, 'researcher', ['research', 'fact_checking']);
   
   // Too vague
   $agent = new SimpleCollaborativeAgent($client, 'agent1', ['general']);
   ```

2. **Use Protocols**: Enforce communication patterns
   ```php
   $manager = new CollaborationManager($client, [
       'protocol' => Protocol::requestResponse(), // Structured communication
   ]);
   ```

3. **Monitor Performance**: Track metrics
   ```php
   $metrics = $manager->getMetrics();
   if ($metrics['messages_routed'] > 1000) {
       // Optimize or scale
   }
   ```

4. **Handle Failures**: Always check results
   ```php
   $result = $manager->collaborate($task);
   if (!$result->isSuccess()) {
       $error = $result->getError();
       // Handle error
   }
   ```

5. **Clean Up**: Clear state between sessions
   ```php
   $agent->clearInbox();
   $agent->clearOutbox();
   $memory->clear();
   ```

## Next Steps

- Explore [Protocol patterns](../MultiAgent.md#protocols)
- Learn about [HierarchicalAgent](../HierarchicalAgent.md) for master-worker patterns
- Check out [CoordinatorAgent](../CoordinatorAgent.md) for load balancing
- Build [async multi-agent systems](#) with AMPHP

## Troubleshooting

**Problem**: Messages not being delivered  
**Solution**: Ensure `enable_message_passing` is true and agents implement `CollaborativeInterface`

**Problem**: Infinite collaboration loops  
**Solution**: Set appropriate `max_rounds` limit

**Problem**: Protocol rejecting messages  
**Solution**: Check message types match protocol requirements

**Problem**: Race conditions in shared memory  
**Solution**: Use atomic operations (`compareAndSwap`, `increment`)

