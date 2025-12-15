# DebateSystem

## Overview

The **DebateSystem** enables multi-agent debates where different AI agents with distinct perspectives discuss and analyze complex topics. It orchestrates structured conversations, synthesizes insights, and measures agreement across different viewpoints. This pattern is particularly valuable for decision-making, exploring trade-offs, and stress-testing ideas.

## Key Features

- **Multi-Agent Debates**: Multiple agents with different perspectives participate in structured discussions
- **Built-in Patterns**: Pre-configured debate patterns for common scenarios
- **Moderator Synthesis**: Automatic synthesis of debate outcomes with balanced conclusions
- **Agreement Measurement**: Quantitative scoring of consensus levels
- **Round-Based Structure**: Organized debate rounds with context building
- **Fluent Interface**: Chainable methods for easy configuration
- **Flexible Architecture**: Create custom agents and debate structures

## Architecture

The DebateSystem orchestrates several components:

```
┌────────────────┐
│  DebateSystem  │
└────────┬───────┘
         │
         ├─► DebateAgent (multiple)
         ├─► DebateModerator
         ├─► DebateRound
         └─► DebateResult
```

### Components

1. **DebateSystem**: Orchestrates the overall debate process
2. **DebateAgent**: Individual agents with specific perspectives
3. **DebateModerator**: Synthesizes conclusions and measures agreement
4. **DebateRound**: Represents a single round of statements
5. **DebateResult**: Contains the complete debate outcome with analysis

## Installation

The DebateSystem is included in the claude-php-agent package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

### Creating a Basic Debate

```php
use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create agents with different perspectives
$proAgent = new DebateAgent(
    $client,
    'Proponent',
    'support',
    'You advocate for proposals. Present benefits and opportunities.'
);

$conAgent = new DebateAgent(
    $client,
    'Opponent',
    'oppose',
    'You challenge proposals. Identify risks and drawbacks.'
);

// Create and configure debate system
$system = DebateSystem::create($client)
    ->addAgent('pro', $proAgent)
    ->addAgent('con', $conAgent)
    ->rounds(2);

// Run the debate
$result = $system->debate('Should we adopt a 4-day work week?');

// Access results
echo "Topic: " . $result->getTopic() . "\n";
echo "Synthesis: " . $result->getSynthesis() . "\n";
echo "Agreement Score: " . round($result->getAgreementScore() * 100) . "%\n";
```

## Built-in Debate Patterns

### 1. Pro/Con Debate

Two-sided debate with advocates and opponents:

```php
use ClaudeAgents\Debate\Patterns\ProConDebate;

$system = ProConDebate::create(
    $client, 
    'Should we implement feature X?',
    rounds: 3
);

$result = $system->debate('Should we implement feature X?');
```

**Agents:**
- **Proponent**: Advocates for the proposal
- **Opponent**: Challenges the proposal

**Best for:** Binary decisions, proposals requiring scrutiny

### 2. Round Table Debate

Multi-perspective discussion with specialized roles:

```php
use ClaudeAgents\Debate\Patterns\RoundTableDebate;

$system = RoundTableDebate::create($client, rounds: 2);
$result = $system->debate('What technology should we choose?');
```

**Agents:**
- **User Advocate**: User needs and experience
- **Engineer**: Technical feasibility and complexity
- **Business Analyst**: ROI and strategic value
- **Designer**: UX and design considerations

**Best for:** Complex decisions requiring multiple viewpoints, product planning

### 3. Consensus Builder

Building agreement between pragmatic and idealistic approaches:

```php
use ClaudeAgents\Debate\Patterns\ConsensusBuilder;

$system = ConsensusBuilder::create($client, rounds: 3);
$result = $system->debate('How should we balance quality vs speed?');
```

**Agents:**
- **Pragmatist**: Practical solutions that work
- **Idealist**: Optimal long-term solutions
- **Mediator**: Finds common ground

**Best for:** Resolving conflicting priorities, finding middle ground

### 4. Devil's Advocate

