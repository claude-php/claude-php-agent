<?php

declare(strict_types=1);

/**
 * Tutorial 07: Advanced Patterns
 *
 * Learn advanced patterns: multiple paths, dynamic skills,
 * registry manipulation, and agent integration patterns.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillPromptComposer;
use ClaudeAgents\Skills\SkillRegistry;
use ClaudeAgents\Skills\SkillResolver;

SkillManager::resetInstance();

echo "=== Tutorial 07: Advanced Patterns ===\n\n";

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

// ─── Pattern 1: Multiple skill paths ───
echo "Pattern 1: Multiple skill paths\n";
echo str_repeat('-', 50) . "\n";

// You can load skills from multiple directories.
// This allows separating built-in skills from custom/project skills.
$builtinDir = __DIR__ . '/../../../skills';
$projectDir = sys_get_temp_dir() . '/project-skills-' . uniqid();
$teamDir = sys_get_temp_dir() . '/team-skills-' . uniqid();

// Create project-specific skills
mkdir($projectDir . '/deploy-config', 0755, true);
file_put_contents($projectDir . '/deploy-config/SKILL.md', FrontmatterParser::generate([
    'name' => 'deploy-config',
    'description' => 'Project-specific deployment configuration',
]) . "\n# Deploy Config\n\nUse Docker Compose for local, K8s for production.\n");

mkdir($teamDir . '/team-conventions', 0755, true);
file_put_contents($teamDir . '/team-conventions/SKILL.md', FrontmatterParser::generate([
    'name' => 'team-conventions',
    'description' => 'Team coding conventions and standards',
]) . "\n# Team Conventions\n\nUse PSR-12. All methods must have return types.\n");

$manager = new SkillManager($builtinDir);
$manager->addPath($projectDir);
$manager->addPath($teamDir);

$all = $manager->all();
echo "Skills from 3 paths: " . count($all) . "\n";
echo "Built-in: " . count(array_filter($all, fn($s) => str_contains($s->getPath(), 'skills/'))) . "\n";
echo "Project: " . (isset($all['deploy-config']) ? 'yes' : 'no') . "\n";
echo "Team: " . (isset($all['team-conventions']) ? 'yes' : 'no') . "\n\n";

// ─── Pattern 2: Dynamic skill composition ───
echo "Pattern 2: Dynamic skill composition\n";
echo str_repeat('-', 50) . "\n";

// Add context-specific skills at runtime based on the project.
$contextSkill = new Skill(
    metadata: new SkillMetadata(
        name: 'project-context',
        description: 'Context about the current project',
    ),
    instructions: "# Project: MyApp\n\n- Framework: Laravel 11\n- PHP: 8.3\n- DB: PostgreSQL\n- Cache: Redis",
    path: '',
);
$manager->register($contextSkill);

echo "Added project context skill.\n";
echo "Total skills: {$manager->count()}\n\n";

// ─── Pattern 3: Standalone registry and resolver ───
echo "Pattern 3: Standalone registry and resolver\n";
echo str_repeat('-', 50) . "\n";

// You can use the registry and resolver independently.
$registry = new SkillRegistry();

$skills = [
    new Skill(new SkillMetadata('frontend', 'Frontend development with React'), 'Use React 18.', ''),
    new Skill(new SkillMetadata('backend', 'Backend development with PHP'), 'Use PHP 8.3.', ''),
    new Skill(new SkillMetadata('devops', 'DevOps and infrastructure'), 'Use Terraform.', ''),
];
$registry->registerMany($skills);

$resolver = new SkillResolver($registry);

echo "Registry: " . $registry->count() . " skills\n";
$result = $resolver->resolveOne('build a React component');
echo "Resolve 'build a React component': " . ($result ? $result->getName() : 'null') . "\n";
$result = $resolver->resolveOne('PHP API endpoint');
echo "Resolve 'PHP API endpoint': " . ($result ? $result->getName() : 'null') . "\n\n";

// ─── Pattern 4: Skill-enhanced agent workflow ───
echo "Pattern 4: Skill-enhanced agent workflow\n";
echo str_repeat('-', 50) . "\n";

// This pattern shows how skills integrate with an agent loop.
$composer = new SkillPromptComposer();
$basePrompt = "You are an expert PHP developer.";

// Simulate a conversation turn
$messages = [
    "Help me review this code for security issues",
    "Now write some tests for the fixed code",
    "Deploy the changes to staging",
];

foreach ($messages as $i => $message) {
    echo "Turn " . ($i + 1) . ": \"{$message}\"\n";

    // Auto-resolve skills for this turn
    $resolved = $manager->resolve($message);
    $skillNames = array_map(fn($s) => $s->getName(), $resolved);
    echo "  Skills: " . (empty($skillNames) ? 'none' : implode(', ', $skillNames)) . "\n";

    // Compose prompt for this turn
    $prompt = $composer->composeWithDiscovery($basePrompt, $resolved, $manager->summaries());
    echo "  Prompt: " . strlen($prompt) . " chars\n\n";
}

// ─── Pattern 5: Mode skills ───
echo "Pattern 5: Mode skills (behavioral modifiers)\n";
echo str_repeat('-', 50) . "\n";

$modeSkill = new Skill(
    metadata: new SkillMetadata(
        name: 'strict-mode',
        description: 'Enable strict code review mode',
        mode: true,
    ),
    instructions: "# Strict Mode\n\nWhen active:\n- Flag all potential issues\n- Require explicit type declarations\n- Enforce immutability where possible",
    path: '',
);
$manager->register($modeSkill);

$registry = $manager->getRegistry();
$modes = $registry->getModes();
echo "Available modes: " . implode(', ', array_keys($modes)) . "\n";
echo "Mode 'strict-mode' instructions: " . substr($modeSkill->getInstructions(), 0, 60) . "...\n\n";

// ─── Pattern 6: Skill filtering ───
echo "Pattern 6: Filtering skills\n";
echo str_repeat('-', 50) . "\n";

$registry = $manager->getRegistry();
echo "All skills: {$registry->count()}\n";
echo "Auto-invocable: " . count($registry->getAutoInvocable()) . "\n";
echo "Modes: " . count($registry->getModes()) . "\n";
echo "Matching 'code': " . count($registry->search('code')) . "\n\n";

// Clean up
$cleanup($projectDir);
$cleanup($teamDir);

echo "Tutorial complete! You've learned advanced Agent Skills patterns.\n";
echo "Congratulations on completing the Agent Skills tutorial series!\n";
