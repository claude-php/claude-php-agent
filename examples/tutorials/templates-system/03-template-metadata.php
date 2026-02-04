<?php

/**
 * Tutorial 3: Template Metadata
 * 
 * Deep dive into template metadata and requirements.
 * 
 * Time: ~10 minutes
 * Level: Beginner
 * 
 * Topics:
 * - Reading template metadata
 * - Understanding requirements
 * - Checking compatibility
 * - Using metadata for selection
 * - Custom metadata fields
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 3: Template Metadata                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$manager = TemplateManager::getInstance();

// Step 1: Understanding Metadata Structure
echo "Step 1: Understanding Metadata Structure\n";
echo str_repeat('─', 80) . "\n";

$template = $manager->getByName('ReAct Agent');

echo "Template: {$template->getName()}\n\n";

echo "📋 Basic Fields:\n";
echo "  • ID: {$template->getId()}\n";
echo "  • Name: {$template->getName()}\n";
echo "  • Version: {$template->getVersion()}\n";
echo "  • Author: {$template->getAuthor()}\n";
echo "  • Category: {$template->getCategory()}\n";
echo "  • Tags: " . implode(', ', $template->getTags()) . "\n";
echo "  • Last Tested: {$template->getLastTestedVersion()}\n\n";

echo "📝 Description:\n";
echo "  " . wordwrap($template->getDescription(), 76, "\n  ") . "\n\n";

echo "📊 Metadata Fields:\n";
$metadata = $template->getMetadata();
foreach ($metadata as $key => $value) {
    if (is_array($value)) {
        echo "  • {$key}: " . implode(', ', $value) . "\n";
    } else {
        echo "  • {$key}: {$value}\n";
    }
}
echo "\n";

// Step 2: Requirements Validation
echo "Step 2: Requirements Validation\n";
echo str_repeat('─', 80) . "\n";

$requirements = $template->getRequirements();
echo "Template Requirements:\n\n";

echo "PHP Version:\n";
$requiredPhp = $requirements['php'] ?? 'Not specified';
$currentPhp = PHP_VERSION;
echo "  Required: {$requiredPhp}\n";
echo "  Current: {$currentPhp}\n";
$phpOk = version_compare($currentPhp, str_replace('>=', '', $requiredPhp), '>=');
echo "  Status: " . ($phpOk ? '✅ Compatible' : '❌ Incompatible') . "\n\n";

echo "Required Extensions:\n";
$extensions = $requirements['extensions'] ?? [];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "  {$status} {$ext}\n";
}
echo "\n";

echo "Required Packages:\n";
foreach ($requirements['packages'] ?? [] as $package) {
    echo "  • {$package}\n";
}
echo "\n";

// Step 3: Using Metadata for Selection
echo "Step 3: Using Metadata for Selection\n";
echo str_repeat('─', 80) . "\n";

echo "Finding quick-setup templates (≤10 minutes)...\n";
$allTemplates = $manager->loadAll();
$quickSetup = array_filter($allTemplates, function($t) {
    $setup = $t->getMetadata('estimated_setup');
    if ($setup && preg_match('/(\d+)/', $setup, $matches)) {
        return (int)$matches[1] <= 10;
    }
    return false;
});

echo "Found " . count($quickSetup) . " quick-setup templates:\n";
foreach ($quickSetup as $t) {
    $icon = $t->getMetadata('icon') ?: '📄';
    $time = $t->getMetadata('estimated_setup');
    echo "  {$icon} {$t->getName()} - {$time}\n";
}
echo "\n";

echo "Finding templates for specific use case: 'chatbot'...\n";
$chatbotTemplates = array_filter($allTemplates, function($t) {
    $useCases = $t->getMetadata('use_cases') ?: [];
    foreach ($useCases as $useCase) {
        if (stripos($useCase, 'chat') !== false) {
            return true;
        }
    }
    return false;
});

echo "Found " . count($chatbotTemplates) . " templates with chat use cases:\n";
foreach ($chatbotTemplates as $t) {
    echo "  • {$t->getName()}\n";
}
echo "\n";

// Step 4: Comparing Templates
echo "Step 4: Comparing Templates Side-by-Side\n";
echo str_repeat('─', 80) . "\n";

$template1 = $manager->getByName('Basic Agent');
$template2 = $manager->getByName('ReAct Agent');

echo "Comparing: {$template1->getName()} vs {$template2->getName()}\n\n";

$comparison = [
    'Difficulty' => [
        $template1->getMetadata('difficulty'),
        $template2->getMetadata('difficulty')
    ],
    'Setup Time' => [
        $template1->getMetadata('estimated_setup'),
        $template2->getMetadata('estimated_setup')
    ],
    'Max Iterations' => [
        $template1->getConfig()['max_iterations'],
        $template2->getConfig()['max_iterations']
    ],
    'Tag Count' => [
        count($template1->getTags()),
        count($template2->getTags())
    ]
];

foreach ($comparison as $aspect => $values) {
    echo sprintf("  %-20s %-20s vs %-20s\n", $aspect . ':', $values[0], $values[1]);
}
echo "\n";

// Step 5: Accessing Custom Metadata
echo "Step 5: Accessing Custom Metadata\n";
echo str_repeat('─', 80) . "\n";

echo "Templates can have custom metadata fields.\n\n";

foreach ($allTemplates as $template) {
    $icon = $template->getMetadata('icon');
    $difficulty = $template->getMetadata('difficulty');
    
    if ($icon && $difficulty) {
        echo "  {$icon} {$template->getName()}\n";
        echo "     Difficulty: {$difficulty}\n";
        echo "     Setup: {$template->getMetadata('estimated_setup')}\n\n";
        
        if (count($allTemplates) > 5) break; // Show first 5 for brevity
    }
}

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 3 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ Template metadata structure\n";
echo "  ✓ Validating requirements\n";
echo "  ✓ Using metadata for selection\n";
echo "  ✓ Comparing templates\n";
echo "  ✓ Custom metadata fields\n\n";

echo "Key Concepts:\n";
echo "  💡 Metadata helps you choose the right template\n";
echo "  💡 Always check requirements before instantiation\n";
echo "  💡 Difficulty levels guide learning path\n";
echo "  💡 Setup time helps planning\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 4: php 04-advanced-search.php\n";
echo "  → Master advanced search techniques\n";
