<?php

declare(strict_types=1);

/**
 * Agent Skills - Skill Validation Example
 *
 * Demonstrates how to validate skill content and directories
 * against the Agent Skills specification.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillValidator;

echo "=== Agent Skills: Skill Validation ===\n\n";

$validator = new SkillValidator();

// 1. Validate good skill content
echo "--- Valid skill content ---\n";
$validContent = <<<'MD'
---
name: my-skill
description: A valid skill following the spec
license: MIT
---

# My Skill

Instructions for this skill.
MD;

$result = $validator->validate($validContent);
echo "Valid: " . ($result['valid'] ? 'yes' : 'no') . "\n";
echo "Errors: " . count($result['errors']) . "\n";
echo "Warnings: " . count($result['warnings']) . "\n\n";

// 2. Validate skill missing required fields
echo "--- Invalid skill (missing fields) ---\n";
$invalidContent = <<<'MD'
---
name: my-skill
---

# Missing Description
MD;

$result = $validator->validate($invalidContent);
echo "Valid: " . ($result['valid'] ? 'yes' : 'no') . "\n";
foreach ($result['errors'] as $error) {
    echo "  ERROR: {$error}\n";
}
echo "\n";

// 3. Validate with warnings
echo "--- Skill with warnings ---\n";
$warningContent = <<<'MD'
---
name: MySkill_Bad-Name
description: This description is way too long and exceeds the recommended character limit for a skill description field which should be concise and to the point but this one is not so it generates a warning because it exceeds two hundred characters limit
---
MD;

$result = $validator->validate($warningContent);
echo "Valid: " . ($result['valid'] ? 'yes' : 'no') . "\n";
foreach ($result['warnings'] as $warning) {
    echo "  WARNING: {$warning}\n";
}
echo "\n";

// 4. Validate bundled skill directories
echo "--- Bundled skill directories ---\n";
$skillsDir = __DIR__ . '/../../skills';
$items = scandir($skillsDir);
foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    $path = $skillsDir . '/' . $item;
    if (!is_dir($path)) {
        continue;
    }
    $result = $validator->validateDirectory($path);
    $status = $result['valid'] ? 'PASS' : 'FAIL';
    echo "  [{$status}] {$item}\n";
    foreach ($result['errors'] as $error) {
        echo "         ERROR: {$error}\n";
    }
    foreach ($result['warnings'] as $warning) {
        echo "         WARN: {$warning}\n";
    }
}
echo "\n";
