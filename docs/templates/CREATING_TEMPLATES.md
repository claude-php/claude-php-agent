# Creating Templates Guide

Learn how to create, validate, and share custom templates for the Claude PHP Agent framework.

## Table of Contents

- [Overview](#overview)
- [Template Structure](#template-structure)
- [Creating from Scratch](#creating-from-scratch)
- [Exporting Agents](#exporting-agents)
- [Validation](#validation)
- [Best Practices](#best-practices)
- [Publishing Templates](#publishing-templates)

## Overview

Templates allow you to:
- Share agent configurations with team members
- Document standard patterns
- Quick-start new projects
- Build reusable agent architectures

## Template Structure

### Minimum Required Fields

```json
{
  "name": "Template Name",
  "description": "What this template does",
  "category": "custom",
  "config": {
    "agent_type": "Agent"
  }
}
```

### Complete Template

```json
{
  "id": "custom-001",
  "name": "My Custom Agent",
  "description": "Detailed description of what this agent does and when to use it",
  "category": "custom",
  "tags": ["domain", "use-case", "difficulty"],
  "version": "1.0.0",
  "author": "Your Name or Team",
  "last_tested_version": "1.2.0",
  "requirements": {
    "php": ">=8.1",
    "extensions": ["json", "mbstring", "curl"],
    "packages": ["claude-php/agent", "other/package"]
  },
  "metadata": {
    "icon": "🎯",
    "difficulty": "intermediate",
    "estimated_setup": "15 minutes",
    "use_cases": [
      "Primary use case",
      "Secondary use case",
      "Additional use case"
    ],
    "target_audience": "Who should use this",
    "domain": "Domain area"
  },
  "config": {
    "agent_type": "ReactAgent",
    "model": "claude-sonnet-4-5",
    "max_iterations": 10,
    "system_prompt": "Detailed system prompt",
    "temperature": 0.7,
    "max_tokens": 4096
  }
}
```

## Creating from Scratch

### Method 1: Create JSON File Directly

```json
{
  "name": "Email Assistant",
  "description": "Helps draft, review, and organize emails",
  "category": "custom",
  "tags": ["email", "productivity", "assistant"],
  "version": "1.0.0",
  "metadata": {
    "icon": "📧",
    "difficulty": "beginner",
    "estimated_setup": "10 minutes",
    "use_cases": [
      "Drafting professional emails",
      "Email summarization",
      "Response suggestions"
    ]
  },
  "config": {
    "agent_type": "Agent",
    "model": "claude-sonnet-4-5",
    "max_iterations": 5,
    "system_prompt": "You are a professional email assistant. Help users write clear, professional emails.",
    "temperature": 0.6
  }
}
```

Save to `templates/custom/email-assistant.json`.

### Method 2: Use Template API

```php
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\TemplateManager;

$template = Template::fromArray([
    'name' => 'Email Assistant',
    'description' => 'Helps draft, review, and organize emails',
    'category' => 'custom',
    'tags' => ['email', 'productivity', 'assistant'],
    'version' => '1.0.0',
    'author' => 'Your Team',
    'metadata' => [
        'icon' => '📧',
        'difficulty' => 'beginner',
        'estimated_setup' => '10 minutes',
        'use_cases' => [
            'Drafting professional emails',
            'Email summarization',
            'Response suggestions'
        ]
    ],
    'config' => [
        'agent_type' => 'Agent',
        'model' => 'claude-sonnet-4-5',
        'max_iterations' => 5,
        'system_prompt' => 'You are a professional email assistant.',
        'temperature' => 0.6
    ]
]);

// Validate
if (!$template->isValid()) {
    foreach ($template->getErrors() as $error) {
        echo "Validation error: {$error}\n";
    }
    exit(1);
}

// Save
$manager = TemplateManager::getInstance();
$manager->saveTemplate($template, 'custom/email-assistant.json');
```

## Exporting Agents

### Basic Export

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Templates\TemplateManager;

// Create and configure your agent
$agent = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('Your custom system prompt')
    ->withTool($tool1)
    ->withTool($tool2);

// Export as template
$manager = TemplateManager::getInstance();
$template = $manager->exportAgent($agent, [
    'name' => 'My Agent',
    'description' => 'Description of what it does',
    'category' => 'custom',
    'tags' => ['tag1', 'tag2']
]);

// Save
$manager->saveTemplate($template, 'custom/my-agent.json');
```

### Export with Complete Metadata

```php
$template = $manager->exportAgent($agent, [
    'name' => 'Research Assistant',
    'description' => 'AI agent specialized in academic research and literature review',
    'category' => 'custom',
    'tags' => ['research', 'academic', 'literature-review'],
    'version' => '1.0.0',
    'author' => 'Research Team',
    'metadata' => [
        'icon' => '🔬',
        'difficulty' => 'intermediate',
        'estimated_setup': '20 minutes',
        'use_cases' => [
            'Literature review',
            'Citation management',
            'Research synthesis',
            'Paper summarization'
        ],
        'target_audience' => 'Researchers and academics',
        'domain' => 'Academic Research'
    ]
]);
```

## Validation

### Required Fields Validation

Templates must have:
- `name` (non-empty string)
- `description` (non-empty string)
- `category` (valid category)
- `config.agent_type` (valid agent class)

```php
$template = Template::fromArray($data);

if (!$template->isValid()) {
    $errors = $template->getErrors();
    // Handle errors
}
```

### Category Validation

Valid categories:
- `agents` - Basic and foundational agents
- `chatbots` - Conversational agents
- `rag` - Retrieval-augmented generation
- `workflows` - Multi-step systems
- `specialized` - Domain-specific agents
- `production` - Enterprise configurations
- `custom` - User-created templates

### Version Validation

Version must follow semantic versioning: `MAJOR.MINOR.PATCH`

```json
{
  "version": "1.0.0"  // ✓ Valid
  "version": "v1.0"   // ✗ Invalid
  "version": "1"      // ✗ Invalid
}
```

### Agent Type Validation

Agent type must be a valid, instantiable agent class:

Valid types:
- `Agent`
- `ReactAgent`
- `ReflectionAgent`
- `HierarchicalAgent`
- `DialogAgent`
- `ChainOfThoughtAgent`
- And others...

```php
$instantiator = new TemplateInstantiator();
$availableTypes = $instantiator->getRegisteredAgentTypes();
```

## Best Practices

### Naming

**Good Names:**
- "Code Review Agent"
- "Customer Service Chatbot"
- "Research Assistant"

**Bad Names:**
- "Agent1"
- "MyAgent"
- "test"

### Description

Be specific and actionable:

**Good:**
```
"Analyzes code pull requests for security vulnerabilities, best practices, 
and style consistency. Provides detailed feedback with code examples."
```

**Bad:**
```
"Reviews code"
```

### Tags

Use specific, searchable tags:

**Good:**
```json
["code-review", "security", "best-practices", "automated-review"]
```

**Bad:**
```json
["agent", "ai", "stuff"]
```

### System Prompts

Be clear and specific:

**Good:**
```
"You are a professional code reviewer specializing in PHP. When reviewing 
code, focus on: 1) Security vulnerabilities, 2) Performance issues, 
3) PSR compliance, 4) Best practices. Provide specific examples and 
suggest improvements."
```

**Bad:**
```
"Review code"
```

### Use Cases

List specific, relatable scenarios:

**Good:**
```json
{
  "use_cases": [
    "Automated PR reviews in CI/CD",
    "Pre-commit code quality checks",
    "Security vulnerability scanning",
    "Onboarding new developers with code feedback"
  ]
}
```

**Bad:**
```json
{
  "use_cases": ["Code stuff"]
}
```

### Difficulty Levels

- **beginner**: Can use with minimal setup, clear documentation
- **intermediate**: Requires some configuration, domain knowledge helpful
- **advanced**: Complex setup, requires deep understanding

### Icons

Choose meaningful emojis:

```
🤖 Basic agents
💬 Chat/conversation
📚 Knowledge/RAG
🔄 Workflows
🎯 Specialized
🏭 Production
🔬 Research
📧 Email
🎨 Creative
📊 Analytics
```

## Publishing Templates

### Organization Templates

For team/organization use:

1. Create `templates/organization/` directory
2. Add templates with team-specific configurations
3. Document in team wiki/docs

```php
$manager->setTemplatesPath(__DIR__ . '/templates/organization');
```

### Public Templates

For open-source sharing:

1. Validate template thoroughly
2. Test with multiple scenarios
3. Add comprehensive documentation
4. Create example usage
5. Submit pull request or publish package

### Template Package Structure

```
my-templates/
├── templates/
│   ├── agent1.json
│   ├── agent2.json
│   └── README.md
├── examples/
│   ├── agent1-example.php
│   └── agent2-example.php
├── tests/
│   └── TemplateTest.php
└── composer.json
```

## Testing Templates

### Validation Test

```php
public function testTemplateIsValid(): void
{
    $template = TemplateManager::getInstance()
        ->getByName('My Template');
    
    $this->assertTrue($template->isValid());
}
```

### Instantiation Test

```php
public function testTemplateCanBeInstantiated(): void
{
    $agent = TemplateManager::instantiate('my-template', [
        'api_key' => getenv('ANTHROPIC_API_KEY')
    ]);
    
    $this->assertInstanceOf(Agent::class, $agent);
}
```

### Execution Test

```php
public function testTemplateAgentWorks(): void
{
    $agent = TemplateManager::instantiate('my-template', [
        'api_key' => getenv('ANTHROPIC_API_KEY')
    ]);
    
    $result = $agent->run('Test task');
    
    $this->assertNotEmpty($result->getAnswer());
}
```

## Version Management

### Versioning Strategy

```json
{
  "version": "1.2.3",
  "last_tested_version": "1.2.0"
}
```

- **Major** (1.x.x): Breaking changes
- **Minor** (x.2.x): New features, backward compatible
- **Patch** (x.x.3): Bug fixes

### Updating Templates

1. Increment version number
2. Update `last_tested_version` if tested with new framework
3. Document changes in description or separate changelog
4. Test thoroughly before publishing

## Examples

See `examples/templates/04-custom-template.php` for a complete example of creating and exporting templates.

## See Also

- [Template System Guide](README.md) - Complete usage guide
- [Template Catalog](TEMPLATE_CATALOG.md) - All templates documented
- [Templates Tutorial](../tutorials/Templates_Tutorial.md) - Step-by-step tutorial
