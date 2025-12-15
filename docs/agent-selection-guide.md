# Agent Selection Guide

**A comprehensive guide to choosing the right agent for your task in the Claude PHP Agent Framework**

---

## Table of Contents

1. [Introduction](#introduction)
2. [Quick Decision Tree](#quick-decision-tree)
3. [Agent Categories](#agent-categories)
4. [Detailed Agent Reference](#detailed-agent-reference)
5. [Selection Criteria](#selection-criteria)
6. [Common Use Cases](#common-use-cases)
7. [Performance Characteristics](#performance-characteristics)
8. [Examples and Code Patterns](#examples-and-code-patterns)

---

## Introduction

The Claude PHP Agent Framework provides **25+ specialized agent types** designed for different problem domains, scales, and requirements. This guide helps you understand when and how to select the appropriate agent for your specific use case.

### 🎯 Don't Want to Choose? Use the Adaptive Agent Service!

**If you're unsure which agent to use**, consider the **[Adaptive Agent Service](./adaptive-agent-service.md)** - a meta-agent that automatically:
- Analyzes your task to understand requirements
- Selects the best agent from your registered agents
- Validates the result quality and correctness
- Adapts by trying different agents or reframing if needed

This guide is still useful for understanding what each agent does and for manually selecting agents when you know exactly what you need.

### Why Multiple Agent Types?

Different tasks have different characteristics:
- **Complexity**: Simple rule-based vs. complex reasoning
- **Scale**: Single-step vs. million-step tasks
- **Interaction**: Conversational vs. batch processing
- **Requirements**: Real-time monitoring vs. long-running goals
- **Quality**: Speed vs. accuracy trade-offs

Selecting the right agent ensures optimal performance, cost-efficiency, and reliability.

---

## Quick Decision Tree

```
START HERE: What is your primary goal?

┌─────────────────────────────────────────────────────────────┐
│ Not sure which agent to use? Want automatic selection?     │
│ → AdaptiveAgentService (intelligent auto-selection)        │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need to solve complex tasks with tools?                    │
│ → ReactAgent (general purpose, 10-100 steps)               │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need guaranteed accuracy for massive tasks?                │
│ → MakerAgent (million-step scale, zero errors)             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Building a conversational interface?                        │
│ → DialogAgent + IntentClassifierAgent                      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need to monitor systems or metrics?                        │
│ → MonitoringAgent + AlertAgent                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need specialized expertise on different tasks?             │
│ → HierarchicalAgent (master-worker pattern)                │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Quality is critical (code, writing)?                        │
│ → ReflectionAgent (iterative refinement)                   │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need to ground responses in knowledge?                     │
│ → RAGAgent (document-based retrieval)                      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Need fast rule-based responses?                            │
│ → ReflexAgent (condition-action rules)                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Agent Categories

The framework organizes agents into **7 categories** based on their primary purpose:

### 1. 🎯 Foundational Agents
*Core agentic patterns for general-purpose tasks*

- **ReactAgent** - Reason-Act-Observe loop with tools
- **PlanExecuteAgent** - Planning followed by execution
- **ReflectionAgent** - Generate-critique-refine cycle
- **HierarchicalAgent** - Master-worker delegation
- **AutonomousAgent** - Persistent goal pursuit across sessions

### 2. 🏭 Production Operations
*Monitoring, scheduling, and alerting for production systems*

- **MonitoringAgent** - System and metric monitoring
- **SchedulerAgent** - Task scheduling and cron jobs
- **AlertAgent** - Intelligent notification processing

### 3. 🤖 Classical AI
*Traditional AI agent architectures (Russell & Norvig)*

- **ReflexAgent** - Simple condition-action rules
- **ModelBasedAgent** - State-aware decision making
- **UtilityBasedAgent** - Optimization and trade-off analysis
- **LearningAgent** - Experience-based adaptation

### 4. 🤝 Multi-Agent Systems
*Collaboration and coordination between multiple agents*

- **CollaborationManager** - AutoGen-style multi-agent collaboration
- **TaskPrioritizationAgent** - BabyAGI-style task management
- **CoordinatorAgent** - Enhanced agent orchestration and load balancing
- **WorkerAgent** - Specialized worker for hierarchical systems

### 5. 💬 Conversational
*Dialog management and natural language understanding*

- **DialogAgent** - Multi-turn conversation management
- **IntentClassifierAgent** - Intent recognition and entity extraction

### 6. 🎓 Specialized Roles
*Domain-specific capabilities*

- **EnvironmentSimulatorAgent** - What-if analysis and prediction
- **SolutionDiscriminatorAgent** - Solution evaluation and ranking
- **MemoryManagerAgent** - Shared knowledge management

### 7. 🚀 Advanced Patterns
*Cutting-edge reasoning and exploration techniques*

- **MakerAgent** - Million-step tasks with zero errors (MDAP)
- **TreeOfThoughtsAgent** - Tree-based exploration
- **ChainOfThoughtAgent** - Step-by-step reasoning
- **RAGAgent** - Retrieval-Augmented Generation
- **MicroAgent** - Atomic units for MAKER framework

---

## Detailed Agent Reference

### ReactAgent
**Pattern**: Reason-Act-Observe Loop  
**Best For**: General-purpose autonomous tasks requiring tools  
**Scale**: ~10-100 steps  
**Complexity**: Medium

**When to Use:**
- You need an agent to use multiple tools intelligently
- The task requires iterative problem-solving
- You want observation-driven refinement
- You're building a research, analysis, or data processing system

**Configuration:**
```php
$agent = new ReactAgent($client, [
    'tools' => [$searchTool, $calculatorTool, $weatherTool],
    'max_iterations' => 10,
    'system' => 'You are a helpful research assistant.',
]);
```

**Example Use Cases:**
- Web research and data gathering
- Financial analysis and calculations
- API integration and data processing
- Multi-step problem solving

**Strengths:**
- ✅ Flexible and general-purpose
- ✅ Good tool orchestration
- ✅ Self-correcting through observation

**Limitations:**
- ❌ Can struggle with very long task sequences
- ❌ May need guidance for complex planning

---

### MakerAgent (MDAP)
**Pattern**: Massively Decomposed Agentic Processes  
**Best For**: Million-step tasks requiring zero errors  
**Scale**: Millions of steps  
**Complexity**: Extreme

**When to Use:**
- You need guaranteed accuracy on massive tasks
- The task can be decomposed into atomic subtasks
- Error rates must be near-zero
- You're working on organization-level processes

**Configuration:**
```php
$maker = new MakerAgent($client, [
    'voting_k' => 3,                    // First-to-ahead-by-3 voting
    'enable_red_flagging' => true,      // Detect unreliable responses
    'max_decomposition_depth' => 10,    // Extreme decomposition
]);
```

**Example Use Cases:**
- Complex algorithmic tasks (Towers of Hanoi)
- Large-scale data processing pipelines
- Multi-phase project execution
- Tasks requiring absolute correctness

**Strengths:**
- ✅ Scales to millions of steps
- ✅ Near-zero error rates through voting
- ✅ Sub-linear cost scaling
- ✅ Red-flagging for reliability

**Limitations:**
- ❌ Higher initial overhead
- ❌ Best for decomposable tasks
- ❌ More complex to configure

---

### HierarchicalAgent
**Pattern**: Master-Worker Delegation  
**Best For**: Multi-domain tasks requiring specialized expertise  
**Scale**: ~100-5K steps  
**Complexity**: High

**When to Use:**
- Tasks span multiple domains (research, writing, coding)
- You need specialized expertise for different subtasks
- Task can be decomposed by capability
- You want coordinated execution

**Configuration:**
```php
$master = new HierarchicalAgent($client);

$master->registerWorker('researcher', new WorkerAgent($client, [
    'specialty' => 'research and information gathering',
]));

$master->registerWorker('writer', new WorkerAgent($client, [
    'specialty' => 'writing and content creation',
]));
```

**Example Use Cases:**
- Business analysis and report generation
- Multi-phase project execution
- Content creation with research
- Complex decision support systems

**Strengths:**
- ✅ Specialized expertise per domain
- ✅ Efficient task delegation
- ✅ Good for complex workflows

**Limitations:**
- ❌ Requires upfront worker definition
- ❌ Overhead from coordination

---

### ReflectionAgent
**Pattern**: Generate-Critique-Refine  
**Best For**: Quality-critical outputs  
**Scale**: ~50-500 steps  
**Complexity**: Medium

**When to Use:**
- Output quality is more important than speed
- You're generating code, writing, or creative content
- You want iterative improvement
- Quality can be scored objectively

**Configuration:**
```php
$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,  // 1-10 scale
]);
```

**Example Use Cases:**
- Code generation and optimization
- Technical writing and documentation
- Creative writing
- Design and planning documents

**Strengths:**
- ✅ High-quality outputs
- ✅ Self-improving
- ✅ Quality scoring

**Limitations:**
- ❌ Slower than single-pass agents
- ❌ Higher token costs

---

### DialogAgent
**Pattern**: Multi-turn Conversation Management  
**Best For**: Conversational interfaces  
**Scale**: Continuous (session-based)  
**Complexity**: Low-Medium

**When to Use:**
- Building chatbots or virtual assistants
- Need to maintain context across turns
- Supporting multi-turn conversations
- Session-based interactions

**Configuration:**
```php
$dialog = new DialogAgent($client, [
    'session_timeout' => 3600,  // 1 hour
]);

$session = $dialog->startConversation();
$response1 = $dialog->turn("Hi, I need help with my order");
$response2 = $dialog->turn("Order #12345");
```

**Example Use Cases:**
- Customer service chatbots
- Virtual assistants
- Interactive help systems
- Support ticket systems

**Strengths:**
- ✅ Context preservation
- ✅ Session management
- ✅ Natural conversations

**Limitations:**
- ❌ Requires state management
- ❌ Context window limitations

---

### MonitoringAgent
**Pattern**: Continuous Data Stream Monitoring  
**Best For**: System monitoring and anomaly detection  
**Scale**: Real-time, continuous  
**Complexity**: Low-Medium

**When to Use:**
- Monitoring servers, applications, or metrics
- Need anomaly detection
- LLM-enhanced alerting
- Pattern analysis

**Configuration:**
```php
$monitor = new MonitoringAgent($client, [
    'thresholds' => ['cpu' => 80, 'memory' => 90],
    'check_interval' => 60,  // seconds
]);

$monitor->watch($dataSource, function($alert) {
    // Handle alert
});
```

**Example Use Cases:**
- Server and infrastructure monitoring
- Application performance monitoring
- Business metrics tracking
- Security monitoring

**Strengths:**
- ✅ Real-time detection
- ✅ LLM-powered insights
- ✅ Threshold-based alerts

**Limitations:**
- ❌ Requires continuous running
- ❌ Cost for high-frequency checks

---

### AlertAgent
**Pattern**: Intelligent Notification Processing  
**Best For**: Smart alerting and escalation  
**Scale**: Real-time  
**Complexity**: Low

**When to Use:**
- Processing and routing alerts
- Need intelligent severity classification
- Aggregating and deduplicating alerts
- Multi-channel delivery

**Configuration:**
```php
$alertAgent = new AlertAgent($client, [
    'aggregation_window' => 300,  // 5 minutes
]);

$alertAgent->registerChannel('email', $emailHandler);
$alertAgent->registerChannel('slack', $slackHandler);

$alertAgent->processAlert([
    'message' => 'High CPU usage detected',
    'severity' => 'warning',
]);
```

**Example Use Cases:**
- Incident management
- System notifications
- Alert routing and escalation
- On-call systems

**Strengths:**
- ✅ Intelligent routing
- ✅ Aggregation and deduplication
- ✅ Multi-channel support

**Limitations:**
- ❌ Best paired with monitoring systems
- ❌ Requires channel configuration

---

### RAGAgent
**Pattern**: Retrieval-Augmented Generation  
**Best For**: Knowledge-grounded responses  
**Scale**: ~10-100 steps per query  
**Complexity**: Medium

**When to Use:**
- Need to ground responses in specific documents
- Building knowledge base systems
- Want source attribution
- Handling domain-specific information

**Configuration:**
```php
$rag = new RAGAgent($client, [
    'top_k' => 3,  // Retrieve top 3 sources
]);

$rag->addDocument('PHP Basics', $phpContent);
$rag->addDocument('OOP Guide', $oopContent);

$result = $rag->run('How do I use namespaces in PHP?');
```

**Example Use Cases:**
- Documentation systems
- Knowledge bases
- Q&A systems
- Technical support

**Strengths:**
- ✅ Grounded in specific knowledge
- ✅ Source attribution
- ✅ Reduces hallucinations

**Limitations:**
- ❌ Requires document preparation
- ❌ Limited to indexed knowledge

---

### ChainOfThoughtAgent
**Pattern**: Step-by-Step Reasoning  
**Best For**: Transparent logical reasoning  
**Scale**: ~10-500 steps  
**Complexity**: Low-Medium

**When to Use:**
- Need to show reasoning process
- Working on math or logic problems
- Want transparent decision-making
- Educational or explanatory contexts

**Configuration:**
```php
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',  // or 'few_shot'
    'trigger' => "Let's think step by step.",
]);

$result = $agent->run('If a shirt costs $40 and is 20% off, what is the final price?');
```

**Example Use Cases:**
- Mathematical problem solving
- Logical reasoning tasks
- Educational systems
- Explainable AI

**Strengths:**
- ✅ Transparent reasoning
- ✅ Good for math problems
- ✅ Easy to implement

**Limitations:**
- ❌ Can be verbose
- ❌ Not always faster than direct answers

---

### TreeOfThoughtsAgent
**Pattern**: Tree-Based Exploration  
**Best For**: Complex problems with multiple solution paths  
**Scale**: ~100-1K steps  
**Complexity**: High

**When to Use:**
- Problem has multiple possible approaches
- Need to explore solution space
- Want to evaluate different paths
- Optimization or creative tasks

**Configuration:**
```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,           // Thoughts per node
    'max_depth' => 4,              // Tree depth
    'search_strategy' => 'best_first',
]);

$result = $agent->run('Find a way to make 24 using 3, 5, 7, 11');
```

**Example Use Cases:**
- Puzzle solving
- Optimization problems
- Creative writing
- Strategic planning

**Strengths:**
- ✅ Explores multiple paths
- ✅ Evaluates alternatives
- ✅ Good for exploration

**Limitations:**
- ❌ Higher computational cost
- ❌ Can be slower

---

### PlanExecuteAgent
**Pattern**: Planning Followed by Execution  
**Best For**: Complex multi-step tasks requiring upfront planning  
**Scale**: ~100-1K steps  
**Complexity**: Medium-High

**When to Use:**
- Task requires careful upfront planning
- Multiple dependent steps
- Resource coordination needed
- Project-like workflows

**Configuration:**
```php
$agent = new PlanExecuteAgent($client, [
    'max_plan_iterations' => 3,
    'tools' => [$tool1, $tool2],
]);

$result = $agent->run('Plan and execute a marketing campaign launch');
```

**Example Use Cases:**
- Project planning
- Workflow automation
- Multi-phase processes
- Resource scheduling

**Strengths:**
- ✅ Structured approach
- ✅ Good for complex workflows
- ✅ Separates planning from execution

**Limitations:**
- ❌ Less flexible than ReAct
- ❌ Upfront planning overhead

---

### AutonomousAgent
**Pattern**: Persistent Goal Pursuit  
**Best For**: Long-running tasks across multiple sessions  
**Scale**: ~100-5K steps, multi-session  
**Complexity**: Medium-High

**When to Use:**
- Tasks span multiple sessions
- Need to persist state between runs
- Long-running goals
- Progress tracking required

**Configuration:**
```php
$agent = new AutonomousAgent($client, [
    'goal' => 'Complete website redesign',
    'state_file' => '/path/to/state.json',
]);

$result = $agent->run(); // Can run multiple times
```

**Example Use Cases:**
- Long-term projects
- Background task processing
- Persistent assistants
- Multi-session workflows

**Strengths:**
- ✅ State persistence
- ✅ Multi-session support
- ✅ Progress tracking

**Limitations:**
- ❌ Requires state management
- ❌ More complex setup

---

### IntentClassifierAgent
**Pattern**: Intent Recognition and Entity Extraction  
**Best For**: Natural language understanding  
**Scale**: Instant (single call)  
**Complexity**: Low

**When to Use:**
- Routing user requests
- NLU in conversational systems
- Command interpretation
- Form filling assistance

**Configuration:**
```php
$classifier = new IntentClassifierAgent($client);

$classifier->addIntent('book_flight', [
    'I want to book a flight',
    'Book me a ticket to Paris',
]);

$result = $classifier->run("I'd like to fly to London tomorrow");
// Returns: {intent: 'book_flight', confidence: 0.95, entities: [...]}
```

**Example Use Cases:**
- Chatbot routing
- Command interpretation
- Form processing
- Request classification

**Strengths:**
- ✅ Fast classification
- ✅ Entity extraction
- ✅ Confidence scores

**Limitations:**
- ❌ Requires intent definitions
- ❌ Single-turn only

---

### ReflexAgent
**Pattern**: Condition-Action Rules  
**Best For**: Fast rule-based responses  
**Scale**: Instant  
**Complexity**: Low

**When to Use:**
- Simple, deterministic responses
- High-speed requirements
- Rule-based systems
- FAQ-style interactions

**Configuration:**
```php
$reflex = new ReflexAgent($client);

$reflex->addRule(
    'greeting',
    fn($input) => str_contains(strtolower($input), 'hello'),
    'Hello! How can I help you today?',
    priority: 10
);
```

**Example Use Cases:**
- FAQ automation
- Simple chatbots
- Command handling
- Quick responses

**Strengths:**
- ✅ Very fast
- ✅ Deterministic
- ✅ Low cost

**Limitations:**
- ❌ Limited flexibility
- ❌ Requires rule definition

---

### ModelBasedAgent
**Pattern**: State-Aware Decision Making  
**Best For**: Planning with world model  
**Scale**: ~50-500 steps  
**Complexity**: Medium

**When to Use:**
- Need to track world state
- Planning with state transitions
- Goal-directed behavior
- Simulation requirements

**Configuration:**
```php
$agent = new ModelBasedAgent($client, [
    'initial_state' => ['location' => 'home', 'time' => 'morning'],
]);

$result = $agent->run('Goal: arrive at work by 9am');
```

**Example Use Cases:**
- Route planning
- Resource management
- Game AI
- Process simulation

**Strengths:**
- ✅ State tracking
- ✅ Predictive planning
- ✅ Goal-directed

**Limitations:**
- ❌ Requires state modeling
- ❌ More complex than reflex

---

### UtilityBasedAgent
**Pattern**: Utility Maximization  
**Best For**: Optimization and trade-off analysis  
**Scale**: ~10-100 steps  
**Complexity**: Medium

**When to Use:**
- Multiple competing objectives
- Need to optimize trade-offs
- Decision support
- Resource allocation

**Configuration:**
```php
$utility = new UtilityBasedAgent($client);

$utility->addObjective('value', fn($action) => $action['value'], weight: 0.7);
$utility->addObjective('cost', fn($action) => 100 - $action['cost'], weight: 0.3);

$result = $utility->run('Choose the best investment option');
```

**Example Use Cases:**
- Investment decisions
- Resource allocation
- Pricing optimization
- Feature prioritization

**Strengths:**
- ✅ Multi-objective optimization
- ✅ Trade-off analysis
- ✅ Quantifiable decisions

**Limitations:**
- ❌ Requires utility functions
- ❌ May oversimplify complex decisions

---

### LearningAgent
**Pattern**: Experience-Based Adaptation  
**Best For**: Adaptive systems  
**Scale**: Continuous improvement  
**Complexity**: Medium-High

**When to Use:**
- Need to improve over time
- Feedback available
- Strategy adaptation
- Performance optimization

**Configuration:**
```php
$learning = new LearningAgent($client, [
    'initial_strategies' => ['analytical', 'creative', 'pragmatic'],
    'learning_rate' => 0.1,
]);

$result = $learning->run($task);
$learning->provideFeedback($result->getMetadata()['experience_id'], 
    reward: 0.8, 
    success: true
);
```

**Example Use Cases:**
- Personalization systems
- A/B testing
- Strategy optimization
- Recommendation systems

**Strengths:**
- ✅ Improves over time
- ✅ Adaptive behavior
- ✅ Feedback-driven

**Limitations:**
- ❌ Requires feedback loop
- ❌ Needs time to learn

---

### CollaborationManager (AutoGen-style)
**Pattern**: Multi-Agent Collaboration  
**Best For**: Complex tasks requiring multiple perspectives  
**Scale**: ~500-5K steps  
**Complexity**: High

**When to Use:**
- Task benefits from multiple perspectives
- Need specialized capabilities
- Complex research or analysis
- Team-like collaboration

**Configuration:**
```php
$manager = new CollaborationManager($client, ['max_rounds' => 10]);

$manager->registerAgent('researcher', $researchAgent, ['research', 'analysis']);
$manager->registerAgent('writer', $writerAgent, ['writing', 'editing']);

$result = $manager->collaborate('Write a research paper on AI ethics');
```

**Example Use Cases:**
- Research projects
- Content creation with review
- Complex analysis
- Decision making

**Strengths:**
- ✅ Multiple perspectives
- ✅ Specialized agents
- ✅ Rich discussions

**Limitations:**
- ❌ Higher token costs
- ❌ Longer execution time

---

### TaskPrioritizationAgent (BabyAGI-style)
**Pattern**: Dynamic Task Generation and Prioritization  
**Best For**: Goal-driven task management  
**Scale**: ~500-1K steps  
**Complexity**: High

**When to Use:**
- Need dynamic task breakdown
- Priority-based execution
- Goal-oriented workflows
- Adaptive planning

**Configuration:**
```php
$taskAgent = new TaskPrioritizationAgent($client, [
    'goal' => 'Launch mobile app',
]);

$result = $taskAgent->run('Launch a new mobile application');
```

**Example Use Cases:**
- Project management
- Goal decomposition
- Adaptive workflows
- Dynamic planning

**Strengths:**
- ✅ Dynamic task generation
- ✅ Priority management
- ✅ Goal-focused

**Limitations:**
- ❌ Can create many tasks
- ❌ Requires clear goals

---

### CoordinatorAgent
**Pattern**: Agent Orchestration and Load Balancing  
**Best For**: Distributed agent systems  
**Scale**: ~1K-10K steps  
**Complexity**: High

**When to Use:**
- Managing multiple agents
- Load balancing required
- Capability-based routing
- Distributed systems

**Configuration:**
```php
$coordinator = new CoordinatorAgent($client);

$coordinator->registerAgent('agent1', $agent1, ['capability1', 'capability2']);
$coordinator->registerAgent('agent2', $agent2, ['capability3']);

$result = $coordinator->run($complexTask);
```

**Example Use Cases:**
- Agent networks
- Microservices coordination
- Load distribution
- Capability routing

**Strengths:**
- ✅ Intelligent routing
- ✅ Load balancing
- ✅ Scalable

**Limitations:**
- ❌ Complex setup
- ❌ Coordination overhead

---

### SchedulerAgent
**Pattern**: Task Scheduling and Cron Jobs  
**Best For**: Time-based task execution  
**Scale**: Continuous  
**Complexity**: Low-Medium

**When to Use:**
- Need cron-style scheduling
- Recurring tasks
- Dependency management
- Batch processing

**Configuration:**
```php
$scheduler = new SchedulerAgent($client);

$scheduler->schedule('backup', '0 2 * * *', $backupTask);
$scheduler->scheduleOnce('migration', '+1 hour', $migrationTask);

$scheduler->run(); // Process scheduled tasks
```

**Example Use Cases:**
- Automated backups
- Report generation
- Data synchronization
- Maintenance tasks

**Strengths:**
- ✅ Time-based execution
- ✅ Dependency handling
- ✅ Recurring support

**Limitations:**
- ❌ Requires continuous running
- ❌ System integration needed

---

### EnvironmentSimulatorAgent
**Pattern**: What-If Analysis and Prediction  
**Best For**: Scenario simulation  
**Scale**: ~10-100 steps per simulation  
**Complexity**: Medium

**When to Use:**
- Need to predict outcomes
- What-if analysis
- Testing scenarios
- Risk assessment

**Configuration:**
```php
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => ['servers' => 3, 'load' => 'normal'],
]);

