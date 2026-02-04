<?php

/**
 * Tutorial 1: Discovering Templates
 * 
 * Learn how to browse and search the template catalog.
 * 
 * Time: ~5 minutes
 * Level: Beginner
 * 
 * Topics:
 * - Loading all templates
 * - Browsing by category
 * - Searching by tags
 * - Understanding template metadata
 * - Filtering by difficulty level
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 1: Discovering Templates                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Initialize Template Manager
echo "Step 1: Initialize Template Manager\n";
echo str_repeat('─', 80) . "\n";

$manager = TemplateManager::getInstance();
echo "✓ Template Manager initialized\n";
echo "  Templates Directory: {$manager->getTemplatesPath()}\n";
echo "  Total Templates: {$manager->count()}\n\n";

// Step 2: Browse All Templates
echo "Step 2: Browse All Templates\n";
echo str_repeat('─', 80) . "\n";

$allTemplates = $manager->loadAll();
echo "✓ Loaded {" . count($allTemplates) . "} templates\n\n";

echo "Template Catalog:\n";
foreach ($allTemplates as $template) {
    $icon = $template->getMetadata('icon') ?: '📄';
    $difficulty = $template->getMetadata('difficulty') ?: 'N/A';
    echo sprintf("  %s  %-35s [%-12s] %s\n", 
        $icon, 
        $template->getName(),
        $template->getCategory(),
        $difficulty
    );
}
echo "\n";

// Step 3: Explore Categories
echo "Step 3: Explore Categories\n";
echo str_repeat('─', 80) . "\n";

$categories = $manager->getCategories();
echo "✓ Found {" . count($categories) . "} categories\n\n";

foreach ($categories as $category) {
    $categoryTemplates = $manager->getByCategory($category);
    echo "📁 " . strtoupper($category) . " ({" . count($categoryTemplates) . "} templates)\n";
    
    foreach ($categoryTemplates as $template) {
        $icon = $template->getMetadata('icon') ?: '  ';
        $setupTime = $template->getMetadata('estimated_setup') ?: 'N/A';
        echo "   {$icon} {$template->getName()} - Setup: {$setupTime}\n";
    }
    echo "\n";
}

// Step 4: Browse by Tags
echo "Step 4: Browse by Tags\n";
echo str_repeat('─', 80) . "\n";

$allTags = $manager->getAllTags();
echo "✓ Found {" . count($allTags) . "} unique tags\n\n";

echo "Popular tags:\n";
$popularTags = ['beginner', 'advanced', 'conversation', 'rag', 'production'];
foreach ($popularTags as $tag) {
    if (in_array($tag, $allTags)) {
        $tagged = $manager->getByTags([$tag]);
        echo "  🏷️  {$tag} ({" . count($tagged) . "} templates)\n";
    }
}
echo "\n";

// Step 5: Filter by Difficulty
echo "Step 5: Filter by Difficulty Level\n";
echo str_repeat('─', 80) . "\n";

$difficulties = ['beginner', 'intermediate', 'advanced'];
foreach ($difficulties as $difficulty) {
    $filtered = array_filter($allTemplates, function($template) use ($difficulty) {
        return $template->getMetadata('difficulty') === $difficulty;
    });
    
    echo ucfirst($difficulty) . " Templates ({" . count($filtered) . "}):\n";
    foreach ($filtered as $template) {
        $icon = $template->getMetadata('icon') ?: '📄';
        echo "  {$icon} {$template->getName()}\n";
    }
    echo "\n";
}

// Step 6: Inspect Template Details
echo "Step 6: Inspect Template Details\n";
echo str_repeat('─', 80) . "\n";

$template = $manager->getByName('Basic Agent');
echo "Selected Template: {$template->getName()}\n\n";

echo "Basic Information:\n";
echo "  ID: {$template->getId()}\n";
echo "  Name: {$template->getName()}\n";
echo "  Version: {$template->getVersion()}\n";
echo "  Author: {$template->getAuthor()}\n";
echo "  Category: {$template->getCategory()}\n";
echo "  Tags: " . implode(', ', $template->getTags()) . "\n\n";

echo "Metadata:\n";
echo "  Icon: {$template->getMetadata('icon')}\n";
echo "  Difficulty: {$template->getMetadata('difficulty')}\n";
echo "  Setup Time: {$template->getMetadata('estimated_setup')}\n\n";

echo "Description:\n";
echo "  " . wordwrap($template->getDescription(), 76, "\n  ") . "\n\n";

echo "Use Cases:\n";
foreach ($template->getMetadata('use_cases') as $useCase) {
    echo "  • {$useCase}\n";
}
echo "\n";

echo "Configuration:\n";
$config = $template->getConfig();
echo "  Agent Type: {$config['agent_type']}\n";
echo "  Model: {$config['model']}\n";
echo "  Max Iterations: {$config['max_iterations']}\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 1 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ How to browse all available templates\n";
echo "  ✓ Template categories and organization\n";
echo "  ✓ Searching by tags and difficulty\n";
echo "  ✓ Understanding template metadata\n";
echo "  ✓ Inspecting template details\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 2: php 02-instantiating-agents.php\n";
echo "  → Learn how to create live agents from templates\n\n";

echo "Quick Tips:\n";
echo "  💡 Start with 'beginner' templates when learning\n";
echo "  💡 Check setup time before choosing templates\n";
echo "  💡 Read use cases to find the right template\n";
echo "  💡 Categories help narrow down your search\n";
