<?php

declare(strict_types=1);

/**
 * Tutorial 06: Skill Management
 *
 * Learn how to install, uninstall, validate, and manage skills.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\SkillInstaller;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillValidator;

SkillManager::resetInstance();

echo "=== Tutorial 06: Skill Management ===\n\n";

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

// ─── Step 1: Validate skill content ───
echo "Step 1: Validate skill content\n";
echo str_repeat('-', 50) . "\n";

$validator = new SkillValidator();

// Valid content
$valid = <<<'MD'
---
name: valid-skill
description: A properly formatted skill
---

# Valid Skill

Instructions here.
MD;

$result = $validator->validate($valid);
echo "Valid skill:   valid={$result['valid']}, errors=" . count($result['errors']) . ", warnings=" . count($result['warnings']) . "\n";

// Invalid (missing description)
$invalid = "---\nname: bad-skill\n---\nBody\n";
$result = $validator->validate($invalid);
echo "Missing desc:  valid={$result['valid']}, errors=" . count($result['errors']) . "\n";
foreach ($result['errors'] as $e) {
    echo "  ERROR: {$e}\n";
}

// Warnings
$warned = "---\nname: MyBadName\ndescription: " . str_repeat('x', 210) . "\n---\n";
$result = $validator->validate($warned);
echo "With warnings: valid={$result['valid']}, warnings=" . count($result['warnings']) . "\n";
foreach ($result['warnings'] as $w) {
    echo "  WARN: " . substr($w, 0, 80) . "\n";
}
echo "\n";

// ─── Step 2: Validate directories ───
echo "Step 2: Validate skill directories\n";
echo str_repeat('-', 50) . "\n";

$skillsDir = __DIR__ . '/../../../skills';
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
    $icon = $result['valid'] ? 'PASS' : 'FAIL';
    echo "  [{$icon}] {$item}";
    if (!empty($result['warnings'])) {
        echo " (" . count($result['warnings']) . " warnings)";
    }
    echo "\n";
}
echo "\n";

// ─── Step 3: Install skills ───
echo "Step 3: Install skills from external sources\n";
echo str_repeat('-', 50) . "\n";

$installDir = sys_get_temp_dir() . '/managed-skills-' . uniqid();
mkdir($installDir, 0755, true);

// Create an external skill
$sourceDir = sys_get_temp_dir() . '/external-skill-' . uniqid();
mkdir($sourceDir . '/scripts', 0755, true);
file_put_contents($sourceDir . '/SKILL.md', <<<'MD'
---
name: external-skill
description: An externally installed skill
license: Apache-2.0
---

# External Skill

Instructions for the external skill.
MD
);
file_put_contents($sourceDir . '/scripts/run.sh', '#!/bin/bash\necho "running"');

$installer = new SkillInstaller($installDir);
$skill = $installer->install($sourceDir);
echo "Installed: {$skill->getName()}\n";
echo "Location: {$installDir}/{$skill->getName()}\n";
echo "Is installed: " . ($installer->isInstalled('external-skill') ? 'yes' : 'no') . "\n\n";

// ─── Step 4: List installed skills ───
echo "Step 4: List installed skills\n";
echo str_repeat('-', 50) . "\n";

$installed = $installer->listInstalled();
echo "Installed skills: " . implode(', ', $installed) . "\n\n";

// ─── Step 5: Uninstall skills ───
echo "Step 5: Uninstall skills\n";
echo str_repeat('-', 50) . "\n";

$installer->uninstall('external-skill');
echo "Uninstalled: external-skill\n";
echo "Still installed: " . ($installer->isInstalled('external-skill') ? 'yes' : 'no') . "\n";
echo "Remaining: " . count($installer->listInstalled()) . "\n\n";

// ─── Step 6: Manager-level management ───
echo "Step 6: Manager-level operations\n";
echo str_repeat('-', 50) . "\n";

$manager = new SkillManager($skillsDir);
$manager->discover();

echo "Total skills: {$manager->count()}\n";
echo "Search 'code': " . count($manager->search('code')) . " results\n";
echo "Search 'data': " . count($manager->search('data')) . " results\n";

// Validate through manager
$result = $manager->validate($valid);
echo "Manager validate: valid={$result['valid']}\n";

$result = $manager->validateDirectory($skillsDir . '/code-review');
echo "Manager validateDir: valid={$result['valid']}\n\n";

// Clean up
$cleanup($installDir);
$cleanup($sourceDir);

echo "Tutorial complete! You can manage the full skill lifecycle.\n";
echo "Next: Tutorial 07 - Advanced Patterns\n";
