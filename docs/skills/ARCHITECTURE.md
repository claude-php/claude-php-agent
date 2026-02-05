# Agent Skills Architecture

## Overview

The Agent Skills system implements the [agentskills.io](https://agentskills.io) open standard for modular AI agent capabilities. It follows a layered architecture with clear separation of concerns.

## Architecture Diagram

```
┌─────────────────────────────────────────────────┐
│                  SkillManager                    │
│           (Central Facade/Singleton)             │
├─────────────────────────────────────────────────┤
│                                                  │
│  ┌──────────────┐  ┌──────────────┐             │
│  │  SkillLoader  │  │ SkillRegistry│             │
│  │ (Filesystem)  │→ │  (In-Memory) │             │
│  └──────────────┘  └──────┬───────┘             │
│                           │                      │
│  ┌──────────────┐  ┌──────┴───────┐             │
│  │SkillValidator│  │ SkillResolver│             │
│  │ (Validation) │  │  (Matching)  │             │
│  └──────────────┘  └──────────────┘             │
│                                                  │
│  ┌──────────────┐  ┌──────────────┐             │
│  │SkillInstaller│  │ SkillExporter│             │
│  │  (Install)   │  │   (Export)   │             │
│  └──────────────┘  └──────────────┘             │
│                                                  │
├─────────────────────────────────────────────────┤
│              SkillPromptComposer                 │
│         (Prompt Integration Layer)               │
└─────────────────────────────────────────────────┘
```

## Data Flow

### Discovery Flow

```
Filesystem → SkillLoader → FrontmatterParser → Skill → SkillRegistry
```

1. `SkillLoader` scans skill directories
2. For each directory with `SKILL.md`, it reads the file
3. `FrontmatterParser` extracts YAML frontmatter and markdown body
4. `SkillMetadata` is created from the frontmatter
5. `Skill` object is constructed with metadata, instructions, and resources
6. Skill is registered in `SkillRegistry`

### Resolution Flow

```
User Query → SkillResolver → Scored Skills → SkillPromptComposer → Enhanced Prompt
```

1. User query is passed to `SkillResolver`
2. Resolver scores each auto-invocable skill against the query
3. Skills above the threshold are returned, sorted by relevance
4. `SkillPromptComposer` builds the enhanced prompt with skill instructions

### Installation Flow

```
Source Path → SkillInstaller → Validation → Copy → SkillRegistry
```

1. Source skill directory is validated
2. Files are copied to the install directory
3. Skill is loaded and registered

## Design Patterns

### Singleton (SkillManager)

The `SkillManager` acts as a facade and uses the singleton pattern for global access. It can be reset for testing.

### Value Object (SkillMetadata)

`SkillMetadata` is an immutable value object with readonly properties. It cannot be modified after creation.

### Registry (SkillRegistry)

The registry provides indexed access to skills with search and filtering capabilities.

### Strategy (SkillResolver)

The resolver implements a scoring strategy that can be extended with custom matching logic.

### Facade (SkillManager)

`SkillManager` provides a unified interface to the subsystem of loader, registry, resolver, validator, installer, and exporter.

## Progressive Disclosure Design

The system implements progressive disclosure at multiple levels:

1. **Summaries** - Name + description only (always available, no file I/O)
2. **Instructions** - Full SKILL.md body (loaded on match)
3. **References** - Detailed docs in references/ (loaded on demand)
4. **Scripts** - Helper scripts (executed when needed)

This ensures the AI agent's context is not overwhelmed with unused skill instructions.

## Scoring Algorithm

The relevance scoring uses a weighted multi-signal approach:

```
score = max(
    nameMatch * 0.8,          // Direct name match
    descriptionMatch * 0.5,    // Description word match
    tagMatch * 0.3             // Tag match
)
```

- **Name match**: Skill name appears in query (or query in name)
- **Description match**: Ratio of query words found in description
- **Tag match**: Any tag appears in query

The default threshold is 0.15, filtering out low-relevance matches.

## Thread Safety

The system is designed for single-request PHP execution. The singleton pattern is suitable for web requests but should be reset between test cases using `SkillManager::resetInstance()`.

## Extension Points

| Extension | How |
|-----------|-----|
| Custom scoring | Extend `SkillResolver` with custom `relevanceScore()` |
| Custom loaders | Implement `SkillLoaderInterface` for non-filesystem sources |
| Custom registries | Implement `SkillRegistryInterface` for persistent storage |
| Custom validators | Add validation rules in `SkillValidator` |
