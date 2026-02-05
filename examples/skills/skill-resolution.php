<?php

declare(strict_types=1);

/**
 * Agent Skills - Skill Resolution Example
 *
 * Demonstrates how to resolve skills based on natural language queries.
 * The resolver scores each skill against the query and returns
 * the best matches above a configurable threshold.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;

SkillManager::resetInstance();

echo "=== Agent Skills: Skill Resolution ===\n\n";

$skillsDir = __DIR__ . '/../../skills';
$manager = new SkillManager($skillsDir);
$manager->discover();

// Test various queries
$queries = [
    'review my PHP code for quality',
    'test the REST API endpoints',
    'analyze the dataset and generate statistics',
    'refactor this legacy PHP code',
    'write documentation for this module',
];

foreach ($queries as $query) {
    echo "Query: \"{$query}\"\n";

    // Resolve best single match
    $best = $manager->resolveOne($query);
    if ($best) {
        echo "  Best match: {$best->getName()} (desc: {$best->getDescription()})\n";
    } else {
        echo "  No match found\n";
    }

    // Resolve all matches with scores
    $resolver = $manager->getResolver();
    $registry = $manager->getRegistry();
    $scored = $resolver->resolveWithScores($query);
    if (!empty($scored)) {
        echo "  All matches:\n";
        foreach ($scored as $entry) {
            echo "    - {$entry['skill']->getName()}: score={$entry['score']}\n";
        }
    }
    echo "\n";
}
