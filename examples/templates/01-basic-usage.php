<?php

/**
 * Example 1: Basic Template Usage
 * 
 * This example demonstrates:
 * - Loading all templates
 * - Listing templates with basic info
 * - Getting template details
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "=== Template System: Basic Usage ===\n\n";

// Initialize template manager
$manager = TemplateManager::getInstance();

echo "Templates Directory: " . $manager->getTemplatesPath() . "\n\n";

// Get total count
$count = $manager->count();
echo "Total Templates: {$count}\n\n";

// Load all templates
echo "Loading all templates...\n";
$templates = $manager->loadAll();

echo "\nAvailable Templates:\n";
echo str_repeat('-', 80) . "\n";

foreach ($templates as $template) {
    $icon = $template->getMetadata('icon') ?: '📄';
    $difficulty = $template->getMetadata('difficulty') ?: 'N/A';
    
    echo sprintf(
        "%s  %-30s  [%s]  %s\n",
        $icon,
        $template->getName(),
        $template->getCategory(),
        $difficulty
    );
}

echo str_repeat('-', 80) . "\n\n";

// Get detailed info for specific template
echo "=== Template Details: ReAct Agent ===\n";
$reactTemplate = $manager->getByName('ReAct Agent');

echo "ID: " . $reactTemplate->getId() . "\n";
echo "Name: " . $reactTemplate->getName() . "\n";
echo "Description: " . $reactTemplate->getDescription() . "\n";
echo "Category: " . $reactTemplate->getCategory() . "\n";
echo "Tags: " . implode(', ', $reactTemplate->getTags()) . "\n";
echo "Version: " . $reactTemplate->getVersion() . "\n";
echo "Author: " . $reactTemplate->getAuthor() . "\n";
echo "Difficulty: " . $reactTemplate->getMetadata('difficulty') . "\n";
echo "Setup Time: " . $reactTemplate->getMetadata('estimated_setup') . "\n";

echo "\nUse Cases:\n";
$useCases = $reactTemplate->getMetadata('use_cases') ?: [];
foreach ($useCases as $useCase) {
    echo "  - {$useCase}\n";
}

echo "\nConfiguration:\n";
$config = $reactTemplate->getConfig();
foreach ($config as $key => $value) {
    if (is_scalar($value)) {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n=== All Categories ===\n";
$categories = $manager->getCategories();
foreach ($categories as $category) {
    $categoryTemplates = $manager->getByCategory($category);
    echo sprintf("%-20s: %d templates\n", ucfirst($category), count($categoryTemplates));
}

echo "\n=== All Tags ===\n";
$tags = $manager->getAllTags();
echo "Available tags: " . implode(', ', $tags) . "\n";

echo "\n✅ Example completed!\n";