$result = $simulator->simulateAction('Add 2 more servers');
// Returns: {resulting_state, outcome, success_probability, side_effects}
```

**Example Use Cases:**
- Infrastructure planning
- Risk analysis
- Testing scenarios
- Decision support

**Strengths:**
- ✅ Outcome prediction
- ✅ Risk assessment
- ✅ Testing without execution

**Limitations:**
- ❌ Predictions may not be perfect
- ❌ Requires good state modeling

---

### SolutionDiscriminatorAgent
**Pattern**: Solution Evaluation and Ranking  
**Best For**: Choosing between alternatives  
**Scale**: ~10-50 steps  
**Complexity**: Low-Medium

**When to Use:**
- Multiple solution candidates
- Need objective evaluation
- Quality assessment
- Best option selection

**Configuration:**
```php
$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['correctness', 'efficiency', 'maintainability'],
]);

$evaluations = $discriminator->evaluateSolutions($candidateSolutions);
```

**Example Use Cases:**
- Code review
- Design selection
- Proposal evaluation
- Quality assurance

**Strengths:**
- ✅ Objective evaluation
- ✅ Multi-criteria assessment
- ✅ Ranking support

**Limitations:**
- ❌ Requires clear criteria
- ❌ Quality of criteria matters

---

### MemoryManagerAgent
**Pattern**: Shared Knowledge Management  
**Best For**: Cross-agent memory  
**Scale**: Continuous  
**Complexity**: Low-Medium

**When to Use:**
- Sharing knowledge between agents
- Persistent memory needs
- Semantic search required
- Context preservation

**Configuration:**
```php
$memory = new MemoryManagerAgent($client);

