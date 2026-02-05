<?php

declare(strict_types=1);

/**
 * Agent Skills - Skill Installation Example
 *
 * Demonstrates how to install and uninstall skills from external sources.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\SkillInstaller;
use ClaudeAgents\Skills\SkillLoader;

echo "=== Agent Skills: Skill Installation ===\n\n";

// Set up install directory
$installDir = sys_get_temp_dir() . '/installed-skills-' . uniqid();
mkdir($installDir, 0755, true);

// Create a source skill to install
$sourceDir = sys_get_temp_dir() . '/source-skill-' . uniqid();
mkdir($sourceDir . '/scripts', 0755, true);
mkdir($sourceDir . '/references', 0755, true);

file_put_contents($sourceDir . '/SKILL.md', <<<'MD'
---
name: git-workflow
description: Guide for Git branching strategies and workflow best practices
license: MIT
metadata:
  author: devops-team
  tags: [git, workflow, branching]
---

# Git Workflow Skill

## Branching Strategy
- Use feature branches from main
- Name branches: feature/*, bugfix/*, hotfix/*
- Squash merge to main

## Commit Messages
Follow conventional commits format:
- feat: New feature
- fix: Bug fix
- docs: Documentation
- refactor: Code refactoring
MD
);

file_put_contents($sourceDir . '/scripts/branch-check.sh', '#!/bin/bash\ngit branch --list');
file_put_contents($sourceDir . '/references/conventions.md', '# Conventional Commits\n\nSee conventionalcommits.org');

$installer = new SkillInstaller($installDir);

// Install the skill
echo "--- Installing skill ---\n";
$skill = $installer->install($sourceDir);
echo "Installed: {$skill->getName()}\n";
echo "Description: {$skill->getDescription()}\n";
echo "Installed path: {$installDir}/{$skill->getName()}\n\n";

// List installed skills
echo "--- Installed skills ---\n";
$installed = $installer->listInstalled();
foreach ($installed as $name) {
    echo "  - {$name}\n";
    echo "    Installed: " . ($installer->isInstalled($name) ? 'yes' : 'no') . "\n";
}
echo "\n";

// Verify the installed skill can be loaded
echo "--- Verify loaded ---\n";
$loader = new SkillLoader($installDir);
$loaded = $loader->loadByName('git-workflow');
echo "Loaded: {$loaded->getName()}\n";
echo "Scripts: " . implode(', ', $loaded->getScripts()) . "\n";
echo "References: " . implode(', ', $loaded->getReferences()) . "\n\n";

// Uninstall
echo "--- Uninstalling ---\n";
$installer->uninstall('git-workflow');
echo "Uninstalled git-workflow\n";
echo "Still installed: " . ($installer->isInstalled('git-workflow') ? 'yes' : 'no') . "\n\n";

// Clean up
$cleanup = function (string $dir) use (&$cleanup) {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        is_dir($path) ? $cleanup($path) : unlink($path);
    }
    rmdir($dir);
};
$cleanup($installDir);
$cleanup($sourceDir);
echo "Cleaned up temporary directories.\n";
