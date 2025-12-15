# DebateSystem Tutorial: Building Multi-Agent Debates

## Introduction

Welcome to the DebateSystem tutorial! In this guide, you'll learn how to create AI-powered debates where multiple agents with different perspectives discuss complex topics, helping you make better decisions and explore ideas from multiple angles.

**What You'll Learn:**
- How multi-agent debates work
- Setting up debate systems with different patterns
- Creating custom debate agents
- Analyzing debate results
- Practical applications for decision-making

**Prerequisites:**
- PHP 8.1 or higher
- Composer installed
- An Anthropic API key
- Basic understanding of PHP

**Time to Complete:** 45-60 minutes

## Part 1: Understanding Multi-Agent Debates

### What is a Multi-Agent Debate?

A multi-agent debate is a structured conversation where multiple AI agents with distinct perspectives discuss a topic. Each agent advocates for its viewpoint, building on previous statements to create a comprehensive analysis.

### Why Use Debates?

Debates help you:
- **Explore multiple perspectives** on complex decisions
- **Identify risks and opportunities** from different angles
- **Reduce blind spots** in your thinking
- **Build consensus** or understand disagreements
- **Make better decisions** with comprehensive analysis

### How DebateSystem Works

```
Topic → Round 1 (all agents speak) → Round 2 (agents respond) → Synthesis
  ↓         ↓                            ↓                          ↓
"Should   Agent A: "Yes because..."   Agent A: "To add..."    Moderator
we use    Agent B: "No because..."    Agent B: "However..."   analyzes &
Feature   [Context built]              [Context grows]         synthesizes
X?"
```

## Part 2: Your First Debate

### Step 1: Installation

Ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

### Step 2: Basic Pro/Con Debate

Create a new file `my_first_debate.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Debate\Patterns\ProConDebate;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a Pro/Con debate
$debate = ProConDebate::create(
    $client, 
    'Should we adopt a 4-day work week?',
    rounds: 2
);

// Run the debate
$result = $debate->debate('Should we adopt a 4-day work week?');

// Display results
echo "=== Debate Results ===\n\n";
echo "Topic: " . $result->getTopic() . "\n\n";
echo "Synthesis:\n" . $result->getSynthesis() . "\n\n";
echo "Agreement Score: " . round($result->getAgreementScore() * 100) . "%\n";
```

Run it:

```bash
export ANTHROPIC_API_KEY='your-key-here'
php my_first_debate.php
```

**Expected Output:**
- A balanced synthesis considering both pro and con perspectives
- An agreement score showing consensus level
- Insights from both advocates and critics

### Step 3: Understanding the Result

```php
// Get detailed information
$rounds = $result->getRounds();

echo "\n=== Round-by-Round Analysis ===\n";
foreach ($rounds as $round) {
    echo "\nRound " . $round->getRoundNumber() . ":\n";
    
    foreach ($round->getStatements() as $agentName => $statement) {
        echo "\n{$agentName}:\n";
        echo wordwrap($statement, 80) . "\n";
    }
}

// Get full transcript
$transcript = $result->getTranscript();
file_put_contents('debate_transcript.txt', $transcript);
echo "\nFull transcript saved to debate_transcript.txt\n";
```

## Part 3: Built-in Debate Patterns

### Pattern 1: Pro/Con Debate

**Best for:** Binary decisions, proposals requiring scrutiny

```php
use ClaudeAgents\Debate\Patterns\ProConDebate;

$debate = ProConDebate::create($client, 'Should we migrate to Kubernetes?', rounds: 3);
$result = $debate->debate('Should we migrate to Kubernetes?');
```

**Agents:**
- **Proponent**: Advocates for benefits and opportunities
- **Opponent**: Identifies risks and drawbacks

### Pattern 2: Round Table Debate

**Best for:** Complex decisions requiring multiple viewpoints

```php
use ClaudeAgents\Debate\Patterns\RoundTableDebate;

$debate = RoundTableDebate::create($client, rounds: 2);
$result = $debate->debate('What technology should we use for our mobile app?');
```

