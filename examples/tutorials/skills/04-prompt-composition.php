<?php

declare(strict_types=1);

/**
 * Tutorial 04: Prompt Composition
 *
 * Learn how to build skill-enhanced prompts with progressive disclosure.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillPromptComposer;

SkillManager::resetInstance();

echo "=== Tutorial 04: Prompt Composition ===\n\n";

$skillsDir = __DIR__ . '/../../../skills';
$manager = new SkillManager($skillsDir);
$manager->discover();

$composer = new SkillPromptComposer();

// ─── Step 1: Simple composition ───
echo "Step 1: Simple composition\n";
echo str_repeat('-', 50) . "\n";

// Compose a prompt with a single skill's instructions.
$basePrompt = "You are a helpful PHP developer assistant.";
$skills = [$manager->get('code-review')];

$prompt = $composer->compose($basePrompt, $skills);
echo "Prompt length: " . strlen($prompt) . " chars\n";
echo "First 300 chars:\n" . substr($prompt, 0, 300) . "...\n\n";

// ─── Step 2: Skills index ───
echo "Step 2: Skills index (lightweight summary)\n";
echo str_repeat('-', 50) . "\n";

// Build a compact index of all available skills.
// This is the "table of contents" in the progressive disclosure metaphor.
$summaries = $manager->summaries();
$index = $composer->buildSkillsIndex($summaries);
echo $index . "\n\n";

// ─── Step 3: Progressive disclosure ───
echo "Step 3: Progressive disclosure composition\n";
echo str_repeat('-', 50) . "\n";

// composeWithDiscovery() includes:
// - Full instructions for loaded skills
// - Lightweight summaries for available (unloaded) skills
$loadedSkills = [$manager->get('code-review')];

$fullPrompt = $composer->composeWithDiscovery(
    $basePrompt,
    $loadedSkills,
    $summaries
);

echo "Full prompt length: " . strlen($fullPrompt) . " chars\n";
echo "Contains 'Active Skills': " . (str_contains($fullPrompt, 'Active Skills') ? 'yes' : 'no') . "\n";
echo "Contains 'Available Skills': " . (str_contains($fullPrompt, 'Available Skills') ? 'yes' : 'no') . "\n\n";

// ─── Step 4: Auto-resolve and compose ───
echo "Step 4: Auto-resolve and compose\n";
echo str_repeat('-', 50) . "\n";

// In practice, you resolve skills from the user's message,
// then compose the prompt with those skills.
$userMessage = "Can you help me refactor this legacy PHP code?";
echo "User: \"{$userMessage}\"\n";

$resolved = $manager->resolve($userMessage);
echo "Resolved: " . implode(', ', array_map(fn($s) => $s->getName(), $resolved)) . "\n";

$autoPrompt = $composer->composeWithDiscovery(
    $basePrompt,
    $resolved,
    $summaries
);
echo "Auto prompt length: " . strlen($autoPrompt) . " chars\n\n";

// ─── Step 5: Skills prompt generation ───
echo "Step 5: Built-in skills prompt\n";
echo str_repeat('-', 50) . "\n";

// The manager can generate a complete skills prompt with
// categorized sections (auto-invocable, modes, manual).
$skillsPrompt = $manager->generateSkillsPrompt();
echo "Skills prompt length: " . strlen($skillsPrompt) . " chars\n";
echo "First 400 chars:\n" . substr($skillsPrompt, 0, 400) . "...\n\n";

// ─── Step 6: Empty composition ───
echo "Step 6: Graceful empty handling\n";
echo str_repeat('-', 50) . "\n";

// When no skills match, the base prompt is returned unchanged.
$emptyPrompt = $composer->compose($basePrompt, []);
echo "No skills: prompt = base prompt? " . ($emptyPrompt === $basePrompt ? 'yes' : 'no') . "\n";

$emptyIndex = $composer->buildSkillsIndex([]);
echo "No summaries: index = empty? " . ($emptyIndex === '' ? 'yes' : 'no') . "\n\n";

echo "Tutorial complete! You understand skill prompt composition.\n";
echo "Next: Tutorial 05 - Creating Custom Skills\n";
