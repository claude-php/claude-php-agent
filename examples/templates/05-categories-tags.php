<?php

/**
 * Example 5: Browse by Categories and Tags
 * 
 * This example demonstrates:
 * - Browsing templates by category
 * - Filtering by tags
 * - Understanding template organization
 * - Finding templates for specific use cases
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "=== Template System: Categories and Tags ===\n\n";

$manager = TemplateManager::getInstance();

// Display all categories
echo "=== Available Categories ===\n";
$categories = $manager->getCategories();
echo "Found " . count($categories) . " categories:\n\n";

foreach ($categories as $category) {
    $templates = $manager->getByCategory($category);
    echo sprintf("📁 %-20s (%d templates)\n", strtoupper($category), count($templates));
    
    foreach ($templates as $template) {
        $icon = $template->getMetadata('icon') ?: '  ';
        echo sprintf("   %s %s\n", $icon, $template->getName());
    }
    echo "\n";
}

// Display all tags
echo "=== Available Tags ===\n";
$allTags = $manager->getAllTags();
echo "Found " . count($allTags) . " unique tags:\n";
echo implode(', ', $allTags) . "\n\n";

// Browse by specific category
echo "=== Browse: Chatbots Category ===\n";
$chatbots = $manager->getByCategory('chatbots');
echo "Found " . count($chatbots) . " chatbot templates:\n\n";

foreach ($chatbots as $template) {
    $icon = $template->getMetadata('icon') ?: '💬';
    $difficulty = $template->getMetadata('difficulty') ?: 'N/A';
    
    echo "{$icon} {$template->getName()}\n";
    echo "   Difficulty: {$difficulty}\n";
    echo "   Tags: " . implode(', ', $template->getTags()) . "\n";
    echo "   " . substr($template->getDescription(), 0, 80) . "...\n\n";
}

// Find templates by tag
echo "=== Find Templates by Tag: 'advanced' ===\n";
$advanced = $manager->getByTags(['advanced']);
echo "Found " . count($advanced) . " advanced templates:\n";

foreach ($advanced as $template) {
    echo "  - {$template->getName()} [{$template->getCategory()}]\n";
}
echo "\n";

// Find templates by multiple tags
echo "=== Find Templates with Tags: 'conversation' OR 'memory' ===\n";
$conversational = $manager->getByTags(['conversation', 'memory']);
echo "Found " . count($conversational) . " templates:\n";

foreach ($conversational as $template) {
    echo "  - {$template->getName()}\n";
    echo "    Tags: " . implode(', ', $template->getTags()) . "\n";
}
echo "\n";

// Use case: Find templates for beginners
echo "=== Use Case: Templates for Beginners ===\n";
$allTemplates = $manager->loadAll();
$beginnerTemplates = array_filter($allTemplates, function($template) {
    return $template->getMetadata('difficulty') === 'beginner';
});

echo "Found " . count($beginnerTemplates) . " beginner-friendly templates:\n\n";
foreach ($beginnerTemplates as $template) {
    $setupTime = $template->getMetadata('estimated_setup') ?: 'N/A';
    echo "✅ {$template->getName()}\n";
    echo "   Category: {$template->getCategory()}\n";
    echo "   Setup: {$setupTime}\n";
    echo "   " . $template->getDescription() . "\n\n";
}

// Use case: Find production-ready templates
echo "=== Use Case: Production-Ready Templates ===\n";
$production = $manager->search(
    category: 'production'
);
echo "Found " . count($production) . " production templates:\n\n";

foreach ($production as $template) {
    echo "🏭 {$template->getName()}\n";
    $useCases = $template->getMetadata('use_cases') ?: [];
    if (!empty($useCases)) {
        echo "   Use Cases:\n";
        foreach ($useCases as $useCase) {
            echo "   • {$useCase}\n";
        }
    }
    echo "\n";
}

// Use case: Find templates with specific capabilities
echo "=== Use Case: Templates with RAG Capabilities ===\n";
$ragTemplates = $manager->search(
    tags: ['rag', 'retrieval', 'knowledge-base']
);
echo "Found " . count($ragTemplates) . " RAG-related templates:\n";

foreach ($ragTemplates as $template) {
    echo "  📚 {$template->getName()}\n";
    echo "     Category: {$template->getCategory()}\n";
    echo "     " . substr($template->getDescription(), 0, 100) . "...\n\n";
}

// Use case: Quick setup templates
echo "=== Use Case: Quick Setup (≤10 minutes) ===\n";
$quickTemplates = array_filter($allTemplates, function($template) {
    $setup = $template->getMetadata('estimated_setup');
    if ($setup && str_contains($setup, 'minute')) {
        preg_match('/(\d+)/', $setup, $matches);
        return isset($matches[1]) && (int)$matches[1] <= 10;
    }
    return false;
});

echo "Found " . count($quickTemplates) . " quick-setup templates:\n";
foreach ($quickTemplates as $template) {
    $time = $template->getMetadata('estimated_setup');
    echo "  ⚡ {$template->getName()} - {$time}\n";
}
echo "\n";

// Statistics
echo "=== Template Statistics ===\n";
echo "Total Templates: " . $manager->count() . "\n";
echo "Categories: " . count($categories) . "\n";
echo "Unique Tags: " . count($allTags) . "\n\n";

$difficultyCounts = [
    'beginner' => 0,
    'intermediate' => 0,
    'advanced' => 0
];

foreach ($allTemplates as $template) {
    $difficulty = $template->getMetadata('difficulty');
    if (isset($difficultyCounts[$difficulty])) {
        $difficultyCounts[$difficulty]++;
    }
}

echo "By Difficulty:\n";
foreach ($difficultyCounts as $level => $count) {
    echo "  " . ucfirst($level) . ": {$count}\n";
}

echo "\n✅ Example completed!\n";
echo "\n💡 Navigation Tips:\n";
echo "  - Start with beginner templates for learning\n";
echo "  - Use categories to find domain-specific templates\n";
echo "  - Use tags to find templates with specific features\n";
echo "  - Check difficulty and setup time before choosing\n";
