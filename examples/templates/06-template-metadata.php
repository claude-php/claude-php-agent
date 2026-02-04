<?php

/**
 * Example 6: Working with Template Metadata
 * 
 * This example demonstrates:
 * - Accessing template metadata
 * - Understanding template requirements
 * - Using metadata for selection
 * - Modifying template metadata
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;

echo "=== Template System: Metadata Management ===\n\n";

$manager = TemplateManager::getInstance();

// Example 1: Comprehensive metadata inspection
echo "=== Example 1: Full Template Metadata ===\n";
$template = $manager->getByName('ReAct Agent');

echo "Template: {$template->getName()}\n";
echo str_repeat('-', 80) . "\n\n";

// Basic info
echo "📋 Basic Information:\n";
echo "  ID: {$template->getId()}\n";
echo "  Name: {$template->getName()}\n";
echo "  Version: {$template->getVersion()}\n";
echo "  Author: {$template->getAuthor()}\n";
echo "  Category: {$template->getCategory()}\n";
echo "  Tags: " . implode(', ', $template->getTags()) . "\n";
echo "  Last Tested: {$template->getLastTestedVersion()}\n\n";

// Description
echo "📝 Description:\n";
echo "  " . $template->getDescription() . "\n\n";

// Requirements
echo "⚙️  Requirements:\n";
$requirements = $template->getRequirements();
if (isset($requirements['php'])) {
    echo "  PHP: {$requirements['php']}\n";
}
if (isset($requirements['extensions'])) {
    echo "  Extensions: " . implode(', ', $requirements['extensions']) . "\n";
}
if (isset($requirements['packages'])) {
    echo "  Packages: " . implode(', ', $requirements['packages']) . "\n";
}
echo "\n";

// Metadata
echo "📊 Metadata:\n";
$metadata = $template->getMetadata();
foreach ($metadata as $key => $value) {
    if (is_array($value)) {
        echo "  {$key}:\n";
        foreach ($value as $item) {
            echo "    • {$item}\n";
        }
    } else {
        echo "  {$key}: {$value}\n";
    }
}
echo "\n";

// Configuration
echo "⚡ Configuration:\n";
$config = $template->getConfig();
foreach ($config as $key => $value) {
    if (is_scalar($value)) {
        echo "  {$key}: {$value}\n";
    } elseif (is_array($value) && empty($value)) {
        echo "  {$key}: []\n";
    }
}
echo "\n";

// Example 2: Filtering by metadata
echo "=== Example 2: Filter by Metadata ===\n\n";

// Find quick-setup templates
echo "Quick Setup Templates (≤10 minutes):\n";
$allTemplates = $manager->loadAll();
$quickTemplates = array_filter($allTemplates, function($template) {
    $setup = $template->getMetadata('estimated_setup');
    return $setup && str_contains($setup, '5 minutes');
});

foreach ($quickTemplates as $t) {
    $icon = $t->getMetadata('icon') ?: '📄';
    echo "  {$icon} {$t->getName()} - {$t->getMetadata('estimated_setup')}\n";
}
echo "\n";

// Find templates by icon
echo "Templates with specific icons:\n";
$brainTemplates = array_filter($allTemplates, function($template) {
    return $template->getMetadata('icon') === '🧠';
});

foreach ($brainTemplates as $t) {
    echo "  🧠 {$t->getName()}\n";
}
echo "\n";

// Example 3: Check requirements
echo "=== Example 3: Requirements Validation ===\n\n";

$template = $manager->getByName('Async Batch Processor');
echo "Template: {$template->getName()}\n";
echo "Checking requirements...\n\n";

$requirements = $template->getRequirements();

// Check PHP version
$requiredPhp = $requirements['php'] ?? '>=8.1';
$currentPhp = PHP_VERSION;
echo "PHP Version:\n";
echo "  Required: {$requiredPhp}\n";
echo "  Current: {$currentPhp}\n";

// Check extensions
echo "\nRequired Extensions:\n";
$extensions = $requirements['extensions'] ?? [];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '✅' : '❌';
    echo "  {$status} {$ext}\n";
}

// Check packages
echo "\nRequired Packages:\n";
$packages = $requirements['packages'] ?? [];
foreach ($packages as $package) {
    echo "  • {$package}\n";
}
echo "\n";

// Example 4: Modifying template metadata
echo "=== Example 4: Create Template with Custom Metadata ===\n\n";

$customTemplate = Template::fromArray([
    'id' => 'custom-001',
    'name' => 'Custom Research Agent',
    'description' => 'Specialized agent for academic research tasks',
    'category' => 'custom',
    'tags' => ['research', 'academic', 'specialized'],
    'version' => '1.0.0',
    'author' => 'Research Team',
    'requirements' => [
        'php' => '>=8.1',
        'extensions' => ['json', 'curl'],
        'packages' => ['claude-php/agent']
    ],
    'metadata' => [
        'icon' => '🔬',
        'difficulty' => 'intermediate',
        'estimated_setup' => '20 minutes',
        'use_cases' => [
            'Literature review',
            'Citation management',
            'Research synthesis'
        ],
        'target_audience' => 'Researchers and academics',
        'domain' => 'Academic Research'
    ],
    'config' => [
        'agent_type' => 'ReactAgent',
        'model' => 'claude-sonnet-4-5',
        'max_iterations' => 15
    ]
]);

echo "Created custom template:\n";
echo "  Name: {$customTemplate->getName()}\n";
echo "  Icon: {$customTemplate->getMetadata('icon')}\n";
echo "  Domain: {$customTemplate->getMetadata('domain')}\n";
echo "  Target: {$customTemplate->getMetadata('target_audience')}\n\n";

// Validate template
if ($customTemplate->isValid()) {
    echo "✅ Template is valid!\n";
} else {
    echo "❌ Validation errors:\n";
    foreach ($customTemplate->getErrors() as $error) {
        echo "  • {$error}\n";
    }
}
echo "\n";

// Example 5: Use cases by template
echo "=== Example 5: Browse Use Cases ===\n\n";

$templates = $manager->search(category: 'specialized');
foreach ($templates as $template) {
    echo "{$template->getName()}:\n";
    $useCases = $template->getMetadata('use_cases') ?: [];
    if (!empty($useCases)) {
        foreach ($useCases as $useCase) {
            echo "  • {$useCase}\n";
        }
    } else {
        echo "  No use cases defined\n";
    }
    echo "\n";
}

echo "✅ Example completed!\n";
echo "\n💡 Metadata Tips:\n";
echo "  - Use difficulty to choose appropriate templates\n";
echo "  - Check requirements before instantiation\n";
echo "  - Use cases help identify right template for your task\n";
echo "  - Custom metadata fields are fully supported\n";
