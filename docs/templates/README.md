# Template System Guide

The Claude PHP Agent Template System provides a powerful way to discover, instantiate, and share pre-configured agent setups. With 22+ ready-to-use templates, you can quickly get started with any agent pattern in the framework.

## Table of Contents

- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Template Structure](#template-structure)
- [Searching Templates](#searching-templates)
- [Instantiating Agents](#instantiating-agents)
- [Creating Custom Templates](#creating-custom-templates)
- [Template Categories](#template-categories)
- [API Reference](#api-reference)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Quick Start

### Installation

Templates are included with the framework. No additional installation required.

```bash
composer require claude-php/agent
```

### Basic Usage

```php
use ClaudeAgents\Templates\TemplateManager;

// Initialize manager
$manager = TemplateManager::getInstance();

// Search for templates
$templates = $manager->search(query: 'chatbot');

// Instantiate an agent
$agent = $manager->instantiate('dialog-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// Run the agent
$result = $agent->run('Hello! How can you help me?');
echo $result->getAnswer();
```

## Core Concepts

### Templates

A template is a JSON or PHP file that contains:
- Agent configuration (type, model, parameters)
- Metadata (name, description, tags, difficulty)
- Requirements (PHP version, extensions, packages)
- Use cases and documentation

### Template Manager

The central hub for template operations:
- **Search**: Find templates by name, tags, or category
- **Instantiate**: Create live agents from templates
- **Export**: Save agent configurations as templates
- **Browse**: Explore catalog by category

### Template Categories

Templates are organized into categories:
- **agents**: Basic and foundational agents
- **chatbots**: Conversational agents
- **rag**: Retrieval-augmented generation
- **workflows**: Multi-step and multi-agent systems
- **specialized**: Domain-specific agents
- **production**: Enterprise-ready configurations

## Template Structure

### JSON Format

```json
{
  "id": "unique-identifier",
  "name": "Template Name",
  "description": "Detailed description of what this template does",
  "category": "agents",
  "tags": ["tag1", "tag2", "tag3"],
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
    "difficulty": "beginner",
    "estimated_setup": "5 minutes",
    "use_cases": [
      "Use case 1",
      "Use case 2"
    ]
  },
  "config": {
    "agent_type": "ReactAgent",
    "model": "claude-sonnet-4-5",
    "max_iterations": 10,
    "system_prompt": "Your system prompt here",
    "temperature": 0.7,
    "max_tokens": 4096
  }
}
```

### Required Fields

- `name`: Template name
- `description`: What the template does
- `category`: One of the valid categories
- `config.agent_type`: Agent class name

### Optional But Recommended

- `tags`: For searchability
- `metadata.difficulty`: beginner, intermediate, or advanced
- `metadata.use_cases`: Help users understand when to use it
- `metadata.icon`: Visual identifier

## Searching Templates

### Simple Search

```php
// Search by query (searches name and description)
$templates = $manager->search(query: 'conversation');

// Search by category
$chatbots = $manager->search(category: 'chatbots');

// Search by tags
$beginnerTemplates = $manager->search(tags: ['beginner']);
```

### Advanced Search

```php
// Combined search
$results = $manager->search(
    query: 'agent',
    category: 'specialized',
    tags: ['advanced', 'monitoring']
);

// With field selection (returns arrays instead of Template objects)
$results = $manager->search(
    query: 'chatbot',
    fields: ['name', 'description', 'difficulty']
);
```

### Browse Catalog

```php
// Get all categories
$categories = $manager->getCategories();

// Get all tags
$tags = $manager->getAllTags();

// Get templates by category
$agentTemplates = $manager->getByCategory('agents');

// Get templates by tags
$conversational = $manager->getByTags(['conversation', 'memory']);

// Get total count
$count = $manager->count();
```

## Instantiating Agents

### Basic Instantiation

```php
// By template name
$agent = $manager->instantiate('react-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// By template ID
$agent = $manager->instantiate('react-001', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// From template object
$template = $manager->getByName('Dialog Agent');
$agent = $manager->instantiateFromTemplate($template, [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);
```

### Configuration Overrides

Override any template configuration:

```php
$agent = $manager->instantiate('dialog-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'model' => 'claude-opus-4',              // Override model
    'max_iterations' => 15,                  // Override iterations
    'system_prompt' => 'Custom prompt',      // Override system prompt
    'temperature' => 0.8,                    // Override temperature
    'tools' => [$myCustomTool]              // Add tools
]);
```

### With Tools

```php
use ClaudeAgents\Tools\Tool;

$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->parameter('expression', 'string', 'Math expression')
    ->required('expression')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

$agent = $manager->instantiate('react-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$calculator]
]);
```

### With Client Override

```php
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(
    apiKey: getenv('ANTHROPIC_API_KEY'),
    timeout: 60.0
);

$agent = $manager->instantiate('production-agent', [
    'client' => $client
]);
```

## Creating Custom Templates

### Export Existing Agent

```php
use ClaudeAgents\Agent;

// Create your agent
$agent = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a helpful assistant')
    ->withTool($myTool);

// Export as template
$template = $manager->exportAgent($agent, [
    'name' => 'My Custom Agent',
    'description' => 'A specialized agent for my use case',
    'category' => 'custom',
    'tags' => ['custom', 'specialized'],
    'author' => 'Your Name',
    'metadata' => [
        'icon' => '🎯',
        'difficulty' => 'intermediate',
        'estimated_setup' => '15 minutes',
        'use_cases' => [
            'Use case 1',
            'Use case 2'
        ]
    ]
]);

// Save to file
$manager->saveTemplate($template, 'custom/my-agent.json');
```

### Create from Scratch

```php
use ClaudeAgents\Templates\Template;

$template = Template::fromArray([
    'name' => 'Custom Research Agent',
    'description' => 'Specialized for academic research',
    'category' => 'custom',
    'tags' => ['research', 'academic'],
    'version' => '1.0.0',
    'author' => 'Research Team',
    'requirements' => [
        'php' => '>=8.1',
        'packages' => ['claude-php/agent']
    ],
    'metadata' => [
        'icon' => '🔬',
        'difficulty' => 'intermediate',
        'use_cases' => ['Literature review', 'Research synthesis']
    ],
    'config' => [
        'agent_type' => 'ReactAgent',
        'model' => 'claude-sonnet-4-5',
        'max_iterations' => 15,
        'system_prompt' => 'You are a research assistant...'
    ]
]);

// Validate
if ($template->isValid()) {
    $manager->saveTemplate($template, 'custom/research-agent.json');
}
```

## Template Categories

### Agents (5 templates)

Basic and foundational agent patterns:

- **Basic Agent**: Simple agent with one tool - perfect for learning
- **ReAct Agent**: Reason-Act-Observe pattern for autonomous tasks
- **Chain-of-Thought Agent**: Step-by-step reasoning for complex problems
- **Reflex Agent**: Rule-based responses for fast, deterministic reactions
- **Model-Based Agent**: State-aware decision making with planning

### Chatbots (2 templates)

Conversational agents with context:

- **Dialog Agent**: Multi-turn conversations with context tracking
- **Memory Chatbot**: Persistent memory across sessions

### RAG (3 templates)

Retrieval-augmented generation:

- **RAG Agent**: Document retrieval and question answering
- **Memory Chatbot**: Conversation memory and context
- **Knowledge Manager**: Knowledge base management

### Workflows (2 templates)

Multi-step and multi-agent systems:

- **Sequential Tasks Agent**: Pipeline processing
- **Debate System**: Multi-agent consensus building

### Specialized (5 templates)

Domain-specific agents:

- **Hierarchical Agent**: Master-worker pattern
- **Coordinator Agent**: Multi-agent orchestration
- **Dialog Agent**: Conversational AI
- **Intent Classifier**: Intent recognition and routing
- **Monitoring Agent**: System monitoring and alerts

### Production (2 templates)

Enterprise-ready configurations:

- **Production Agent**: Full error handling, logging, monitoring
- **Async Batch Processor**: Concurrent task processing

## API Reference

### TemplateManager

#### getInstance(?string $templatesPath = null): self

Get singleton instance.

#### search(?string $query, ?array $tags, ?string $category, ?array $fields): array

Search templates with optional filters and field selection.

#### getById(string $id): Template

Get template by ID. Throws `TemplateNotFoundException` if not found.

#### getByName(string $name): Template

Get template by name. Returns first match.

#### getByCategory(string $category): Template[]

Get all templates in a category.

#### getByTags(array $tags): Template[]

Get templates with ANY of the specified tags.

#### instantiate(string $identifier, array $config = []): Agent

Instantiate agent from template by ID or name.

#### exportAgent(Agent $agent, array $metadata = []): Template

Export an agent as a template.

#### saveTemplate(Template $template, string $path): bool

Save template to file (.json or .php).

### Template

#### fromArray(array $data): self

Create template from array.

#### fromJson(string $json): self

Create template from JSON string.

#### isValid(): bool

Validate template structure.

#### getErrors(): array

Get validation errors.

#### toArray(): array

Convert to array.

#### toJson(int $options = JSON_PRETTY_PRINT): string

Convert to JSON.

#### toPhp(): string

Convert to PHP array export.

## Best Practices

### Template Selection

1. **Start with difficulty level**
   - Beginners: Use beginner templates
   - Experienced: Try intermediate/advanced

2. **Match use case**
   - Check template metadata use_cases
   - Read template descriptions

3. **Check requirements**
   - Verify PHP version compatibility
   - Ensure required extensions are installed

### Configuration

1. **Use environment variables for secrets**
   ```php
   'api_key' => getenv('ANTHROPIC_API_KEY')
   ```

2. **Override sparingly**
   - Templates provide sensible defaults
   - Only override what you need

3. **Validate before production**
   ```php
   if ($template->isValid()) {
       // Use template
   }
   ```

### Custom Templates

1. **Follow naming conventions**
   - Use clear, descriptive names
   - Include agent type in name

2. **Document thoroughly**
   - Detailed description
   - List use cases
   - Specify difficulty

3. **Tag appropriately**
   - Use existing tags when possible
   - Add domain-specific tags

4. **Test before sharing**
   - Validate template
   - Test instantiation
   - Run with sample tasks

## Troubleshooting

### Template Not Found

```
TemplateNotFoundException: Template with name 'XYZ' not found
```

**Solution**: Check spelling, use `search()` to find available templates.

### Invalid Template

```
TemplateValidationException: Template validation failed
```

**Solution**: Check `getErrors()` for specific validation issues.

### Instantiation Failed

```
TemplateInstantiationException: Failed to instantiate agent
```

**Solutions**:
- Verify ANTHROPIC_API_KEY is set
- Check agent type exists
- Verify configuration is valid

### Missing Agent Type

```
TemplateInstantiationException: Agent type 'XYZ' not found
```

**Solution**: Verify agent class exists and is imported.

## Examples

See the [examples/templates](../../examples/templates/) directory for comprehensive examples:

- `01-basic-usage.php` - Loading and listing templates
- `02-search-filter.php` - Advanced search
- `03-instantiate.php` - Creating agents from templates
- `04-custom-template.php` - Exporting and creating templates
- `05-categories-tags.php` - Browsing by category/tag
- `06-template-metadata.php` - Working with metadata
- `07-production-patterns.php` - Production best practices

## See Also

- [Template Catalog](TEMPLATE_CATALOG.md) - All templates documented
- [Creating Templates](CREATING_TEMPLATES.md) - Template creation guide
- [Templates Tutorial](../tutorials/Templates_Tutorial.md) - Step-by-step tutorial
