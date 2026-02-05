# Agent Skills System

The Agent Skills system is a PHP implementation of the [agentskills.io](https://agentskills.io) specification. It provides a standardized way to package, discover, load, and compose reusable instruction sets ("skills") for AI agents. Skills enable agents to gain specialized capabilities on demand without requiring the full instructions to be loaded into context at all times.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Architecture](#architecture)
- [Quick Start](#quick-start)
- [Directory Structure](#directory-structure)
- [Components](#components)
- [Progressive Disclosure](#progressive-disclosure)
- [Related Documentation](#related-documentation)

## Overview

An Agent Skill is a self-contained directory with a `SKILL.md` file that contains YAML frontmatter metadata and markdown instructions. When an agent receives a user request, the Skills system automatically resolves which skills are relevant, loads their instructions, and composes them into the agent's system prompt.

The system is built around three key principles:

1. **Convention over configuration** -- Skills follow a standardized directory layout with a `SKILL.md` file at the root. No registration boilerplate is needed.
2. **Progressive disclosure** -- Only lightweight summaries (name and description) are loaded initially. Full instructions are loaded on demand when a skill is matched to a user's request.
3. **Separation of concerns** -- Each component (loading, registration, resolution, validation, composition) has a single responsibility and can be used or replaced independently.

## Core Concepts

### Skills

A skill is a directory containing a `SKILL.md` file. The file has two parts:

- **YAML frontmatter** -- Metadata including the skill name, description, tags, dependencies, and compatibility requirements.
- **Markdown body** -- The actual instructions that will be injected into the agent's prompt when the skill is activated.

### Skill Resolution

When a user sends a message, the `SkillResolver` scores each registered skill against the input using keyword matching on the name, description, and tags. Skills scoring above a configurable threshold are loaded and their instructions are composed into the prompt.

### Modes

Some skills are "mode skills" that modify agent behavior rather than adding task-specific capabilities. Mode skills are flagged with `mode: true` in frontmatter and are listed separately in the skills index.

### Auto-Invocation Control

By default, skills can be automatically invoked when the resolver determines relevance. Setting `disable-model-invocation: true` in frontmatter restricts a skill to manual invocation only -- the user or code must explicitly request it by name.

## Architecture

```
                    +------------------+
                    |   SkillManager   |  (Central facade / singleton)
                    +--------+---------+
                             |
        +----------+---------+----------+----------+
        |          |         |          |          |
   SkillLoader  Registry  Resolver  Validator  Installer
        |          |         |
        v          v         v
   Filesystem   In-Memory   Scoring &
   Discovery    Storage     Matching
```

The `SkillManager` is the primary entry point. It coordinates the following components:

| Component | Responsibility |
|-----------|---------------|
| `SkillLoader` | Discovers and parses `SKILL.md` files from the filesystem |
| `SkillRegistry` | Stores and indexes loaded skills in memory |
| `SkillResolver` | Matches skills to user queries by relevance scoring |
| `SkillValidator` | Validates skill format against the specification |
| `SkillInstaller` | Installs/uninstalls skill directories |
| `SkillExporter` | Exports skills to the standard directory format |
| `SkillPromptComposer` | Composes system prompts with skill instructions |
| `FrontmatterParser` | Parses YAML frontmatter from `SKILL.md` files |

## Quick Start

```php
use ClaudeAgents\Skills\SkillManager;

// Initialize with a skills directory
$manager = new SkillManager('/path/to/skills');

// Discover all skills from the filesystem
$manager->discover();

// Resolve skills relevant to a user's request
$skills = $manager->resolve('Review my PHP code for security issues');
// Returns: [Skill('code-review')]

// Get a specific skill by name
$skill = $manager->get('code-review');
echo $skill->getInstructions();

// Get lightweight summaries for progressive disclosure
$summaries = $manager->summaries();
// Returns: ['code-review' => ['name' => 'code-review', 'description' => '...']]
```

For a complete walkthrough, see [QUICKSTART.md](QUICKSTART.md).

## Directory Structure

### Project Layout

```
project/
  skills/                    # Default skills directory
    code-review/
      SKILL.md               # Required: skill definition
      scripts/               # Optional: executable scripts
        review-checklist.php
      references/             # Optional: reference documents
        severity-guidelines.md
      assets/                 # Optional: static assets
    api-testing/
      SKILL.md
      scripts/
        generate-test-suite.php
  src/
    Skills/                  # Skills system source code
      Skill.php
      SkillManager.php
      ...
```

### Skill Directory Layout

Each skill directory must contain a `SKILL.md` file and may contain the following optional subdirectories:

| Path | Required | Purpose |
|------|----------|---------|
| `SKILL.md` | Yes | Skill definition with frontmatter and instructions |
| `scripts/` | No | Executable scripts the agent can run |
| `references/` | No | Reference documents for additional context |
| `assets/` | No | Static files (images, templates, config files) |

## Components

### Skill

The `Skill` class represents a single loaded skill. It holds the parsed metadata, markdown instructions, filesystem path, and lists of discovered resources (scripts, references, assets). It provides relevance scoring against user queries.

See [API.md](API.md#skill) for the full API reference.

### SkillMetadata

A value object representing the YAML frontmatter fields. Contains both required fields (`name`, `description`) and optional fields (`license`, `version`, `metadata`, `dependencies`, `compatibility`, `disable-model-invocation`, `mode`).

See [API.md](API.md#skillmetadata) for the full API reference.

### SkillManager

The central facade that coordinates all skill operations. Supports singleton usage via `getInstance()` or direct instantiation. Provides methods for discovery, resolution, search, validation, installation, export, and prompt generation.

See [API.md](API.md#skillmanager) for the full API reference.

### SkillAwareAgent

A trait that can be applied to any agent class to add skill capabilities. Enables automatic skill resolution, manual skill loading, and prompt composition with progressive disclosure.

See [API.md](API.md#skillawareagent-trait) for the full API reference.

### SkillPromptComposer

Composes system prompts with loaded skill instructions. Supports three modes: active skills only, skills index only, and combined (loaded skills with discovery index for unloaded skills).

See [API.md](API.md#skillpromptcomposer) for the full API reference.

## Progressive Disclosure

The Skills system implements progressive disclosure to minimize context usage:

1. **Discovery phase** -- All skills are scanned but only summaries (name + description) are retained.
2. **Index injection** -- A compact skills index is added to the system prompt so the agent knows what is available.
3. **On-demand loading** -- When a user request matches a skill, its full instructions are loaded and composed into the prompt.
4. **Resource access** -- Scripts, references, and assets are only accessed when explicitly needed.

For a detailed explanation, see [PROGRESSIVE_DISCLOSURE.md](PROGRESSIVE_DISCLOSURE.md).

## Related Documentation

| Document | Description |
|----------|-------------|
| [QUICKSTART.md](QUICKSTART.md) | Step-by-step guide to get started |
| [SPECIFICATION.md](SPECIFICATION.md) | Agent Skills specification reference (agentskills.io adapted for PHP) |
| [API.md](API.md) | Full API reference for all classes |
| [CREATING_SKILLS.md](CREATING_SKILLS.md) | Guide to creating custom skills |
| [PROGRESSIVE_DISCLOSURE.md](PROGRESSIVE_DISCLOSURE.md) | How progressive disclosure works |

## Namespace

All Skills classes live under `ClaudeAgents\Skills`:

```
ClaudeAgents\Skills\Skill
ClaudeAgents\Skills\SkillMetadata
ClaudeAgents\Skills\SkillLoader
ClaudeAgents\Skills\SkillRegistry
ClaudeAgents\Skills\SkillResolver
ClaudeAgents\Skills\SkillValidator
ClaudeAgents\Skills\SkillManager
ClaudeAgents\Skills\SkillInstaller
ClaudeAgents\Skills\SkillExporter
ClaudeAgents\Skills\SkillPromptComposer
ClaudeAgents\Skills\SkillAwareAgent (trait)
ClaudeAgents\Skills\FrontmatterParser
```

Contracts live under `ClaudeAgents\Contracts`:

```
ClaudeAgents\Contracts\SkillInterface
ClaudeAgents\Contracts\SkillLoaderInterface
ClaudeAgents\Contracts\SkillRegistryInterface
ClaudeAgents\Contracts\SkillResolverInterface
```

Exceptions live under `ClaudeAgents\Skills\Exceptions`:

```
ClaudeAgents\Skills\Exceptions\SkillException
ClaudeAgents\Skills\Exceptions\SkillNotFoundException
ClaudeAgents\Skills\Exceptions\SkillValidationException
ClaudeAgents\Skills\Exceptions\SkillLoadException
ClaudeAgents\Skills\Exceptions\SkillInstallException
```