$id = $memory->store('Important fact: API rate limit is 1000/hour');
$results = $memory->search('What is the rate limit?');
```

**Example Use Cases:**
- Shared context
- Knowledge bases
- Agent communication
- Fact storage

**Strengths:**
- ✅ Persistent storage
- ✅ Semantic search
- ✅ Cross-agent sharing

**Limitations:**
- ❌ Requires storage backend
- ❌ Search quality varies

---

## Selection Criteria

### By Task Complexity

**Simple (< 100 steps)**
- ReflexAgent - Rule-based responses
- IntentClassifierAgent - Intent recognition
- UtilityBasedAgent - Simple optimization
- ChainOfThoughtAgent - Basic reasoning

**Medium (100-1K steps)**
- ReactAgent - General tool usage
- PlanExecuteAgent - Structured workflows
- ReflectionAgent - Quality-critical tasks
- ModelBasedAgent - State-aware planning
- RAGAgent - Knowledge-grounded responses
- TreeOfThoughtsAgent - Solution exploration

**Complex (1K-10K steps)**
- HierarchicalAgent - Multi-domain tasks
- CollaborationManager - Multi-agent work
- TaskPrioritizationAgent - Goal decomposition
- CoordinatorAgent - Agent orchestration

**Million-step Scale**
- MakerAgent - Extreme scale with zero errors

---

### By Primary Requirement

**Speed & Responsiveness**
1. ReflexAgent (instant, rule-based)
2. IntentClassifierAgent (single call)
3. ReactAgent (efficient tool use)

**Accuracy & Quality**
1. MakerAgent (near-zero errors)
2. ReflectionAgent (iterative refinement)
3. SolutionDiscriminatorAgent (evaluation)

**Knowledge Grounding**
1. RAGAgent (document-based)
2. MemoryManagerAgent (shared knowledge)

**Reasoning & Explanation**
1. ChainOfThoughtAgent (step-by-step)
2. TreeOfThoughtsAgent (exploration)
3. PlanExecuteAgent (planning)

**Specialization**
1. HierarchicalAgent (expert workers)
2. CollaborationManager (multiple perspectives)
3. WorkerAgent (specific domains)

**Production Operations**
1. MonitoringAgent (system watching)
2. AlertAgent (notification routing)
3. SchedulerAgent (time-based tasks)

**Conversation**
1. DialogAgent (multi-turn)
2. IntentClassifierAgent (understanding)

---

### By Use Case Domain

**Customer Support**
→ DialogAgent + IntentClassifierAgent + ReflexAgent

**System Monitoring**
→ MonitoringAgent + AlertAgent

**Research & Analysis**
→ ReactAgent or CollaborationManager + RAGAgent

**Code Generation**
→ ReflectionAgent or MakerAgent (if complex)

**Content Creation**
→ HierarchicalAgent or ReflectionAgent

**Decision Support**
→ UtilityBasedAgent or EnvironmentSimulatorAgent

**Project Management**
→ TaskPrioritizationAgent or PlanExecuteAgent

**Automation & Workflows**
→ SchedulerAgent + ReactAgent

**Knowledge Systems**
→ RAGAgent + MemoryManagerAgent

**Optimization**
→ TreeOfThoughtsAgent or UtilityBasedAgent

---

## Common Use Cases

### 1. Building a Customer Service Chatbot

**Recommended Stack:**
- **IntentClassifierAgent** - Route requests by intent
- **DialogAgent** - Manage conversation state
- **ReflexAgent** - Handle FAQs quickly
- **RAGAgent** - Ground responses in documentation

**Example Flow:**
```php
// 1. Classify intent
$intent = $intentClassifier->run($userMessage);

