<?php

/**
 * Example 2: Advanced Search and Filtering
 * 
 * This example demonstrates:
 * - Searching templates by query
 * - Filtering by tags
 * - Filtering by category
 * - Selective field loading
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "=== Template System: Search and Filtering ===\n\n";

$manager = TemplateManager::getInstance();

// Search by query
echo "=== Search by Query: 'chatbot' ===\n";
$results = $manager->search(query: 'chatbot');
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  - " . $template->getName() . "\n";
}

// Search by tags
echo "\n=== Search by Tags: ['conversation', 'memory'] ===\n";
$results = $manager->search(tags: ['conversation', 'memory']);
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  - " . $template->getName() . " (tags: " . implode(', ', $template->getTags()) . ")\n";
}

// Search by category
echo "\n=== Search by Category: 'agents' ===\n";
$results = $manager->search(category: 'agents');
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  - " . $template->getName() . "\n";
}

// Combined search
echo "\n=== Combined Search: category='specialized', tags=['monitoring'] ===\n";
$results = $manager->search(
    category: 'specialized',
    tags: ['monitoring']
);
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  - " . $template->getName() . "\n";
}

// Search with field filtering (returns array instead of Template objects)
echo "\n=== Search with Field Filtering ===\n";
echo "Query: 'agent', Fields: name, description, difficulty\n\n";
$results = $manager->search(
    query: 'agent',
    fields: ['name', 'description', 'metadata']
);

echo "Found " . count($results) . " templates:\n";
foreach ($results as $data) {
    $difficulty = $data['metadata']['difficulty'] ?? 'N/A';
    echo "\n" . $data['name'] . " ({$difficulty})\n";
    echo "  " . substr($data['description'], 0, 80) . "...\n";
}

// Advanced filtering: Get all beginner templates
echo "\n=== Advanced: All Beginner Templates ===\n";
$allTemplates = $manager->loadAll();
$beginnerTemplates = array_filter($allTemplates, function($template) {
    return $template->getMetadata('difficulty') === 'beginner';
});

echo "Found " . count($beginnerTemplates) . " beginner templates:\n";
foreach ($beginnerTemplates as $template) {
    echo "  - " . $template->getName() . " [" . $template->getCategory() . "]\n";
}

// Get templates by multiple tags
echo "\n=== Templates with 'advanced' or 'planning' tags ===\n";
$results = $manager->search(tags: ['advanced', 'planning']);
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  - " . $template->getName() . " (tags: " . implode(', ', $template->getTags()) . ")\n";
}

echo "\n✅ Example completed!\n";
