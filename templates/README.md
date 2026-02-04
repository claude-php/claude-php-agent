# Agent Templates Catalog

This directory contains 22 ready-to-use agent templates covering all major agent patterns in the Claude PHP Agent framework.

## Quick Start

```php
use ClaudeAgents\Templates\TemplateManager;

// Search templates
$templates = TemplateManager::search(query: 'chatbot');

// Instantiate from template
$agent = TemplateManager::instantiate('rag-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// Run the agent
$result = $agent->run('Your task here');
```

## Template Categories

### Basic Agents (5 templates)
Perfect for learning and simple tasks:

- **basic-agent.json** - Simple agent with one tool
- **react-agent.json** - Reason-Act-Observe pattern
- **chain-of-thought-agent.json** - Step-by-step reasoning
- **reflex-agent.json** - Rule-based responses
- **model-based-agent.json** - State-aware decisions

### Advanced Agents (5 templates)
For complex reasoning and optimization:

- **reflection-agent.json** - Self-improvement loop
- **plan-execute-agent.json** - Multi-step planning
- **tree-of-thoughts-agent.json** - Exploration and branching
- **maker-agent.json** - Million-step reliable tasks
- **adaptive-agent.json** - Intelligent agent selection

### Specialized Agents (5 templates)
Domain-specific agents:

- **hierarchical-agent.json** - Master-worker pattern
- **coordinator-agent.json** - Multi-agent orchestration
- **dialog-agent.json** - Conversational AI
- **intent-classifier-agent.json** - Intent recognition
- **monitoring-agent.json** - System monitoring

### RAG & Knowledge (3 templates)
Information retrieval and memory:

- **rag-agent.json** - Document retrieval and QA
- **memory-chatbot.json** - Persistent conversation memory
- **knowledge-manager.json** - Knowledge management

### Workflows (2 templates)
Multi-agent and sequential processing:

- **sequential-tasks-agent.json** - Multi-step workflows
- **debate-system.json** - Multi-agent debate

### Production (2 templates)
Enterprise-ready configurations:

- **production-agent.json** - Full error handling and logging
- **async-batch-processor.json** - Concurrent task processing

## Template Structure

Each template includes:

```json
{
  "id": "unique-id",
  "name": "Template Name",
  "description": "Detailed description",
  "category": "agents|chatbots|rag|workflows|specialized|production",
  "tags": ["tag1", "tag2"],
  "version": "1.0.0",
  "author": "Author Name",
  "last_tested_version": "1.2.0",
  "requirements": {
    "php": ">=8.1",
    "extensions": ["json", "mbstring"],
    "packages": ["claude-php/agent"]
  },
  "metadata": {
    "icon": "🤖",
    "difficulty": "beginner|intermediate|advanced",
    "estimated_setup": "5 minutes",
    "use_cases": ["Use case 1", "Use case 2"]
  },
  "config": {
    "agent_type": "ReactAgent",
    "model": "claude-sonnet-4-5",
    "max_iterations": 10,
    "system_prompt": "System prompt here",
    ...
  }
}
```

## Usage Examples

### Search Templates

```php
// By category
$templates = TemplateManager::search(category: 'chatbots');

// By tags
$templates = TemplateManager::search(tags: ['conversation', 'memory']);

// By query
$templates = TemplateManager::search(query: 'planning');

// With field filtering
$templates = TemplateManager::search(
    query: 'agent',
    fields: ['name', 'description', 'difficulty']
);
```

### Browse Catalog

```php
// Get all categories
$categories = TemplateManager::getCategories();

// Get all tags
$tags = TemplateManager::getAllTags();

// Get templates by category
$agents = TemplateManager::getByCategory('agents');
```

### Instantiate Agents

```php
// By template name
$agent = TemplateManager::instantiate('react-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'model' => 'claude-sonnet-4-5'
]);

// With custom configuration
$agent = TemplateManager::instantiate('dialog-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'system_prompt' => 'Custom system prompt',
    'max_iterations' => 15
]);
```

### Export Custom Templates

```php
// Create your own agent
$myAgent = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('Custom agent');

// Export as template
$template = TemplateManager::exportAgent($myAgent, [
    'name' => 'My Custom Agent',
    'description' => 'Description here',
    'category' => 'custom',
    'tags' => ['custom', 'specialized']
]);

// Save to file
TemplateManager::saveTemplate($template, 'custom/my-agent.json');
```

## Documentation

- **[Template System Guide](../docs/templates/README.md)** - Complete usage guide
- **[Template Catalog](../docs/templates/TEMPLATE_CATALOG.md)** - All templates documented
- **[Creating Templates](../docs/templates/CREATING_TEMPLATES.md)** - Template creation guide
- **[Tutorial](../docs/tutorials/Templates_Tutorial.md)** - Step-by-step tutorial

## Requirements

- PHP 8.1 or higher
- claude-php/agent package
- ANTHROPIC_API_KEY environment variable

## License

MIT License - see [LICENSE](../LICENSE) for details.
