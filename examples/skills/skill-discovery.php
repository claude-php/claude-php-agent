<?php

declare(strict_types=1);

/**
 * Agent Skills - Skill Discovery Example
 *
 * Demonstrates how to discover and list all available skills
 * from the bundled skills directory.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;

SkillManager::resetInstance();

echo "=== Agent Skills: Skill Discovery ===\n\n";

// Initialize the manager with the bundled skills directory
$skillsDir = __DIR__ . '/../../skills';
$manager = new SkillManager($skillsDir);

// Discover all skills
$skills = $manager->discover();
echo "Discovered " . count($skills) . " skills:\n\n";

foreach ($skills as $name => $skill) {
    echo "  [{$name}]\n";
    echo "    Description: {$skill->getDescription()}\n";
    echo "    Path: {$skill->getPath()}\n";
    $scripts = $skill->getScripts();
    $references = $skill->getReferences();
    if (!empty($scripts)) {
        echo "    Scripts: " . implode(', ', $scripts) . "\n";
    }
    if (!empty($references)) {
        echo "    References: " . implode(', ', $references) . "\n";
    }
    echo "\n";
}

// Show summaries for progressive disclosure
echo "--- Skill Summaries (for progressive disclosure) ---\n\n";
$summaries = $manager->summaries();
foreach ($summaries as $summary) {
    echo "  - {$summary['name']}: {$summary['description']}\n";
}
echo "\n";