// 2. Route to appropriate handler
if ($intent['intent'] === 'faq') {
    $response = $reflexAgent->run($userMessage);
} else {
    // 3. Use dialog agent for complex interactions
    $response = $dialogAgent->turn($userMessage);
}
```

---

### 2. System Monitoring and Alerting

**Recommended Stack:**
- **MonitoringAgent** - Watch metrics and detect anomalies
- **AlertAgent** - Process and route alerts
- **SchedulerAgent** - Periodic health checks

**Example Flow:**
```php
// 1. Schedule monitoring
$scheduler->schedule('health_check', '*/5 * * * *', function() use ($monitoring) {
    $monitoring->checkMetrics();
});

// 2. Monitor with alerting
$monitoring->watch($metricStream, function($alert) use ($alertAgent) {
    $alertAgent->processAlert($alert);
});
```

---

### 3. Research and Report Generation

**Recommended Stack:**
- **HierarchicalAgent** - Coordinate specialized agents
- **WorkerAgent** (researcher) - Gather information
- **WorkerAgent** (analyst) - Analyze data
- **WorkerAgent** (writer) - Create report

**Example Flow:**
```php
$master = new HierarchicalAgent($client);
$master->registerWorker('researcher', $researchAgent);
$master->registerWorker('analyst', $analysisAgent);
$master->registerWorker('writer', $writerAgent);

