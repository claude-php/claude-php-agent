# Agent Templates System - Tutorial Examples

Complete tutorial series for mastering the Agent Templates/Starter Projects system.

## Overview

This tutorial series teaches you how to discover, use, and create reusable agent templates using the Template System, inspired by Langflow's template architecture.

**Total Time:** ~60 minutes  
**Skill Level:** Beginner to Intermediate  
**Prerequisites:** Basic PHP and familiarity with claude-php-agent

## Prerequisites

### 1. Install Dependencies

```bash
composer install
```

### 2. Set API Key

```bash
export ANTHROPIC_API_KEY='your-anthropic-api-key-here'
```

### 3. Verify Setup

```bash
php -v  # Should show PHP 8.1+
echo $ANTHROPIC_API_KEY  # Should show your key
ls ../../templates/*.json | wc -l  # Should show 22+ templates
```

## Tutorial Series

### Tutorial 1: Discovering Templates (5 minutes)

**File:** `01-discovering-templates.php`

Learn how to browse and search the template catalog.

```bash
php 01-discovering-templates.php
```

**Topics:**
- Loading all templates
- Browsing by category
- Searching by tags
- Understanding template metadata
- Filtering by difficulty level

**What You'll Learn:**
- How to explore the 22+ available templates
- Different template categories and their purposes
- How to find the right template for your use case

---

### Tutorial 2: Instantiating Agents (10 minutes)

**File:** `02-instantiating-agents.php`

Learn to create live agents from templates.

```bash
php 02-instantiating-agents.php
```

**Topics:**
- Basic instantiation
- Configuration overrides
- Adding tools to templated agents
- Working with different agent types
- Running instantiated agents

**What You'll Learn:**
- How to quickly spin up agents
- Customizing template configurations
- Best practices for instantiation

---

### Tutorial 3: Template Metadata (10 minutes)

**File:** `03-template-metadata.php`

Deep dive into template metadata and requirements.

```bash
php 03-template-metadata.php
```

**Topics:**
- Reading template metadata
- Understanding requirements
- Checking compatibility
- Using metadata for selection
- Custom metadata fields

**What You'll Learn:**
- How to validate templates before use
- Understanding difficulty levels
- Setup time estimates
- Use case matching

---

### Tutorial 4: Advanced Search (10 minutes)

**File:** `04-advanced-search.php`

Master advanced search and filtering techniques.

```bash
php 04-advanced-search.php
```

**Topics:**
- Multi-criteria search
- Field selection for performance
- Complex tag filtering
- Category combinations
- Custom search patterns

**What You'll Learn:**
- Building powerful search queries
- Optimizing template discovery
- Creating custom template collections

---

### Tutorial 5: Creating Custom Templates (15 minutes)

**File:** `05-creating-templates.php`

Learn to export and create your own templates.

```bash
php 05-creating-templates.php
```

**Topics:**
- Exporting existing agents
- Creating templates from scratch
- Template validation
- Saving templates
- Sharing templates with team

**What You'll Learn:**
- How to package agent configurations
- Best practices for template creation
- Documentation and metadata standards

---

### Tutorial 6: Template Collections (10 minutes)

**File:** `06-template-collections.php`

Organize templates for your organization.

```bash
php 06-template-collections.php
```

**Topics:**
- Creating custom template directories
- Building domain-specific collections
- Template versioning
- Organization patterns
- Template inheritance

**What You'll Learn:**
- Building reusable agent libraries
- Team collaboration patterns
- Managing template lifecycles

---

### Tutorial 7: Production Patterns (15 minutes)

**File:** `07-production-integration.php`

Production-ready template usage patterns.

```bash
php 07-production-integration.php
```

**Topics:**
- Template validation pipelines
- Error handling strategies
- Fallback patterns
- Performance optimization
- Health checks

**What You'll Learn:**
- Production deployment strategies
- Monitoring template usage
- Graceful degradation
- Template testing strategies

---

## Learning Path

### For Beginners
Start with tutorials 1-3 to understand template basics:
1. Discovering Templates → 2. Instantiating Agents → 3. Template Metadata

### For Intermediate Users
Focus on advanced usage (tutorials 4-5):
4. Advanced Search → 5. Creating Custom Templates

### For Production Teams
Complete the series with tutorials 6-7:
6. Template Collections → 7. Production Integration

## Quick Reference

### Basic Template Usage

```php
use ClaudeAgents\Templates\TemplateManager;

// Get template manager
$manager = TemplateManager::getInstance();

// Search templates
$templates = $manager->search(
    query: 'chatbot',
    tags: ['conversation']
);

// Instantiate agent
$agent = $manager->instantiate('Dialog Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// Run agent
$result = $agent->run('Hello!');
```

### Template Categories

- **agents** (10) - Basic and foundational patterns
- **chatbots** (2) - Conversational agents
- **rag** (2) - Retrieval-augmented generation
- **workflows** (2) - Multi-step systems
- **specialized** (4) - Domain-specific agents
- **production** (2) - Enterprise configurations

## Common Patterns

### Pattern 1: Quick Start with Beginners
```php
$templates = $manager->search(tags: ['beginner']);
$agent = $manager->instantiate($templates[0]->getName(), [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);
```

### Pattern 2: Find Production-Ready
```php
$prodTemplates = $manager->search(category: 'production');
foreach ($prodTemplates as $template) {
    echo $template->getName() . " - " . 
         $template->getMetadata('estimated_setup') . "\n";
}
```

### Pattern 3: Custom Agent Export
```php
$template = $manager->exportAgent($myAgent, [
    'name' => 'Custom Agent',
    'category' => 'custom',
    'tags' => ['specialized']
]);
$manager->saveTemplate($template, 'custom/my-agent.json');
```

## Troubleshooting

### Templates Not Found
```bash
# Verify templates directory
ls ../../templates/*.json

# Check templates path
php -r "echo (new ClaudeAgents\Templates\TemplateManager())->getTemplatesPath();"
```

### Instantiation Fails
```bash
# Verify API key is set
echo $ANTHROPIC_API_KEY

# Check template is valid
php -r "
\$t = ClaudeAgents\Templates\TemplateManager::getInstance()->getByName('Basic Agent');
var_dump(\$t->isValid());
"
```

### Template Errors
```php
// Validate before using
$template = $manager->getByName('Template Name');
if (!$template->isValid()) {
    foreach ($template->getErrors() as $error) {
        echo "Error: $error\n";
    }
}
```

## Next Steps

After completing this tutorial:

1. **Read the Docs**
   - [Template System Guide](../../../docs/templates/README.md)
   - [Template Catalog](../../../docs/templates/TEMPLATE_CATALOG.md)
   - [Creating Templates](../../../docs/templates/CREATING_TEMPLATES.md)

2. **Explore Examples**
   - Check `examples/templates/` for more patterns
   - Review production examples

3. **Build Your Library**
   - Create custom templates for your use cases
   - Share templates with your team
   - Contribute templates to the community

## Support

- **Documentation:** [docs/templates/](../../../docs/templates/)
- **Examples:** [examples/templates/](../../templates/)
- **Issues:** [GitHub Issues](https://github.com/claude-php/claude-php-agent/issues)

## License

MIT License - see [LICENSE](../../../LICENSE) for details.
