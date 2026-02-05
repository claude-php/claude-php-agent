<?php

declare(strict_types=1);

/**
 * Agent Skills - Agent Integration Example
 *
 * Demonstrates how to integrate skills with the agent system
 * using the SkillAwareAgent trait.
 *
 * Note: This example shows the integration pattern without
 * requiring an API key. For actual agent execution with skills,
 * set ANTHROPIC_API_KEY environment variable.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillPromptComposer;
use ClaudeAgents\Skills\SkillRegistry;

SkillManager::resetInstance();

echo "=== Agent Skills: Agent Integration ===\n\n";

// Initialize skills
$skillsDir = __DIR__ . '/../../skills';
$manager = new SkillManager($skillsDir);
$manager->discover();

echo "Available skills: " . $manager->count() . "\n\n";

// Simulate the agent integration pattern
echo "--- Simulating skill-aware agent workflow ---\n\n";

// Step 1: User sends a message
$userMessage = "Review this PHP code for security issues and suggest improvements";
echo "User: {$userMessage}\n\n";

// Step 2: Auto-resolve relevant skills
$resolved = $manager->resolve($userMessage);
echo "Auto-resolved " . count($resolved) . " skill(s):\n";
foreach ($resolved as $s) {
    echo "  - {$s->getName()}: {$s->getDescription()}\n";
}
echo "\n";

// Step 3: Compose enhanced system prompt
$composer = new SkillPromptComposer();
$basePrompt = "You are an expert PHP developer assistant.";
$enhancedPrompt = $composer->composeWithDiscovery(
    $basePrompt,
    $resolved,
    $manager->summaries()
);

echo "Enhanced prompt length: " . strlen($enhancedPrompt) . " chars\n";
echo "Contains skill instructions: " . (str_contains($enhancedPrompt, 'Active Skills') ? 'yes' : 'no') . "\n";
echo "Contains available index: " . (str_contains($enhancedPrompt, 'Available Skills') ? 'yes' : 'no') . "\n\n";

// Step 4: Show the prompt structure
echo "--- Prompt structure ---\n";
$sections = explode("\n## ", $enhancedPrompt);
foreach ($sections as $i => $section) {
    $title = strtok($section, "\n");
    $lineCount = count(explode("\n", $section));
    if ($i === 0) {
        echo "  Base prompt ({$lineCount} lines)\n";
    } else {
        echo "  ## {$title} ({$lineCount} lines)\n";
    }
}
echo "\n";

// Step 5: Dynamic skill loading (adding skills mid-conversation)
echo "--- Dynamic skill loading ---\n";
$dynamicSkill = new Skill(
    metadata: new SkillMetadata(
        name: 'project-context',
        description: 'Project-specific context and conventions',
    ),
    instructions: "# Project Context\n\nThis project uses PSR-12 coding standard.\nAll classes must be final unless designed for inheritance.",
    path: '',
);
$manager->register($dynamicSkill);

echo "Added dynamic skill: {$dynamicSkill->getName()}\n";
echo "Total skills: {$manager->count()}\n";

// Re-resolve with more context
$resolved = $manager->resolve("review code following project conventions");
echo "Re-resolved: " . count($resolved) . " skill(s)\n";
foreach ($resolved as $s) {
    echo "  - {$s->getName()}\n";
}
echo "\n";

echo "Agent integration pattern demonstrated successfully.\n";