Stress-testing proposals through rigorous challenge:

```php
use ClaudeAgents\Debate\Patterns\DevilsAdvocate;

$system = DevilsAdvocate::create($client, rounds: 2);
$result = $system->debate('We should migrate to microservices');
```

**Agents:**
- **Proposer**: Advocates for the proposal
- **Devil's Advocate**: Challenges assumptions and finds flaws

**Best for:** Risk analysis, stress-testing ideas, uncovering blind spots

## Advanced Usage

### Custom Debate Agents

Create specialized agents for your domain:

```php
$securityExpert = new DebateAgent(
    $client,
    'Security Expert',
    'security',
    'You analyze security implications. Focus on vulnerabilities, threats, and compliance.'
);

$performanceExpert = new DebateAgent(
    $client,
    'Performance Expert',
    'performance',
    'You assess performance impact. Focus on scalability, latency, and resource usage.'
);

$system = DebateSystem::create($client)
    ->addAgent('security', $securityExpert)
    ->addAgent('performance', $performanceExpert)
    ->rounds(2);
```

### Configuring Rounds

Control the debate depth:

```php
// Quick exploration
$system->rounds(1);

// Standard debate
$system->rounds(2);

// Deep analysis
$system->rounds(4);
```

**Note:** More rounds provide deeper analysis but increase API costs and time.

### Accessing Debate Details

```php
$result = $system->debate('Should we use TypeScript?');

// Get round-by-round analysis
foreach ($result->getRounds() as $round) {
    echo "Round " . $round->getRoundNumber() . ":\n";
    
    foreach ($round->getStatements() as $agentName => $statement) {
        echo "  {$agentName}: {$statement}\n";
    }
}

// Get full transcript
$transcript = $result->getTranscript();

// Convert to array for storage/processing
$data = $result->toArray();
```

## Result Analysis

### Synthesis

The moderator provides a balanced conclusion:

```php
$synthesis = $result->getSynthesis();
```

The synthesis includes:
1. **Key areas of agreement**: Points where agents aligned
2. **Valid concerns from all sides**: Important considerations
3. **Recommended decision with rationale**: Balanced recommendation
4. **Potential risks and mitigations**: Risk awareness

### Agreement Score

Quantitative measure of consensus (0.0 to 1.0):

```php
$score = $result->getAgreementScore();

if ($score > 0.7) {
    echo "Strong consensus\n";
} elseif ($score > 0.5) {
    echo "Moderate agreement\n";
} else {
    echo "Significant disagreement\n";
}
```

## Use Cases

### 1. Technical Decision Making

```php
$system = RoundTableDebate::create($client, rounds: 2);
$result = $system->debate('Should we use GraphQL or REST for our API?');
```

### 2. Feature Prioritization

```php
$system = ConsensusBuilder::create($client, rounds: 2);
$result = $system->debate('Should we prioritize mobile app or web dashboard?');
```

### 3. Risk Assessment

```php
$system = DevilsAdvocate::create($client, rounds: 3);
$result = $system->debate('We should remove this legacy authentication system');
```

### 4. Architecture Review

```php
$architect = new DebateAgent(
    $client, 'Architect', 'design',
    'You evaluate architecture. Focus on modularity, maintainability, and scalability.'
);

$implementer = new DebateAgent(
    $client, 'Implementer', 'practical',
    'You consider implementation. Focus on complexity, time, and team capability.'
);

$system = DebateSystem::create($client)
    ->addAgent('architect', $architect)
    ->addAgent('implementer', $implementer)
    ->rounds(2);

$result = $system->debate('Should we adopt event-driven architecture?');
```

## Configuration Options

### DebateAgent Options

```php
$agent = new DebateAgent(
    client: $client,
    name: 'Expert Name',
    perspective: 'perspective-id',
    systemPrompt: 'Your role and behavior description',
    options: [
        'logger' => $logger,  // PSR-3 logger
    ]
);
```

### DebateSystem Options