**Agents:**
- **User Advocate**: Focuses on user needs and experience
- **Engineer**: Assesses technical feasibility
- **Business Analyst**: Evaluates ROI and strategic value
- **Designer**: Considers UX and design consistency

### Pattern 3: Consensus Builder

**Best for:** Finding common ground between conflicting priorities

```php
use ClaudeAgents\Debate\Patterns\ConsensusBuilder;

$debate = ConsensusBuilder::create($client, rounds: 3);
$result = $debate->debate('Should we prioritize speed or quality?');
```

**Agents:**
- **Pragmatist**: Practical solutions that work
- **Idealist**: Optimal long-term solutions
- **Mediator**: Finds common ground and builds agreement

### Pattern 4: Devil's Advocate

**Best for:** Stress-testing ideas and identifying risks

```php
use ClaudeAgents\Debate\Patterns\DevilsAdvocate;

$debate = DevilsAdvocate::create($client, rounds: 2);
$result = $debate->debate('We should remove password authentication and use only OAuth');
```

**Agents:**
- **Proposer**: Defends the proposal
- **Devil's Advocate**: Challenges assumptions and finds flaws

## Part 4: Creating Custom Debates

### Custom Agents for Your Domain

Let's create a debate about database choices with domain-specific agents:

```php
use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create specialized agents
$dba = new DebateAgent(
    client: $client,
    name: 'Database Administrator',
    perspective: 'operations',
    systemPrompt: 'You are a database administrator focused on reliability, ' .
                  'performance, and operational complexity. Consider backup, ' .
                  'replication, monitoring, and maintenance requirements.'
);

$developer = new DebateAgent(
    client: $client,
    name: 'Application Developer',
    perspective: 'development',
    systemPrompt: 'You are an application developer focused on productivity, ' .
                  'developer experience, and integration ease. Consider APIs, ' .
                  'ORMs, tooling, and learning curve.'
);

$architect = new DebateAgent(
    client: $client,
    name: 'Solutions Architect',
    perspective: 'architecture',
    systemPrompt: 'You are a solutions architect focused on scalability, ' .
                  'flexibility, and long-term maintainability. Consider ' .
                  'system evolution, technology trends, and strategic fit.'
);

// Build the debate system
$debate = DebateSystem::create($client)
    ->addAgent('dba', $dba)
    ->addAgent('developer', $developer)
    ->addAgent('architect', $architect)
    ->rounds(2);

// Run the debate
$result = $debate->debate('Should we use PostgreSQL or MongoDB for our project?');

echo "Topic: " . $result->getTopic() . "\n\n";
echo "Synthesis:\n" . $result->getSynthesis() . "\n";
```

### Adding a Logger

Track debate progress with logging:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('debate');
$logger->pushHandler(new StreamHandler('debate.log', Logger::DEBUG));

$debate = DebateSystem::create($client, ['logger' => $logger])
    ->addAgent('agent1', $agent1)
    ->addAgent('agent2', $agent2)
    ->rounds(2);
```

## Part 5: Analyzing Debate Results

### Understanding Agreement Scores

```php
$score = $result->getAgreementScore();

if ($score > 0.8) {
    $consensus = "Strong consensus - proceed with confidence";
} elseif ($score > 0.6) {
    $consensus = "Good agreement - minor concerns to address";
} elseif ($score > 0.4) {
    $consensus = "Mixed views - careful consideration needed";
} else {
    $consensus = "Significant disagreement - more research required";
}

echo "Agreement Level: " . round($score * 100) . "%\n";
echo "Recommendation: {$consensus}\n";
```

### Extracting Key Insights

```php
$synthesis = $result->getSynthesis();

// Look for specific sections in the synthesis
$sections = [
    'agreement' => extractSection($synthesis, 'Key areas of agreement'),
    'concerns' => extractSection($synthesis, 'Valid concerns'),
    'decision' => extractSection($synthesis, 'Recommended decision'),
    'risks' => extractSection($synthesis, 'Potential risks'),
];

