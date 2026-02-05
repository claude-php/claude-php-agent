<?php

declare(strict_types=1);

/**
 * Tutorial 05: Creating Custom Skills
 *
 * Learn how to create skills from arrays, markdown, and export them.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillExporter;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillMetadata;

SkillManager::resetInstance();

echo "=== Tutorial 05: Creating Custom Skills ===\n\n";

$skillsDir = __DIR__ . '/../../../skills';
$manager = new SkillManager($skillsDir);

// ─── Step 1: Create from array ───
echo "Step 1: Create skill from array\n";
echo str_repeat('-', 50) . "\n";

$skill = $manager->create([
    'name' => 'testing-helper',
    'description' => 'Help write PHPUnit tests for PHP applications',
    'instructions' => "# Testing Helper\n\n## Guidelines\n- Write tests for all public methods\n- Use data providers for multiple inputs\n- Mock external dependencies",
    'version' => '1.0.0',
    'license' => 'MIT',
]);

echo "Created: {$skill->getName()}\n";
echo "Description: {$skill->getDescription()}\n";

$manager->register($skill);
echo "Registered in manager. Total skills: {$manager->count()}\n\n";

// ─── Step 2: Create from markdown ───
echo "Step 2: Create skill from markdown\n";
echo str_repeat('-', 50) . "\n";

$markdown = <<<'MD'
---
name: database-helper
description: Help with database schema design and query optimization
license: MIT
metadata:
  author: dba-team
  tags: [database, sql, optimization]
---

# Database Helper

## Schema Design
- Normalize to 3NF by default
- Use foreign keys for referential integrity
- Index frequently queried columns

## Query Optimization
- EXPLAIN before committing complex queries
- Avoid SELECT * in production
- Use prepared statements
MD;

$dbSkill = $manager->registerFromMarkdown($markdown);
echo "Created: {$dbSkill->getName()}\n";
echo "Tags: " . implode(', ', $dbSkill->getMetadata()->getTags()) . "\n";
echo "Total skills: {$manager->count()}\n\n";

// ─── Step 3: Create from objects ───
echo "Step 3: Create skill from objects\n";
echo str_repeat('-', 50) . "\n";

$metadata = new SkillMetadata(
    name: 'error-handler',
    description: 'Guide for implementing error handling in PHP',
    license: 'MIT',
    version: '2.0.0',
    metadata: ['author' => 'core-team', 'tags' => ['errors', 'exceptions']],
);

$skill = new Skill(
    metadata: $metadata,
    instructions: "# Error Handling\n\nUse try-catch blocks. Create custom exception classes.",
    path: '',
);

$manager->register($skill);
echo "Created: {$skill->getName()} v{$skill->getMetadata()->version}\n";
echo "Total skills: {$manager->count()}\n\n";

// ─── Step 4: Export to filesystem ───
echo "Step 4: Export skill to filesystem\n";
echo str_repeat('-', 50) . "\n";

$exportDir = sys_get_temp_dir() . '/tutorial-export-' . uniqid();
$path = $manager->export('database-helper', $exportDir);
echo "Exported to: {$path}\n";
echo "Files:\n";
foreach (scandir($path) as $f) {
    if ($f !== '.' && $f !== '..') {
        echo "  - {$f}\n";
    }
}
echo "\nSKILL.md content:\n" . file_get_contents($path . '/SKILL.md') . "\n";

// ─── Step 5: Generate blank template ───
echo "Step 5: Generate a blank skill template\n";
echo str_repeat('-', 50) . "\n";

$exporter = new SkillExporter();
$templateDir = sys_get_temp_dir() . '/tutorial-template-' . uniqid();
$templatePath = $exporter->generateTemplate($templateDir, 'my-new-skill');
echo "Template created at: {$templatePath}\n";
echo "Template content:\n" . file_get_contents($templatePath . '/SKILL.md') . "\n";

// ─── Step 6: Verify created skills are searchable ───
echo "Step 6: Verify skills are searchable\n";
echo str_repeat('-', 50) . "\n";

$tests = ['test', 'database', 'error'];
foreach ($tests as $q) {
    $result = $manager->resolveOne($q);
    echo "  resolveOne('{$q}'): " . ($result ? $result->getName() : 'null') . "\n";
}
echo "\n";

// Clean up
$cleanup = function (string $dir) use (&$cleanup) {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . '/' . $item;
        is_dir($p) ? $cleanup($p) : unlink($p);
    }
    rmdir($dir);
};
$cleanup($exportDir);
$cleanup($templateDir);

echo "Tutorial complete! You can create and export custom skills.\n";
echo "Next: Tutorial 06 - Skill Management\n";