$result = $master->run('Research and report on market trends');
```

---

### 4. Code Generation with Quality Assurance

**Recommended Stack:**
- **ReflectionAgent** - Generate and refine code
- **SolutionDiscriminatorAgent** - Evaluate alternatives

**Example Flow:**
```php
// Generate with iterative refinement
$reflection = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

$result = $reflection->run('Write a secure authentication function');
```

---

### 5. Complex Multi-Step Project

**Recommended Stack:**
- **MakerAgent** - For extremely complex tasks
- **TaskPrioritizationAgent** - For goal decomposition
- **PlanExecuteAgent** - For structured workflows

**Example Flow:**
```php
// For massive tasks requiring zero errors
$maker = new MakerAgent($client, [
    'voting_k' => 3,
    'enable_red_flagging' => true,
]);

$result = $maker->run('Implement complete e-commerce system');
```

---

### 6. Knowledge Base Q&A System

**Recommended Stack:**
- **RAGAgent** - Document retrieval and grounding
- **MemoryManagerAgent** - Persistent knowledge
- **IntentClassifierAgent** - Query understanding

**Example Flow:**
```php
// 1. Build knowledge base
$rag = new RAGAgent($client);
$rag->addDocuments($documentCollection);

// 2. Answer queries
$result = $rag->run('How do I configure SSL?');
// Includes source citations
```

---

### 7. Decision Support System

**Recommended Stack:**
- **UtilityBasedAgent** - Multi-objective optimization
- **EnvironmentSimulatorAgent** - Scenario testing
- **TreeOfThoughtsAgent** - Explore alternatives

**Example Flow:**
```php
// 1. Simulate scenarios
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => $currentState,
]);
$outcomes = $simulator->simulateAction($proposedAction);

