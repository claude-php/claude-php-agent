# Agent Skills API Reference

## SkillManager

Central management class for the Agent Skills system.

### Constructor

```php
$manager = new SkillManager(string $skillsPath);
```

### Singleton Access

```php
$manager = SkillManager::getInstance(string $skillsPath);
SkillManager::resetInstance(); // Reset for testing
```

### Discovery

```php
$skills = $manager->discover(): array;     // Load all skills from paths
$manager->addPath(string $path): void;      // Add additional skills directory
```

### Retrieval

```php
$skill = $manager->get(string $name): Skill;     // Get by name (loads on demand)
$skills = $manager->all(): array;                  // Get all discovered skills
$count = $manager->count(): int;                   // Count registered skills
$summaries = $manager->summaries(): array;         // Get lightweight summaries
```

### Resolution

```php
$skills = $manager->resolve(string $query, float $threshold = 0.15): array;
$skill = $manager->resolveOne(string $query): ?Skill;
```

### Search

```php
$results = $manager->search(string $query): array;
```

### Registration

```php
$manager->register(Skill $skill): void;
$skill = $manager->registerFromMarkdown(string $markdown): Skill;
$skill = $manager->create(array $data): Skill;
```

### Validation

```php
$result = $manager->validate(string $content): array;
$result = $manager->validateDirectory(string $path): array;
```

### Installation

```php
$skill = $manager->install(string $sourcePath): Skill;
$manager->uninstall(string $name): void;
```

### Export

```php
$path = $manager->export(string $name, string $outputDir): string;
```

### Prompt Generation

```php
$prompt = $manager->generateSkillsPrompt(): string;
```

### Component Access

```php
$registry = $manager->getRegistry(): SkillRegistry;
$loader = $manager->getLoader(): SkillLoader;
$validator = $manager->getValidator(): SkillValidator;
$resolver = $manager->getResolver(): SkillResolver;
```

---

## Skill

Represents a single Agent Skill.

### Constructor

```php
$skill = new Skill(
    SkillMetadata $metadata,
    string $instructions,
    string $path,
    array $scripts = [],
    array $references = [],
    array $assets = [],
);
```

### Static Factory

```php
$skill = Skill::fromMarkdown(string $markdown, string $path = ''): Skill;
$skill = Skill::createWithResourceDiscovery(SkillMetadata $metadata, string $instructions, string $path): Skill;
```

### Properties

```php
$skill->getName(): string;
$skill->getDescription(): string;
$skill->getMetadata(): SkillMetadata;
$skill->getInstructions(): string;
$skill->getPath(): string;
$skill->getScripts(): array;
$skill->getReferences(): array;
$skill->getAssets(): array;
```

### State

```php
$skill->isLoaded(): bool;
$skill->markLoaded(): void;
$skill->isAutoInvocable(): bool;
$skill->isMode(): bool;
```

### Matching

```php
$skill->matchesQuery(string $query): bool;
$skill->relevanceScore(string $query): float;  // 0.0 to 1.0
```

### Resource Content

```php
$content = $skill->getReferenceContent(string $name): ?string;
$content = $skill->getScriptContent(string $name): ?string;
```

### Serialization

```php
$array = $skill->toArray(): array;
$summary = $skill->getSummary(): array;  // ['name' => ..., 'description' => ...]
```

---

## SkillMetadata

Immutable value object for skill YAML frontmatter.

### Constructor

```php
$meta = new SkillMetadata(
    string $name,
    string $description,
    ?string $license = null,
    ?string $version = null,
    bool $mode = false,
    bool $disableModelInvocation = false,
    array $metadata = [],
    array $dependencies = [],
    array $compatibility = [],
);
```

### Static Factory

```php
$meta = SkillMetadata::fromArray(array $data): SkillMetadata;
```

### Properties (readonly)

```php
$meta->name: string;
$meta->description: string;
$meta->license: ?string;
$meta->version: ?string;
$meta->mode: bool;
$meta->disableModelInvocation: bool;
$meta->metadata: array;
$meta->dependencies: array;
$meta->compatibility: array;
```

