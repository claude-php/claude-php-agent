<?php

/**
 * Tutorial 6: Template Collections
 * 
 * Organize templates for your organization.
 * 
 * Time: ~10 minutes
 * Level: Intermediate
 * 
 * Topics:
 * - Creating custom template directories
 * - Building domain-specific collections
 * - Template versioning
 * - Organization patterns
 * - Template management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 6: Template Collections                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$manager = TemplateManager::getInstance();

// Step 1: Understanding Collection Structure
echo "Step 1: Understanding Collection Structure\n";
echo str_repeat('─', 80) . "\n";

echo "Current template organization:\n";
$categories = $manager->getCategories();
foreach ($categories as $category) {
    $templates = $manager->getByCategory($category);
    echo "  📁 {$category}/ (" . count($templates) . " templates)\n";
}
echo "\n";

echo "Recommended collection structure for organizations:\n";
echo "  templates/\n";
echo "    ├── core/          # Framework defaults (22 templates)\n";
echo "    ├── custom/        # Organization-specific templates\n";
echo "    ├── team-a/        # Team A's templates\n";
echo "    ├── team-b/        # Team B's templates\n";
echo "    └── experimental/  # Work-in-progress templates\n";
echo "\n";

// Step 2: Create Domain-Specific Collection
echo "Step 2: Create Domain-Specific Collection\n";
echo str_repeat('─', 80) . "\n";

echo "Creating 'E-Commerce' domain collection...\n";

$ecommerceTemplates = [
    [
        'name' => 'Product Recommendation Agent',
        'description' => 'Analyzes user preferences and browsing history to suggest relevant products.',
        'category' => 'custom',
        'tags' => ['ecommerce', 'recommendations', 'personalization'],
        'metadata' => [
            'icon' => '🛍️',
            'difficulty' => 'intermediate',
            'domain' => 'E-Commerce',
            'use_cases' => ['Product discovery', 'Personalized shopping', 'Cross-selling']
        ],
        'config' => [
            'agent_type' => 'Agent',
            'model' => 'claude-sonnet-4-5',
            'system_prompt' => 'You are a product recommendation expert. Analyze user preferences to suggest relevant products.',
            'max_iterations' => 8
        ]
    ],
    [
        'name' => 'Customer Support Agent',
        'description' => 'Handles customer inquiries, order tracking, and returns with empathy and efficiency.',
        'category' => 'custom',
        'tags' => ['ecommerce', 'support', 'customer-service'],
        'metadata' => [
            'icon' => '🎧',
            'difficulty' => 'beginner',
            'domain' => 'E-Commerce',
            'use_cases' => ['Order inquiries', 'Returns processing', 'Product questions']
        ],
        'config' => [
            'agent_type' => 'DialogAgent',
            'model' => 'claude-sonnet-4-5',
            'system_prompt' => 'You are a helpful customer support agent. Be empathetic and solve issues quickly.',
            'max_iterations' => 10
        ]
    ],
    [
        'name' => 'Inventory Manager Agent',
        'description' => 'Monitors inventory levels, predicts demand, and optimizes stock management.',
        'category' => 'custom',
        'tags' => ['ecommerce', 'inventory', 'optimization'],
        'metadata' => [
            'icon' => '📦',
            'difficulty' => 'advanced',
            'domain' => 'E-Commerce',
            'use_cases' => ['Stock monitoring', 'Demand forecasting', 'Reorder alerts']
        ],
        'config' => [
            'agent_type' => 'MonitoringAgent',
            'model' => 'claude-sonnet-4-5',
            'system_prompt' => 'You are an inventory management expert. Monitor stock and optimize inventory.',
            'max_iterations' => 12
        ]
    ]
];

$domainDir = $manager->getTemplatesPath() . '/ecommerce';
if (!is_dir($domainDir)) {
    mkdir($domainDir, 0755, true);
}

foreach ($ecommerceTemplates as $templateData) {
    $template = Template::fromArray($templateData);
    if ($template->isValid()) {
        $filename = strtolower(str_replace(' ', '-', $template->getName())) . '.json';
        $manager->saveTemplate($template, "ecommerce/{$filename}");
        echo "  ✓ Created: {$template->getName()}\n";
    }
}
echo "\n✓ E-Commerce collection created\n\n";

// Step 3: Template Versioning Strategy
echo "Step 3: Template Versioning Strategy\n";
echo str_repeat('─', 80) . "\n";

echo "Creating versioned template...\n";
$v1Template = Template::fromArray([
    'name' => 'Sales Analytics Agent v1',
    'description' => 'Basic sales data analysis',
    'category' => 'custom',
    'tags' => ['analytics', 'sales', 'v1'],
    'version' => '1.0.0',
    'metadata' => [
        'icon' => '📈',
        'difficulty' => 'intermediate',
        'changelog' => 'Initial release'
    ],
    'config' => [
        'agent_type' => 'Agent',
        'model' => 'claude-sonnet-4-5',
        'system_prompt' => 'Analyze sales data.',
        'max_iterations' => 10
    ]
]);

$manager->saveTemplate($v1Template, 'custom/sales-analytics-v1.json');
echo "✓ Version 1.0.0 saved\n\n";

echo "Creating improved version...\n";
$v2Template = Template::fromArray([
    'name' => 'Sales Analytics Agent v2',
    'description' => 'Advanced sales analysis with predictive insights',
    'category' => 'custom',
    'tags' => ['analytics', 'sales', 'v2', 'ml'],
    'version' => '2.0.0',  // Major version bump
    'metadata' => [
        'icon' => '📈',
        'difficulty' => 'advanced',
        'changelog' => 'Added ML predictions, improved analysis depth'
    ],
    'config' => [
        'agent_type' => 'ReactAgent',  // Changed agent type
        'model' => 'claude-sonnet-4-5',
        'system_prompt' => 'You are an advanced sales analyst with predictive capabilities.',
        'max_iterations' => 15  // Increased iterations
    ]
]);

$manager->saveTemplate($v2Template, 'custom/sales-analytics-v2.json');
echo "✓ Version 2.0.0 saved (breaking changes)\n\n";

// Step 4: Collection Management
echo "Step 4: Collection Management Utilities\n";
echo str_repeat('─', 80) . "\n";

function getCollectionStats(TemplateManager $manager, string $pathSuffix = ''): array
{
    $basePath = $manager->getTemplatesPath();
    $fullPath = $pathSuffix ? $basePath . '/' . $pathSuffix : $basePath;
    
    if (!is_dir($fullPath)) {
        return ['count' => 0, 'categories' => [], 'tags' => []];
    }
    
    $tempManager = new TemplateManager($fullPath);
    $templates = $tempManager->loadAll();
    
    $stats = [
        'count' => count($templates),
        'categories' => [],
        'tags' => [],
        'difficulties' => [],
        'templates' => $templates
    ];
    
    foreach ($templates as $template) {
        $category = $template->getCategory();
        $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;
        
        foreach ($template->getTags() as $tag) {
            $stats['tags'][$tag] = ($stats['tags'][$tag] ?? 0) + 1;
        }
        
        $difficulty = $template->getMetadata('difficulty');
        if ($difficulty) {
            $stats['difficulties'][$difficulty] = ($stats['difficulties'][$difficulty] ?? 0) + 1;
        }
    }
    
    return $stats;
}

$customStats = getCollectionStats($manager, 'custom');
echo "Custom Collection Statistics:\n";
echo "  Total templates: {$customStats['count']}\n";
echo "  Categories: " . count($customStats['categories']) . "\n";
echo "  Unique tags: " . count($customStats['tags']) . "\n";

if (!empty($customStats['difficulties'])) {
    echo "  By difficulty:\n";
    foreach ($customStats['difficulties'] as $level => $count) {
        echo "    {$level}: {$count}\n";
    }
}
echo "\n";

// Step 5: Template Discovery Across Collections
echo "Step 5: Template Discovery Across Collections\n";
echo str_repeat('─', 80) . "\n";

echo "Finding all custom templates...\n";
$customTemplates = array_filter($manager->loadAll(), function($template) {
    return $template->hasTag('custom') || $template->getCategory() === 'custom';
});

echo "Found " . count($customTemplates) . " custom templates:\n";
foreach ($customTemplates as $template) {
    $icon = $template->getMetadata('icon') ?: '📄';
    echo "  {$icon} {$template->getName()}\n";
}
echo "\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 6 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ Organizing templates into collections\n";
echo "  ✓ Creating domain-specific template sets\n";
echo "  ✓ Template versioning strategies\n";
echo "  ✓ Collection management utilities\n";
echo "  ✓ Cross-collection discovery\n\n";

echo "Organization Patterns:\n";
echo "  💡 Group templates by domain or team\n";
echo "  💡 Use semantic versioning for template evolution\n";
echo "  💡 Maintain separate collections for production vs experimental\n";
echo "  💡 Track collection statistics for governance\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 7: php 07-production-integration.php\n";
echo "  → Learn production deployment patterns\n";
