<?php

declare(strict_types=1);

/**
 * Tutorial 02: Understanding Skills
 *
 * Learn the anatomy of a skill: frontmatter, instructions, resources.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;

echo "=== Tutorial 02: Understanding Skills ===\n\n";

// ─── Step 1: SKILL.md format ───
echo "Step 1: The SKILL.md format\n";
echo str_repeat('-', 50) . "\n";

// Every skill is defined by a SKILL.md file with YAML frontmatter.
$skillMd = <<<'MD'
---
name: example-skill
description: An example skill to demonstrate the format
license: MIT
version: "1.0.0"
metadata:
  author: tutorial
  tags: [example, tutorial, learning]
---

# Example Skill

## Overview
This is an example skill that demonstrates the SKILL.md format.

## Instructions
1. Parse the frontmatter for metadata
2. Read the body for instructions
3. Load resources from subdirectories
MD;

echo "SKILL.md content:\n{$skillMd}\n\n";

// ─── Step 2: Parse frontmatter ───
echo "Step 2: Parse frontmatter\n";
echo str_repeat('-', 50) . "\n";

// FrontmatterParser extracts YAML metadata and markdown body.
$parsed = FrontmatterParser::parse($skillMd);

echo "Frontmatter fields:\n";
foreach ($parsed['frontmatter'] as $key => $value) {
    if (is_array($value)) {
        echo "  {$key}: " . json_encode($value) . "\n";
    } else {
        echo "  {$key}: {$value}\n";
    }
}

echo "\nBody (first 100 chars): " . substr($parsed['body'], 0, 100) . "...\n\n";

// ─── Step 3: SkillMetadata ───
echo "Step 3: SkillMetadata value object\n";
echo str_repeat('-', 50) . "\n";

// SkillMetadata is an immutable value object created from frontmatter.
$metadata = SkillMetadata::fromArray($parsed['frontmatter']);

echo "Name: {$metadata->name}\n";
echo "Description: {$metadata->description}\n";
echo "License: {$metadata->license}\n";
echo "Version: {$metadata->version}\n";
echo "Author: {$metadata->getAuthor()}\n";
echo "Tags: " . implode(', ', $metadata->getTags()) . "\n";
echo "Is mode: " . ($metadata->mode ? 'yes' : 'no') . "\n";
echo "Auto-invocable: " . ($metadata->disableModelInvocation ? 'no' : 'yes') . "\n\n";

// ─── Step 4: Skill object ───
echo "Step 4: Creating a Skill object\n";
echo str_repeat('-', 50) . "\n";

// A Skill combines metadata, instructions, and resource paths.
$skill = new Skill(
    metadata: $metadata,
    instructions: $parsed['body'],
    path: '/example/path',
    scripts: ['helper.php', 'validate.sh'],
    references: ['guide.md', 'standards.md'],
    assets: ['template.json'],
);

echo "Name: {$skill->getName()}\n";
echo "Scripts: " . implode(', ', $skill->getScripts()) . "\n";
echo "References: " . implode(', ', $skill->getReferences()) . "\n";
echo "Assets: " . implode(', ', $skill->getAssets()) . "\n\n";

// ─── Step 5: From markdown ───
echo "Step 5: Create skill from markdown string\n";
echo str_repeat('-', 50) . "\n";

// You can create a skill directly from a markdown string.
$skill2 = Skill::fromMarkdown($skillMd);
echo "Created: {$skill2->getName()} - {$skill2->getDescription()}\n\n";

// ─── Step 6: Generate frontmatter ───
echo "Step 6: Generate frontmatter from array\n";
echo str_repeat('-', 50) . "\n";

// You can generate valid YAML frontmatter from an array.
$yaml = FrontmatterParser::generate([
    'name' => 'generated-skill',
    'description' => 'A skill generated programmatically',
    'metadata' => [
        'author' => 'system',
        'tags' => ['auto', 'generated'],
    ],
]);

echo "Generated frontmatter:\n{$yaml}\n";

echo "\nTutorial complete! You've learned the anatomy of an Agent Skill.\n";
echo "Next: Tutorial 03 - Skill Resolution\n";