### Methods

```php
$meta->getAuthor(): ?string;
$meta->getTags(): array;
$meta->toArray(): array;
```

---

## SkillLoader

Loads skills from the filesystem.

```php
$loader = new SkillLoader(string $skillsPath);

$skills = $loader->loadAll(): array;
$skill = $loader->loadByName(string $name): Skill;
$skill = $loader->loadFromPath(string $path): Skill;
$exists = $loader->exists(string $name): bool;
$loader->addPath(string $path): void;
$loader->enableCaching(): void;
$loader->disableCaching(): void;
$loader->clearCache(): void;
$path = $loader->getSkillsPath(): string;
```

---

## SkillRegistry

In-memory registry of skills.

```php
$registry = new SkillRegistry();

$registry->register(Skill $skill): void;
$registry->registerMany(array $skills): void;
$registry->unregister(string $name): void;
$skill = $registry->get(string $name): Skill;
$exists = $registry->has(string $name): bool;
$all = $registry->all(): array;
$count = $registry->count(): int;
$names = $registry->names(): array;
$results = $registry->search(string $query): array;
$summaries = $registry->summaries(): array;
$auto = $registry->getAutoInvocable(): array;
$modes = $registry->getModes(): array;
$registry->clear(): void;
```

---

## SkillResolver

Matches skills to queries.

```php
$resolver = new SkillResolver(SkillRegistry $registry);

$skills = $resolver->resolve(string $query, float $threshold = 0.15): array;
$skill = $resolver->resolveOne(string $query, float $threshold = 0.15): ?Skill;
$scored = $resolver->resolveWithScores(string $query, float $threshold = 0.15): array;
$skill = $resolver->resolveByName(string $name): ?Skill;
```

---

## SkillValidator

Validates skills against the specification.

```php
$validator = new SkillValidator();

$result = $validator->validate(string $content): array;
$result = $validator->validateDirectory(string $path): array;
$result = $validator->validateSkill(Skill $skill): array;
```

Return format: `['valid' => bool, 'errors' => string[], 'warnings' => string[]]`

---

## SkillInstaller

Installs and uninstalls skills.

```php
$installer = new SkillInstaller(string $installPath, ?SkillRegistry $registry = null);

$skill = $installer->install(string $sourcePath): Skill;
$installer->uninstall(string $name): void;
$installed = $installer->isInstalled(string $name): bool;
$names = $installer->listInstalled(): array;
```

---

## SkillExporter

Exports skills to filesystem.

```php
$exporter = new SkillExporter();

$path = $exporter->export(Skill $skill, string $outputDir): string;
$paths = $exporter->exportMany(array $skills, string $outputDir): array;
$md = $exporter->generateSkillMd(Skill $skill): string;
$path = $exporter->generateTemplate(string $outputDir, string $name): string;
$skill = $exporter->createSkill(string $name, string $description, string $instructions, string $outputDir): Skill;
```

---

## SkillPromptComposer

Composes prompts with skill instructions.

```php
$composer = new SkillPromptComposer();

$prompt = $composer->compose(string $basePrompt, array $skills): string;
$section = $composer->buildSkillsSection(array $skills): string;
$index = $composer->buildSkillsIndex(array $summaries): string;
$prompt = $composer->composeWithDiscovery(string $basePrompt, array $loadedSkills, array $summaries): string;
```

---

## FrontmatterParser

Parses and generates YAML frontmatter.

```php
$parsed = FrontmatterParser::parse(string $content): array;
// Returns: ['frontmatter' => array, 'body' => string]

$yaml = FrontmatterParser::generate(array $data): string;
// Returns: "---\nkey: value\n---"
```

---

## Exceptions

| Exception | When |
|-----------|------|
| `SkillNotFoundException` | Skill not found by name or path |
| `SkillValidationException` | Invalid skill content or format |
| `SkillInstallException` | Installation or removal failure |
| `SkillException` | Base class for all skill errors |