// 2. Evaluate options
$utility = new UtilityBasedAgent($client);
$utility->addObjective('roi', $roiFunction, weight: 0.6);
$utility->addObjective('risk', $riskFunction, weight: 0.4);

$decision = $utility->run('Choose best investment strategy');
```

---

### 8. Adaptive Learning System

**Recommended Stack:**
- **LearningAgent** - Adapt based on feedback
- **ReflectionAgent** - Quality improvement

**Example Flow:**
```php
$learning = new LearningAgent($client, [
    'initial_strategies' => ['strategy1', 'strategy2', 'strategy3'],
    'learning_rate' => 0.1,
]);

// Execute and learn
$result = $learning->run($task);

// Provide feedback
$learning->provideFeedback(
    $result->getMetadata()['experience_id'],
    reward: 0.9,
    success: true
);
```

---

## Performance Characteristics

### Token Usage

**Low Token Usage (< 1K tokens/task)**
- ReflexAgent
- IntentClassifierAgent
- ChainOfThoughtAgent (simple tasks)

**Medium Token Usage (1K-10K tokens/task)**
- ReactAgent
- PlanExecuteAgent
- ReflectionAgent
- RAGAgent

**High Token Usage (10K-100K+ tokens/task)**
- HierarchicalAgent
- CollaborationManager
- TreeOfThoughtsAgent
- MakerAgent (but sub-linear scaling)

---

### Execution Time

**Instant (< 1 second)**
- ReflexAgent

**Fast (1-10 seconds)**
- IntentClassifierAgent
- ChainOfThoughtAgent
- RAGAgent (with indexed docs)

**Medium (10-60 seconds)**
- ReactAgent
- PlanExecuteAgent
- ReflectionAgent

**Slow (1-10 minutes)**
- HierarchicalAgent
- CollaborationManager
- TreeOfThoughtsAgent

**Very Long (hours to days)**
- MakerAgent (for million-step tasks)
- AutonomousAgent (multi-session)

---

### Cost Efficiency

**Most Cost-Efficient**
1. ReflexAgent (rule-based, minimal LLM use)
2. IntentClassifierAgent (single call)
3. ChainOfThoughtAgent (single pass)

**Moderate Cost**
1. ReactAgent (iterative but bounded)
2. RAGAgent (retrieval + generation)
3. ReflectionAgent (multiple passes)

**Higher Cost**
1. CollaborationManager (multiple agents)
2. TreeOfThoughtsAgent (exploration)
3. HierarchicalAgent (multiple workers)

**Specialized Cost Profile**
- MakerAgent: High absolute cost but sub-linear scaling for massive tasks

---

### Reliability

**Highest Reliability**
1. MakerAgent (near-zero errors with voting)
2. ReflexAgent (deterministic rules)
3. ReflectionAgent (quality-driven)

**High Reliability**
1. RAGAgent (grounded in documents)
2. PlanExecuteAgent (structured approach)
3. SolutionDiscriminatorAgent (evaluation-based)

**Standard Reliability**
1. ReactAgent (observation-based correction)
2. ChainOfThoughtAgent (reasoning-based)
3. DialogAgent (context-aware)

---

## Examples and Code Patterns

### Pattern 1: Single Agent for Simple Tasks

```php
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Tools\Tool;