function extractSection($text, $heading) {
    // Simple extraction (you can make this more sophisticated)
    $pattern = '/' . preg_quote($heading, '/') . '.*?(?=\n[0-9]\.|\z)/s';
    if (preg_match($pattern, $text, $matches)) {
        return trim($matches[0]);
    }
    return '';
}
```

### Converting to Structured Data

```php
$data = $result->toArray();

// Save to JSON
file_put_contents(
    'debate_result.json',
    json_encode($data, JSON_PRETTY_PRINT)
);

// Store in database
$db->debates->insert([
    'topic' => $data['topic'],
    'synthesis' => $data['synthesis'],
    'agreement_score' => $data['agreement_score'],
    'round_count' => $data['round_count'],
    'rounds' => $data['rounds'],
    'created_at' => date('Y-m-d H:i:s'),
]);
```

## Part 6: Practical Applications

### Application 1: Technical Decision Matrix

```php
class TechnicalDebate
{
    private DebateSystem $debate;
    
    public function __construct(ClaudePhp $client)
    {
        $this->debate = RoundTableDebate::create($client, rounds: 2);
    }
    
    public function evaluateOption(string $option, array $criteria): array
    {
        $topic = "Should we choose {$option}? Consider: " . 
                 implode(', ', $criteria);
        
        $result = $this->debate->debate($topic);
        
        return [
            'option' => $option,
            'score' => $result->getAgreementScore(),
            'synthesis' => $result->getSynthesis(),
            'recommendation' => $this->getRecommendation($result),
        ];
    }
    
    private function getRecommendation($result): string
    {
        $score = $result->getAgreementScore();
        return $score > 0.7 ? 'Recommended' : 
               ($score > 0.5 ? 'Consider with caution' : 'Not recommended');
    }
}

// Usage
$debate = new TechnicalDebate($client);

$options = ['React', 'Vue', 'Svelte'];
$criteria = ['performance', 'developer experience', 'ecosystem', 'learning curve'];

$results = [];
foreach ($options as $option) {
    $results[] = $debate->evaluateOption($option, $criteria);
}

// Sort by score
usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

echo "Top choice: {$results[0]['option']} (score: " . 
     round($results[0]['score'] * 100) . "%)\n";
```

### Application 2: Risk Assessment

```php
function assessRisk(ClaudePhp $client, string $proposal): array
{
    // Use Devil's Advocate pattern for thorough risk analysis
    $debate = DevilsAdvocate::create($client, rounds: 3);
    $result = $debate->debate($proposal);
    
    // Low agreement = high risk
    $riskLevel = 1 - $result->getAgreementScore();
    
    return [
        'proposal' => $proposal,
        'risk_score' => $riskLevel,
        'risk_level' => $riskLevel > 0.6 ? 'High' : 
                       ($riskLevel > 0.3 ? 'Medium' : 'Low'),
        'analysis' => $result->getSynthesis(),
        'proceed' => $riskLevel < 0.5,
    ];
}

// Usage
$proposals = [
    'Remove all unit tests to speed up development',
    'Migrate authentication to OAuth 2.0',
    'Adopt microservices architecture',
];

foreach ($proposals as $proposal) {
    $risk = assessRisk($client, $proposal);
    
    echo "Proposal: {$risk['proposal']}\n";
    echo "Risk Level: {$risk['risk_level']}\n";
    echo "Proceed: " . ($risk['proceed'] ? 'Yes' : 'No') . "\n\n";
}
```

### Application 3: Feature Prioritization

```php
class FeaturePrioritizer
{
    private DebateSystem $debate;
    
    public function __construct(ClaudePhp $client)
    {
        $this->debate = ConsensusBuilder::create($client, rounds: 2);
    }
    
    public function prioritize(array $features): array
    {
        $results = [];
        
        foreach ($features as $feature) {
            $topic = "Should we prioritize: {$feature['name']}? " .
                    "Description: {$feature['description']}";
            
            $result = $this->debate->debate($topic);
            
            $results[] = [
                'feature' => $feature,
                'priority_score' => $result->getAgreementScore(),
                'analysis' => $result->getSynthesis(),
            ];
        }
        
        // Sort by priority
        usort($results, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);
        
        return $results;
    }
}

