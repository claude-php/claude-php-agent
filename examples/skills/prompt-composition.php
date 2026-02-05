<?php

declare(strict_types=1);

/**
 * Agent Skills - Prompt Composition Example
 *
 * Demonstrates how to compose system prompts with skill instructions
 * using progressive disclosure.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillPromptComposer;

SkillManager::resetInstance();

echo "=== Agent Skills: Prompt Composition ===\n\n";

$skillsDir = __DIR__ . '/../../skills';
$manager = new SkillManager($skillsDir);
$manager->discover();
$composer = new SkillPromptComposer();

// 1. Skills index for progressive disclosure
echo "--- Skills Index (lightweight summary) ---\n";
$index = $composer->buildSkillsIndex($manager->summaries());
echo $index . "\n\n";

// 2. Compose prompt with a specific loaded skill
echo "--- Prompt with loaded skill ---\n";
$loadedSkills = [$manager->get('code-review')];
$prompt = $composer->compose('You are a helpful PHP assistant.', $loadedSkills);
echo substr($prompt, 0, 500) . "...\n\n";

// 3. Progressive disclosure: loaded + available
echo "--- Progressive disclosure prompt ---\n";
$loadedSkills = [$manager->get('code-review')];
$summaries = $manager->summaries();
$fullPrompt = $composer->composeWithDiscovery(
    'You are a helpful PHP assistant.',
    $loadedSkills,
    $summaries
);
echo substr($fullPrompt, 0, 800) . "...\n\n";

// 4. Auto-resolve skills from a user message
echo "--- Auto-resolve from user message ---\n";
$userMessage = "Can you review this code and check for security issues?";
echo "User: {$userMessage}\n";
$resolved = $manager->resolve($userMessage);
echo "Resolved " . count($resolved) . " skill(s):\n";
foreach ($resolved as $s) {
    echo "  - {$s->getName()}\n";
}
$autoPrompt = $composer->compose('You are a PHP code assistant.', $resolved);
echo "Generated prompt length: " . strlen($autoPrompt) . " chars\n";

// 5. Generate the full skills prompt
echo "\n--- Full skills prompt ---\n";
$skillsPrompt = $manager->generateSkillsPrompt();
echo substr($skillsPrompt, 0, 600) . "...\n";
