# Skill Creation Guide

This guide walks through creating custom Agent Skills for the Claude PHP Agent framework.

## Skill Directory Structure

```
my-skill/
├── SKILL.md          # Required: metadata + instructions
├── scripts/          # Optional: helper scripts
│   ├── analyze.php
│   └── validate.sh
├── references/       # Optional: reference documents
│   ├── standards.md
│   └── examples.md
└── assets/           # Optional: templates, configs
    └── template.json
```

## Writing SKILL.md

### Frontmatter

```yaml
---
name: my-skill-name
description: Clear description of what this skill does and when to use it
license: MIT
version: "1.0.0"
metadata:
  author: your-name
  tags: [tag1, tag2, tag3]
---
```

**Name Rules:**
- Use kebab-case: `my-skill-name`
- Maximum 64 characters
- Start and end with alphanumeric

**Description Rules:**
- Explain what the skill does AND when to use it
- Include trigger phrases (e.g., "Use when the user asks to review code")
- Keep under 200 characters for the summary; longer descriptions are allowed

### Instructions Body

```markdown
# Skill Title

## Overview
Brief overview of what this skill enables.

## Steps
1. First step
2. Second step
3. Third step

## Guidelines
- Important guideline 1
- Important guideline 2

## Examples
Show example inputs and expected outputs.
```

**Best Practices:**
- Start with a clear heading
- Use structured sections (Overview, Steps, Guidelines, Examples)
- Keep under 500 lines; use references/ for detailed content
- Include concrete examples
- Specify what NOT to do

## Progressive Disclosure

For large skills, use the references/ directory:

```markdown
# Complex Skill

## Overview
This skill handles complex tasks. See references/ for details.

## Quick Reference
| Task | Approach |
|------|----------|
| Simple case | Do X |
| Complex case | See references/complex-guide.md |
```

The agent loads SKILL.md first. If more detail is needed, it reads from references/.

## Mode Skills

Mode skills change agent behavior rather than providing task-specific instructions:

```yaml
---
name: verbose-mode
description: Enable verbose output with detailed explanations
mode: true
---

# Verbose Mode

When active, provide detailed step-by-step explanations for all actions.
Include reasoning for decisions and alternative approaches considered.
```

## Manual-Only Skills

Prevent auto-invocation for admin or dangerous skills:

```yaml
---
name: database-migration
description: Run database migrations (manual invocation only)
disable-model-invocation: true
---

# Database Migration

Only run when explicitly requested by the user.
```

## Adding Scripts

Helper scripts that the skill can reference:

```
my-skill/scripts/
├── analyze.php       # PHP helper
├── validate.sh       # Shell script
└── transform.py      # Python script
```

Reference them in your instructions:
```markdown
## Running Analysis
Execute the analysis script: `php scripts/analyze.php input.json`
```

## Adding References

Detailed documentation for progressive disclosure:

```
my-skill/references/
├── api-guide.md      # Detailed API guide
├── patterns.md       # Design patterns
└── troubleshooting.md
```

## Validation

Validate your skill before distribution:

```php
$validator = new SkillValidator();

// Validate content
$result = $validator->validate(file_get_contents('my-skill/SKILL.md'));

// Validate directory
$result = $validator->validateDirectory('my-skill/');

if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo "ERROR: {$error}\n";
    }
}
foreach ($result['warnings'] as $warning) {
    echo "WARNING: {$warning}\n";
}
```

## Distributing Skills

### Export from the Framework

```php
$exporter = new SkillExporter();
$exporter->export($skill, './exported/my-skill');
```

### Share as a Directory

Copy the skill directory to another project's skills/ path.

### Install from Source

```php
$installer = new SkillInstaller('./skills');
$installer->install('/path/to/external-skill');
```

## Checklist

- [ ] SKILL.md has name and description in frontmatter
- [ ] Name is kebab-case and under 64 characters
- [ ] Description explains what and when
- [ ] Instructions are clear and actionable
- [ ] References used for content over 500 lines
- [ ] Scripts are documented in instructions
- [ ] Validates without errors