```php
$system = new DebateSystem(
    client: $client,
    options: [
        'logger' => $logger,  // PSR-3 logger
    ]
);
```

## Best Practices

### 1. Define Clear Perspectives

```php
// Good: Specific, actionable perspective
$agent = new DebateAgent(
    $client, 'Security Analyst', 'security',
    'You analyze security risks. Focus on data protection, access control, and compliance.'
);

// Avoid: Vague perspective
$agent = new DebateAgent(
    $client, 'Expert', 'expert',
    'You are an expert. Give your opinion.'
);
```

### 2. Choose Appropriate Patterns

- **Pro/Con**: Simple yes/no decisions
- **Round Table**: Multi-faceted decisions
- **Consensus Builder**: Conflicting requirements
- **Devil's Advocate**: Risk analysis

### 3. Balance Round Count

```php
// Quick decisions (1-2 rounds)
$system->rounds(1);

// Standard analysis (2-3 rounds)
$system->rounds(2);

// Deep exploration (3-5 rounds)
$system->rounds(4);
```

### 4. Process Results Systematically

```php
$result = $system->debate($topic);

// 1. Review synthesis
$synthesis = $result->getSynthesis();

// 2. Check agreement level
$agreement = $result->getAgreementScore();

// 3. Examine individual perspectives
foreach ($result->getRounds() as $round) {
    // Analyze each round
}

// 4. Make informed decision
if ($agreement > 0.7 && satisfiesRequirements($synthesis)) {
    proceedWithDecision();
}
```

## Error Handling

```php
try {
    $result = $system->debate($topic);
} catch (\InvalidArgumentException $e) {
    // No agents added
    echo "Configuration error: " . $e->getMessage();
} catch (\RuntimeException $e) {
    // API error
    echo "API error: " . $e->getMessage();
}
```

## Performance Considerations

### Token Usage

- Each agent statement uses tokens
- Moderator synthesis uses additional tokens
- More rounds = more token usage

**Estimate**: (agents × rounds × ~500 tokens) + (moderator × ~1000 tokens)

### Optimization Tips

1. **Start with fewer rounds** for exploration
2. **Use focused system prompts** to reduce verbosity
3. **Consider caching** debate results for similar topics
4. **Limit agent count** to necessary perspectives (3-5 agents optimal)

## Logging

Enable detailed logging for debugging:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('debate');
$logger->pushHandler(new StreamHandler('debate.log', Logger::DEBUG));

$system = new DebateSystem($client, ['logger' => $logger]);
```

## Testing

See the test suite for comprehensive examples:

- `tests/Unit/Debate/DebateAgentTest.php`
- `tests/Unit/Debate/DebateSystemTest.php`
- `tests/Integration/DebateIntegrationTest.php`

## Examples

Complete working examples are available in:

- `examples/debate_example.php` - Multiple debate patterns demonstrated

## Related Components

- **ChainOfThoughtAgent**: For single-agent reasoning
- **CoordinatorAgent**: For task delegation
- **RAGAgent**: For knowledge-based debates

## API Reference

### DebateSystem

```php
// Create
DebateSystem::create(ClaudePhp $client, array $options = []): self

// Configure
->addAgent(string $id, DebateAgent $agent): self
->addAgents(array $agents): self
->rounds(int $count): self

// Execute
->debate(string $topic): DebateResult

// Access
->getModerator(): DebateModerator
->getAgents(): array
->getAgentCount(): int
```

### DebateResult

```php
->getTopic(): string
->getRounds(): array
->getSynthesis(): string
->getAgreementScore(): float
->getTotalTokens(): int
->getRoundCount(): int
->getTranscript(): string
->toArray(): array
```

### DebateAgent

```php
->getName(): string
->getPerspective(): string
->getSystemPrompt(): string
->speak(string $topic, string $context = '', string $instruction = ''): string
```

## Contributing

Contributions are welcome! Please see CONTRIBUTING.md for guidelines.

## License

This component is part of the claude-php-agent package and shares its license.