$tool = Tool::create('calculator')
    ->description('Perform calculations')
    ->stringParam('expression', 'Math expression')
    ->handler(fn($input) => eval("return {$input['expression']};"));

$agent = new ReactAgent($client, [
    'tools' => [$tool],
    'max_iterations' => 5,
]);

$result = $agent->run('Calculate 42 * 17 + 100');
echo $result->getAnswer();
```

---

### Pattern 2: Hierarchical Specialization

```php
use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;

$master = new HierarchicalAgent($client);

$master->registerWorker('research', new WorkerAgent($client, [
    'specialty' => 'research and data gathering',
]));

$master->registerWorker('analysis', new WorkerAgent($client, [
    'specialty' => 'data analysis and insights',
]));

$master->registerWorker('writing', new WorkerAgent($client, [
    'specialty' => 'content creation',
]));

$result = $master->run('Research PHP trends and write a report');
```

---

### Pattern 3: Multi-Agent Collaboration

```php
use ClaudeAgents\MultiAgent\CollaborationManager;
use ClaudeAgents\Agents\ReactAgent;

$manager = new CollaborationManager($client, [
    'max_rounds' => 10,
]);

$researcher = new ReactAgent($client, ['tools' => [$searchTool]]);
$critic = new ReactAgent($client, []);

$manager->registerAgent('researcher', $researcher, ['research']);
$manager->registerAgent('critic', $critic, ['review', 'critique']);

$result = $manager->collaborate('Research AI ethics and critique findings');
```

---

### Pattern 4: Quality-Driven Generation

```php
use ClaudeAgents\Agents\ReflectionAgent;

$agent = new ReflectionAgent($client, [
    'max_refinements' => 5,
    'quality_threshold' => 9,  // High quality bar
]);

$result = $agent->run('Generate a secure authentication system in PHP');