// Usage
$features = [
    [
        'name' => 'Dark Mode',
        'description' => 'Add dark theme support to the application',
    ],
    [
        'name' => 'Export to PDF',
        'description' => 'Allow users to export reports as PDF',
    ],
    [
        'name' => 'Two-Factor Authentication',
        'description' => 'Add 2FA for enhanced security',
    ],
];

$prioritizer = new FeaturePrioritizer($client);
$prioritized = $prioritizer->prioritize($features);

echo "=== Feature Priority List ===\n";
foreach ($prioritized as $index => $item) {
    echo ($index + 1) . ". {$item['feature']['name']} ";
    echo "(" . round($item['priority_score'] * 100) . "%)\n";
}
```

## Part 7: Best Practices

### 1. Choose the Right Pattern

```php
// Binary decisions → Pro/Con
$debate = ProConDebate::create($client, 'Should we...?');

// Multi-faceted analysis → Round Table
$debate = RoundTableDebate::create($client);

// Conflicting priorities → Consensus Builder
$debate = ConsensusBuilder::create($client);

// Risk assessment → Devil's Advocate
$debate = DevilsAdvocate::create($client);
```

### 2. Optimize Round Count

```php
// Quick exploration (cheap, fast)
$debate->rounds(1);

// Standard analysis (balanced)
$debate->rounds(2);

// Deep dive (thorough, expensive)
$debate->rounds(4);
```

### 3. Write Effective System Prompts

```php
// ✅ Good: Specific, actionable
$agent = new DebateAgent(
    $client,
    'Security Expert',
    'security',
    'You analyze security implications. Focus on authentication, authorization, ' .
    'data protection, and compliance. Reference OWASP guidelines and industry standards.'
);

// ❌ Avoid: Vague, generic
$agent = new DebateAgent(
    $client,
    'Expert',
    'expert',
    'You are an expert. Give your opinion.'
);
```

### 4. Handle Results Systematically

```php
function makeDecision(DebateResult $result): array
{
    $score = $result->getAgreementScore();
    $synthesis = $result->getSynthesis();
    
    return [
        'proceed' => $score > 0.6,
        'confidence' => $score > 0.7 ? 'high' : ($score > 0.5 ? 'medium' : 'low'),
        'action_items' => extractActionItems($synthesis),
        'risks' => extractRisks($synthesis),
        'rationale' => $synthesis,
    ];
}
```

## Part 8: Troubleshooting

### Issue: Low-Quality Synthesis

**Problem:** Synthesis is too generic or doesn't address the topic

**Solutions:**
```php
// 1. Add more specific context to the topic
$result = $debate->debate(
    'Should we use Redis for caching? Context: We have 1M daily users, ' .
    '50ms p95 latency requirement, and limited ops team.'
);

// 2. Increase rounds for deeper analysis
$debate->rounds(3);

// 3. Use more specific agent prompts
```

### Issue: High API Costs

**Problem:** Debates are consuming too many tokens

**Solutions:**
```php
// 1. Reduce rounds
$debate->rounds(1);

// 2. Limit agent count
// Use 2-3 agents instead of 4-5

// 3. Cache results for similar topics
$cache = new DebateCache();
$result = $cache->remember($topic, fn() => $debate->debate($topic));
```

### Issue: Agents Agreeing Too Much

**Problem:** Not enough critical analysis

**Solutions:**
```php
// Use Devil's Advocate pattern
$debate = DevilsAdvocate::create($client);

// Or create more oppositional agents
$critic = new DebateAgent(
    $client, 'Critic', 'critical',
    'You are highly skeptical. Challenge every assumption and find flaws.'
);
```

## Part 9: Advanced Techniques

### Chaining Debates

```php
// First debate: Choose technology
$techDebate = RoundTableDebate::create($client);
$techResult = $techDebate->debate('What framework should we use?');

