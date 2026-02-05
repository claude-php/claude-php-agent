<?php

declare(strict_types=1);

/**
 * Agent Skills - Custom Skill Creation Example
 *
 * Demonstrates how to create custom skills programmatically
 * and from markdown strings.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;

SkillManager::resetInstance();

echo "=== Agent Skills: Custom Skill Creation ===\n\n";

$skillsDir = __DIR__ . '/../../skills';
$manager = new SkillManager($skillsDir);

// Method 1: Create from array
echo "--- Method 1: Create from array ---\n";
$skill = $manager->create([
    'name' => 'deployment-helper',
    'description' => 'Help with deployment tasks including Docker, CI/CD, and cloud services',
    'instructions' => "# Deployment Helper\n\n## Steps\n1. Review deployment config\n2. Validate environment\n3. Execute deployment",
    'version' => '1.0.0',
    'license' => 'MIT',
]);
$manager->register($skill);
echo "Created: {$skill->getName()} - {$skill->getDescription()}\n\n";

// Method 2: Create from markdown
echo "--- Method 2: Create from markdown ---\n";
$markdown = <<<'MD'
---
name: security-audit
description: Perform security audits on PHP applications looking for OWASP vulnerabilities
license: MIT
metadata:
  author: security-team
  tags: [security, audit, owasp]
---

# Security Audit Skill

## Overview
Systematically review PHP code for security vulnerabilities.

## Checklist
1. SQL Injection - Check all database queries
2. XSS - Validate output encoding
3. CSRF - Verify token validation
4. Authentication - Review auth logic
5. Authorization - Check access controls

## Reporting
Generate a findings report with severity levels.
MD;

$securitySkill = $manager->registerFromMarkdown($markdown);
echo "Created: {$securitySkill->getName()} - {$securitySkill->getDescription()}\n\n";

// Verify both are searchable
echo "--- Searching for skills ---\n";
$results = $manager->search('deploy');
echo "Search 'deploy': " . count($results) . " result(s)\n";
foreach ($results as $name => $s) {
    echo "  - {$name}\n";
}

$results = $manager->search('security');
echo "Search 'security': " . count($results) . " result(s)\n";
foreach ($results as $name => $s) {
    echo "  - {$name}\n";
}
echo "\n";

// Method 3: Export to filesystem
echo "--- Method 3: Export to filesystem ---\n";
$exportDir = sys_get_temp_dir() . '/exported-skills-' . uniqid();
$exportPath = $manager->export('security-audit', $exportDir);
echo "Exported to: {$exportPath}\n";
echo "SKILL.md exists: " . (file_exists($exportPath . '/SKILL.md') ? 'yes' : 'no') . "\n";

// Show exported content
echo "\nExported SKILL.md content:\n";
echo str_repeat('-', 40) . "\n";
echo file_get_contents($exportPath . '/SKILL.md');
echo str_repeat('-', 40) . "\n";

// Clean up
$items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($exportDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
}
rmdir($exportDir);
echo "\nCleaned up export directory.\n";
