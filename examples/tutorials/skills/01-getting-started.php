<?php

declare(strict_types=1);

/**
 * Tutorial 01: Getting Started with Agent Skills
 *
 * Learn the basics of discovering, loading, and listing skills.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;

SkillManager::resetInstance();

echo "=== Tutorial 01: Getting Started with Agent Skills ===\n\n";

// ─── Step 1: Initialize the SkillManager ───
echo "Step 1: Initialize the SkillManager\n";
echo str_repeat('-', 50) . "\n";

// The SkillManager is the central entry point for all skill operations.
// Point it at a directory containing skill folders.
$skillsDir = __DIR__ . '/../../../skills';
$manager = new SkillManager($skillsDir);

echo "Skills directory: {$skillsDir}\n\n";

// ─── Step 2: Discover available skills ───
echo "Step 2: Discover available skills\n";
echo str_repeat('-', 50) . "\n";

// discover() scans the skills directory and loads all valid skills.
$skills = $manager->discover();

echo "Found " . count($skills) . " skills:\n";
foreach ($skills as $name => $skill) {
    echo "  - {$name}: {$skill->getDescription()}\n";
}
echo "\n";

// ─── Step 3: Get a specific skill ───
echo "Step 3: Get a specific skill\n";
echo str_repeat('-', 50) . "\n";

// Retrieve a skill by name (loads on demand if not yet discovered)
$codeReview = $manager->get('code-review');
echo "Name: {$codeReview->getName()}\n";
echo "Description: {$codeReview->getDescription()}\n";
echo "Path: {$codeReview->getPath()}\n";
echo "Scripts: " . (count($codeReview->getScripts()) > 0
    ? implode(', ', $codeReview->getScripts())
    : 'none') . "\n";
echo "References: " . (count($codeReview->getReferences()) > 0
    ? implode(', ', $codeReview->getReferences())
    : 'none') . "\n";
echo "\n";

// ─── Step 4: View skill instructions ───
echo "Step 4: View skill instructions (first 200 chars)\n";
echo str_repeat('-', 50) . "\n";

$instructions = $codeReview->getInstructions();
echo substr($instructions, 0, 200) . "...\n\n";

// ─── Step 5: List skill summaries ───
echo "Step 5: List skill summaries (for progressive disclosure)\n";
echo str_repeat('-', 50) . "\n";

// Summaries provide a lightweight view without loading full instructions.
// This is used for the progressive disclosure pattern.
$summaries = $manager->summaries();
foreach ($summaries as $summary) {
    echo "  [{$summary['name']}] {$summary['description']}\n";
}
echo "\n";

// ─── Step 6: Search for skills ───
echo "Step 6: Search for skills\n";
echo str_repeat('-', 50) . "\n";

$results = $manager->search('code');
echo "Search 'code': " . count($results) . " result(s)\n";
foreach ($results as $name => $s) {
    echo "  - {$name}\n";
}

$results = $manager->search('test');
echo "Search 'test': " . count($results) . " result(s)\n";
foreach ($results as $name => $s) {
    echo "  - {$name}\n";
}
echo "\n";

echo "Tutorial complete! You've learned how to discover, load, and search skills.\n";
echo "Next: Tutorial 02 - Understanding Skills (anatomy of a skill)\n";