// Extract decision
preg_match('/recommend (\w+)/i', $techResult->getSynthesis(), $matches);
$chosenTech = $matches[1] ?? 'React';

// Second debate: Implementation approach
$implDebate = ConsensusBuilder::create($client);
$implResult = $implDebate->debate(
    "How should we implement {$chosenTech} given: " . 
    $techResult->getSynthesis()
);
```

### Debate Metrics

```php
class DebateMetrics
{
    public function analyze(DebateResult $result): array
    {
        return [
            'topic' => $result->getTopic(),
            'rounds' => $result->getRoundCount(),
            'agreement_score' => $result->getAgreementScore(),
            'total_words' => str_word_count($result->getTranscript()),
            'synthesis_length' => strlen($result->getSynthesis()),
            'tokens_used' => $result->getTotalTokens(),
        ];
    }
}
```

## Part 10: Complete Example

Here's a complete example that ties everything together:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\Patterns\RoundTableDebate;
use ClaudePhp\ClaudePhp;

class DecisionSupport
{
    private ClaudePhp $client;
    
    public function __construct(string $apiKey)
    {
        $this->client = new ClaudePhp(apiKey: $apiKey);
    }
    
    public function evaluateDecision(
        string $topic,
        array $context = [],
        int $rounds = 2
    ): array {
        // Build context string
        $contextStr = empty($context) ? '' : 
            ' Context: ' . implode(', ', $context);
        
        // Create debate
        $debate = RoundTableDebate::create($this->client, rounds: $rounds);
        $result = $debate->debate($topic . $contextStr);
        
        // Analyze
        $score = $result->getAgreementScore();
        
        return [
            'topic' => $topic,
            'recommendation' => $this->getRecommendation($score),
            'confidence' => $this->getConfidence($score),
            'agreement_score' => $score,
            'synthesis' => $result->getSynthesis(),
            'full_result' => $result,
        ];
    }
    
    private function getRecommendation(float $score): string
    {
        if ($score > 0.75) return 'Strongly recommend';
        if ($score > 0.6) return 'Recommend with minor reservations';
        if ($score > 0.4) return 'Neutral - more analysis needed';
        return 'Not recommended';
    }
    
    private function getConfidence(float $score): string
    {
        if ($score > 0.8 || $score < 0.2) return 'High';
        if ($score > 0.6 || $score < 0.4) return 'Medium';
        return 'Low';
    }
}

// Usage
$decisionSupport = new DecisionSupport(getenv('ANTHROPIC_API_KEY'));

$decision = $decisionSupport->evaluateDecision(
    topic: 'Should we implement GraphQL instead of REST?',
    context: [
        'Mobile app with complex nested data',
        'Team has no GraphQL experience',
        'Timeline: 3 months',
    ],
    rounds: 2
);

echo "=== Decision Analysis ===\n\n";
echo "Topic: {$decision['topic']}\n\n";
echo "Recommendation: {$decision['recommendation']}\n";
echo "Confidence: {$decision['confidence']}\n";
echo "Agreement Score: " . round($decision['agreement_score'] * 100) . "%\n\n";
echo "Synthesis:\n{$decision['synthesis']}\n";
```

## Conclusion

You've learned how to:
- ✅ Set up and run multi-agent debates
- ✅ Use built-in debate patterns
- ✅ Create custom agents for your domain
- ✅ Analyze and interpret results
- ✅ Apply debates to real-world decisions

### Next Steps

1. **Experiment** with different patterns and agent configurations
2. **Integrate** debates into your decision-making workflow
3. **Customize** agents for your specific domain
4. **Build** decision support tools using debates

### Additional Resources

- **Documentation**: `docs/DebateSystem.md`
- **Examples**: `examples/debate_example.php`
- **Tests**: `tests/Unit/Debate/` and `tests/Integration/DebateIntegrationTest.php`
- **API Reference**: See DebateSystem.md

### Community

Have questions or built something cool? Share it with the community!

Happy debating! 🎯

