<?php

/**
 * Tutorial 4: Advanced Search Techniques
 * 
 * Master advanced search and filtering techniques.
 * 
 * Time: ~10 minutes
 * Level: Intermediate
 * 
 * Topics:
 * - Multi-criteria search
 * - Field selection for performance
 * - Complex tag filtering
 * - Category combinations
 * - Custom search patterns
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 4: Advanced Search Techniques                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$manager = TemplateManager::getInstance();

// Step 1: Multi-Criteria Search
echo "Step 1: Multi-Criteria Search\n";
echo str_repeat('─', 80) . "\n";

echo "Search 1: Advanced templates in specialized category\n";
$results = $manager->search(
    category: 'specialized',
    tags: ['advanced']
);
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  • {$template->getName()}\n";
}
echo "\n";

echo "Search 2: Conversation-related templates\n";
$results = $manager->search(
    query: 'conversation',
    tags: ['chatbot', 'conversation']
);
echo "Found " . count($results) . " templates:\n";
foreach ($results as $template) {
    echo "  💬 {$template->getName()}\n";
    echo "     Tags: " . implode(', ', $template->getTags()) . "\n";
}
echo "\n";

// Step 2: Field Selection for Performance
echo "Step 2: Field Selection for Performance\n";
echo str_repeat('─', 80) . "\n";

echo "Fetching only essential fields (name, description, difficulty)...\n";
$start = microtime(true);
$results = $manager->search(
    fields: ['name', 'description', 'metadata']
);
$elapsed = (microtime(true) - $start) * 1000;

echo "✓ Retrieved " . count($results) . " templates in " . number_format($elapsed, 2) . "ms\n\n";

echo "Sample results (arrays instead of Template objects):\n";
foreach (array_slice($results, 0, 3) as $result) {
    $difficulty = $result['metadata']['difficulty'] ?? 'N/A';
    echo "  📄 {$result['name']} ({$difficulty})\n";
    echo "     " . substr($result['description'], 0, 70) . "...\n";
}
echo "\n";

// Step 3: Complex Tag Filtering
echo "Step 3: Complex Tag Filtering\n";
echo str_repeat('─', 80) . "\n";

echo "Strategy 1: Find templates with ANY of multiple tags (OR logic)\n";
$results = $manager->search(
    tags: ['beginner', 'intermediate']
);
echo "Templates tagged 'beginner' OR 'intermediate': " . count($results) . "\n\n";

echo "Strategy 2: Find templates with specific tag combinations\n";
$allTemplates = $manager->loadAll();

// Find templates with both 'advanced' AND 'reasoning' tags
$advancedReasoning = array_filter($allTemplates, function($t) {
    return $t->hasTag('advanced') && $t->hasTag('reasoning');
});
echo "Templates with 'advanced' AND 'reasoning': " . count($advancedReasoning) . "\n";
foreach ($advancedReasoning as $t) {
    echo "  • {$t->getName()}\n";
}
echo "\n";

// Step 4: Custom Search Functions
echo "Step 4: Custom Search Functions\n";
echo str_repeat('─', 80) . "\n";

// Function: Find templates by setup time
function findBySetupTime(array $templates, int $maxMinutes): array
{
    return array_filter($templates, function($template) use ($maxMinutes) {
        $setup = $template->getMetadata('estimated_setup');
        if ($setup && preg_match('/(\d+)/', $setup, $matches)) {
            return (int)$matches[1] <= $maxMinutes;
        }
        return false;
    });
}

$quickTemplates = findBySetupTime($allTemplates, 10);
echo "Templates with setup ≤10 minutes: " . count($quickTemplates) . "\n";
foreach ($quickTemplates as $t) {
    echo "  ⚡ {$t->getName()} - {$t->getMetadata('estimated_setup')}\n";
}
echo "\n";

// Function: Find templates by use case keyword
function findByUseCase(array $templates, string $keyword): array
{
    return array_filter($templates, function($template) use ($keyword) {
        $useCases = $template->getMetadata('use_cases') ?: [];
        foreach ($useCases as $useCase) {
            if (stripos($useCase, $keyword) !== false) {
                return true;
            }
        }
        return false;
    });
}

$monitoringTemplates = findByUseCase($allTemplates, 'monitoring');
echo "Templates for 'monitoring' use case: " . count($monitoringTemplates) . "\n";
foreach ($monitoringTemplates as $t) {
    echo "  📊 {$t->getName()}\n";
}
echo "\n";

// Step 5: Building Smart Recommendations
echo "Step 5: Building Smart Recommendations\n";
echo str_repeat('─', 80) . "\n";

function recommendTemplateForSkillLevel(string $level, TemplateManager $manager): array
{
    $allTemplates = $manager->loadAll();
    
    $recommended = array_filter($allTemplates, function($template) use ($level) {
        $difficulty = $template->getMetadata('difficulty');
        
        return match($level) {
            'novice' => $difficulty === 'beginner',
            'intermediate' => in_array($difficulty, ['beginner', 'intermediate']),
            'expert' => true, // All templates
            default => false
        };
    });
    
    // Sort by setup time (quickest first)
    usort($recommended, function($a, $b) {
        $aTime = $a->getMetadata('estimated_setup');
        $bTime = $b->getMetadata('estimated_setup');
        
        preg_match('/(\d+)/', $aTime ?: '999', $aMatches);
        preg_match('/(\d+)/', $bTime ?: '999', $bMatches);
        
        return ((int)($aMatches[1] ?? 999)) <=> ((int)($bMatches[1] ?? 999));
    });
    
    return $recommended;
}

echo "Recommendations for 'novice' developers:\n";
$noviceTemplates = array_slice(recommendTemplateForSkillLevel('novice', $manager), 0, 5);
foreach ($noviceTemplates as $t) {
    $icon = $t->getMetadata('icon') ?: '📄';
    $time = $t->getMetadata('estimated_setup');
    echo "  {$icon} {$t->getName()} ({$time})\n";
    echo "     {$t->getDescription()}\n";
}
echo "\n";

// Step 6: Search Performance
echo "Step 6: Search Performance Comparison\n";
echo str_repeat('─', 80) . "\n";

// Full object search
$start = microtime(true);
$fullResults = $manager->search(query: 'agent');
$fullTime = (microtime(true) - $start) * 1000;

// Field-filtered search
$start = microtime(true);
$filteredResults = $manager->search(
    query: 'agent',
    fields: ['name', 'description']
);
$filteredTime = (microtime(true) - $start) * 1000;

echo "Performance Comparison:\n";
echo sprintf("  Full objects: %.2fms (%d templates)\n", $fullTime, count($fullResults));
echo sprintf("  Field filter: %.2fms (%d results)\n", $filteredTime, count($filteredResults));
echo sprintf("  Speedup: %.1fx faster\n", $fullTime / max($filteredTime, 0.001));
echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 4 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ Multi-criteria search combinations\n";
echo "  ✓ Field selection for performance\n";
echo "  ✓ Complex tag filtering patterns\n";
echo "  ✓ Building custom search functions\n";
echo "  ✓ Smart template recommendations\n";
echo "  ✓ Performance optimization\n\n";

echo "Advanced Techniques:\n";
echo "  💡 Combine multiple filters for precision\n";
echo "  💡 Use field selection when only metadata needed\n";
echo "  💡 Build reusable search functions\n";
echo "  💡 Sort results by relevance\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 5: php 05-creating-templates.php\n";
echo "  → Learn to export and create custom templates\n";
