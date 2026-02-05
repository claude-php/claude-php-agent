<?php

declare(strict_types=1);

/**
 * Tutorial 03: Skill Resolution
 *
 * Learn how skills are matched to queries using relevance scoring.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;

SkillManager::resetInstance();

echo "=== Tutorial 03: Skill Resolution ===\n\n";

$skillsDir = __DIR__ . '/../../../skills';
$manager = new SkillManager($skillsDir);
$manager->discover();

// ─── Step 1: Basic resolution ───
echo "Step 1: Basic resolution\n";
echo str_repeat('-', 50) . "\n";

// resolve() finds skills that match a natural language query.
$query = "review my PHP code for quality issues";
echo "Query: \"{$query}\"\n";
$skills = $manager->resolve($query);
echo "Matched " . count($skills) . " skill(s):\n";
foreach ($skills as $s) {
    echo "  - {$s->getName()}: {$s->getDescription()}\n";
}
echo "\n";

// ─── Step 2: Single best match ───
echo "Step 2: Single best match\n";
echo str_repeat('-', 50) . "\n";

// resolveOne() returns only the best matching skill.
$queries = [
    "review code" => null,
    "test API" => null,
    "analyze data" => null,
    "refactor PHP" => null,
    "write docs" => null,
];

foreach ($queries as $q => &$result) {
    $best = $manager->resolveOne($q);
    $result = $best ? $best->getName() : '(none)';
    echo "  \"{$q}\" => {$result}\n";
}
echo "\n";

// ─── Step 3: Relevance scoring ───
echo "Step 3: Relevance scoring\n";
echo str_repeat('-', 50) . "\n";

// Each skill is scored against the query. Higher scores = better match.
$resolver = $manager->getResolver();

echo "Scores for \"review code quality\":\n";
$scored = $resolver->resolveWithScores("review code quality", 0.0);
foreach ($scored as $entry) {
    $bar = str_repeat('█', (int)($entry['score'] * 40));
    printf("  %-25s %.3f %s\n", $entry['skill']->getName(), $entry['score'], $bar);
}
echo "\n";

// ─── Step 4: Threshold control ───
echo "Step 4: Threshold control\n";
echo str_repeat('-', 50) . "\n";

// The threshold filters out low-relevance matches.
$thresholds = [0.0, 0.1, 0.2, 0.3, 0.5];
$query = "code review quality analysis";

foreach ($thresholds as $threshold) {
    $results = $manager->resolve($query, $threshold);
    echo "  Threshold {$threshold}: " . count($results) . " match(es)\n";
}
echo "\n";

// ─── Step 5: Match signals ───
echo "Step 5: Understanding match signals\n";
echo str_repeat('-', 50) . "\n";

// The resolver uses three signals:
echo "Signal weights:\n";
echo "  1. Name match     (0.8) - query contains skill name\n";
echo "  2. Description    (0.5) - query words found in description\n";
echo "  3. Tag match      (0.3) - query contains a skill tag\n\n";

// Demonstrate each signal
$tests = [
    'code-review' => 'Direct name match (highest score)',
    'review PHP security' => 'Description match (medium score)',
    'php quality' => 'Tag/word match (lower score)',
];

foreach ($tests as $q => $explanation) {
    $best = $manager->resolveOne($q);
    $resolver = $manager->getResolver();
    $scored = $resolver->resolveWithScores($q, 0.0);
    $topScore = !empty($scored) ? $scored[0]['score'] : 0;
    echo "  \"{$q}\"\n";
    echo "    Best: " . ($best ? $best->getName() : 'none') . " (score: {$topScore})\n";
    echo "    Signal: {$explanation}\n\n";
}

// ─── Step 6: Resolve by exact name ───
echo "Step 6: Resolve by exact name\n";
echo str_repeat('-', 50) . "\n";

$skill = $resolver->resolveByName('code-review');
echo "resolveByName('code-review'): " . ($skill ? $skill->getName() : 'null') . "\n";

$skill = $resolver->resolveByName('nonexistent');
echo "resolveByName('nonexistent'): " . ($skill ? $skill->getName() : 'null') . "\n\n";

echo "Tutorial complete! You understand how skill resolution works.\n";
echo "Next: Tutorial 04 - Prompt Composition\n";