// Access refinement history
$history = $result->getMetadata()['refinement_history'];
foreach ($history as $round) {
    echo "Round {$round['iteration']}: Quality {$round['score']}/10\n";
}
```

---

### Pattern 5: Knowledge-Grounded Responses

```php
use ClaudeAgents\Agents\RAGAgent;

$rag = new RAGAgent($client);

// Index documents
$rag->addDocument('PHP Manual', file_get_contents('php-manual.txt'));
$rag->addDocument('Best Practices', file_get_contents('best-practices.txt'));

// Query with source attribution
$result = $rag->run('What are PHP namespace best practices?');

echo $result->getAnswer() . "\n\n";
echo "Sources:\n";
foreach ($result->getMetadata()['sources'] as $source) {
    echo "- {$source['title']}\n";
}
```

---

### Pattern 6: Monitoring and Alerting

```php
use ClaudeAgents\Agents\MonitoringAgent;
use ClaudeAgents\Agents\AlertAgent;

$monitor = new MonitoringAgent($client, [
    'thresholds' => [
        'cpu' => 80,
        'memory' => 90,
        'disk' => 85,
    ],
    'check_interval' => 60,
]);

$alerts = new AlertAgent($client);
$alerts->registerChannel('email', $emailHandler);
$alerts->registerChannel('slack', $slackHandler);

$monitor->watch($metricsSource, function($alert) use ($alerts) {
    $alerts->processAlert($alert);
});
```

---

### Pattern 7: Conversational Interface

```php
use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Agents\IntentClassifierAgent;

// Setup intent classification
$classifier = new IntentClassifierAgent($client);
$classifier->addIntent('support', ['help', 'issue', 'problem']);
$classifier->addIntent('sales', ['buy', 'purchase', 'pricing']);

// Setup dialog management
$dialog = new DialogAgent($client);
$session = $dialog->startConversation();

// Handle user input
$userMessage = "I need help with my account";

// Classify and route
$intent = $classifier->run($userMessage);
$response = $dialog->turn($userMessage);

echo "Intent: {$intent['intent']} ({$intent['confidence']})\n";
echo "Response: {$response}\n";
```

---

### Pattern 8: Million-Step Reliable Execution

```php
use ClaudeAgents\Agents\MakerAgent;

$maker = new MakerAgent($client, [
    'voting_k' => 3,                    // First-to-ahead-by-3
    'enable_red_flagging' => true,      // Detect unreliable responses
    'max_decomposition_depth' => 10,    // Extreme decomposition
]);

$result = $maker->run('Solve 20-disk Towers of Hanoi');

// Track execution stats
$stats = $result->getMetadata()['execution_stats'];
echo "Total Steps: {$stats['total_steps']}\n";
echo "Votes Cast: {$stats['votes_cast']}\n";
echo "Error Rate: " . $result->getMetadata()['error_rate'] . "\n";
```

---

### Pattern 9: Decision Support with Simulation

```php
use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudeAgents\Agents\UtilityBasedAgent;

// Simulate scenarios
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => ['budget' => 100000, 'team' => 5],
]);

$scenario1 = $simulator->simulateAction('Hire 2 more developers');
$scenario2 = $simulator->simulateAction('Invest in automation tools');

// Evaluate with utility
$utility = new UtilityBasedAgent($client);
$utility->addObjective('roi', $roiCalc, weight: 0.6);
$utility->addObjective('risk', $riskCalc, weight: 0.4);

$decision = $utility->run('Choose between hiring or tooling');
```

---

### Pattern 10: Adaptive Learning System

```php
use ClaudeAgents\Agents\LearningAgent;

$agent = new LearningAgent($client, [
    'initial_strategies' => [
        'aggressive' => 'Take bold, innovative approaches',
        'conservative' => 'Use proven, safe methods',
        'balanced' => 'Mix innovation with stability',
    ],
    'learning_rate' => 0.1,
    'replay_buffer_size' => 1000,
]);

// Execute task
$result = $agent->run($task);

// Provide feedback to improve
$experienceId = $result->getMetadata()['experience_id'];
$agent->provideFeedback($experienceId, reward: 0.85, success: true);

// Over time, agent learns which strategy works best
```

---

## Conclusion

The Claude PHP Agent Framework provides a rich set of agents for virtually any use case. The key to success is:

1. **Understand your requirements** - Speed, accuracy, scale, cost
2. **Match agent capabilities** - Use the comparison tables above
3. **Start simple** - ReactAgent is a great default choice
4. **Compose when needed** - Combine agents for complex workflows
5. **Iterate and measure** - Track performance and adjust

### Quick Reference

**Default Choice**: ReactAgent (general-purpose, tool-using)  
**For Scale**: MakerAgent (million-step, zero errors)  
**For Quality**: ReflectionAgent (iterative refinement)  
**For Specialization**: HierarchicalAgent (multi-domain)  
**For Conversation**: DialogAgent + IntentClassifierAgent  
**For Monitoring**: MonitoringAgent + AlertAgent  
**For Knowledge**: RAGAgent (document-grounded)  
**For Speed**: ReflexAgent (rule-based)

### Further Reading

- [README.md](../README.md) - Framework overview
- [AGENT_TAXONOMY.md](../AGENT_TAXONOMY.md) - Complete agent reference
- [MAKER_IMPLEMENTATION.md](../MAKER_IMPLEMENTATION.md) - MAKER details
- [examples/](../examples/) - Working code examples
- [FEATURES.md](../FEATURES.md) - Feature documentation

---

**Happy agent building! 🚀**

