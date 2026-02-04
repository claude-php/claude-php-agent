<?php

/**
 * Tutorial 7: Production Integration
 * 
 * Production-ready template usage patterns.
 * 
 * Time: ~15 minutes
 * Level: Advanced
 * 
 * Topics:
 * - Template validation pipelines
 * - Error handling strategies
 * - Fallback patterns
 * - Performance optimization
 * - Health checks
 * - Monitoring template usage
 * 
 * Prerequisites:
 * - ANTHROPIC_API_KEY environment variable set
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use ClaudeAgents\Templates\Exceptions\TemplateInstantiationException;
use ClaudeAgents\Tools\Tool;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 7: Production Integration                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Check prerequisites
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

$manager = TemplateManager::getInstance();

// Step 1: Production Template Validation Pipeline
echo "Step 1: Production Template Validation Pipeline\n";
echo str_repeat('─', 80) . "\n";

function validateTemplateForProduction(Template $template): array
{
    $issues = [];
    
    // Basic validation
    if (!$template->isValid()) {
        $issues = array_merge($issues, $template->getErrors());
    }
    
    // Check metadata completeness
    $requiredMetadata = ['icon', 'difficulty', 'estimated_setup', 'use_cases'];
    foreach ($requiredMetadata as $field) {
        if (empty($template->getMetadata($field))) {
            $issues[] = "Missing metadata field: {$field}";
        }
    }
    
    // Check requirements
    $requirements = $template->getRequirements();
    if (isset($requirements['php'])) {
        $required = str_replace('>=', '', $requirements['php']);
        if (version_compare(PHP_VERSION, $required, '<')) {
            $issues[] = "PHP {$required}+ required, current: " . PHP_VERSION;
        }
    }
    
    // Check extensions
    if (isset($requirements['extensions'])) {
        foreach ($requirements['extensions'] as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Extension not loaded: {$ext}";
            }
        }
    }
    
    // Check config completeness
    $config = $template->getConfig();
    $requiredConfig = ['agent_type', 'model', 'max_iterations'];
    foreach ($requiredConfig as $field) {
        if (empty($config[$field])) {
            $issues[] = "Missing config field: {$field}";
        }
    }
    
    return $issues;
}

$template = $manager->getByName('Production Agent');
echo "Validating: {$template->getName()}\n";
$issues = validateTemplateForProduction($template);

if (empty($issues)) {
    echo "✅ All production validation checks passed\n";
} else {
    echo "⚠️ Validation issues:\n";
    foreach ($issues as $issue) {
        echo "  • {$issue}\n";
    }
}
echo "\n";

// Step 2: Safe Template Loading with Error Handling
echo "Step 2: Safe Template Loading with Error Handling\n";
echo str_repeat('─', 80) . "\n";

function loadTemplateSafely(TemplateManager $manager, string $name): ?Template
{
    try {
        return $manager->getByName($name);
    } catch (TemplateNotFoundException $e) {
        error_log("Template not found: {$name} - " . $e->getMessage());
        return null;
    }
}

echo "Attempting to load existing template...\n";
$template = loadTemplateSafely($manager, 'Basic Agent');
if ($template) {
    echo "✅ Template loaded: {$template->getName()}\n";
} else {
    echo "❌ Template not found\n";
}
echo "\n";

echo "Attempting to load non-existent template...\n";
$template = loadTemplateSafely($manager, 'Nonexistent Template');
if ($template) {
    echo "✅ Template loaded\n";
} else {
    echo "⚠️ Template not found (handled gracefully)\n";
}
echo "\n";

// Step 3: Fallback Pattern for Instantiation
echo "Step 3: Fallback Pattern for Instantiation\n";
echo str_repeat('─', 80) . "\n";

function instantiateWithFallback(
    TemplateManager $manager,
    array $templateNames,
    array $config
): ?object {
    foreach ($templateNames as $name) {
        try {
            echo "  Attempting: {$name}...\n";
            $agent = $manager->instantiate($name, $config);
            echo "  ✅ Success with: {$name}\n";
            return $agent;
        } catch (TemplateInstantiationException | TemplateNotFoundException $e) {
            echo "  ⚠️ Failed: " . $e->getMessage() . "\n";
            continue;
        }
    }
    
    error_log("All template instantiation attempts failed");
    return null;
}

$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->parameter('expression', 'string', 'Expression')
    ->required('expression')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

echo "Trying multiple templates with fallback...\n";
$agent = instantiateWithFallback(
    $manager,
    ['Production Agent', 'ReAct Agent', 'Basic Agent'],
    [
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'tools' => [$calculator]
    ]
);

if ($agent) {
    echo "✓ Agent ready for use\n";
    $result = $agent->run('Calculate 99 + 1');
    echo "  Test result: {$result->getAnswer()}\n";
} else {
    echo "❌ All instantiation attempts failed\n";
}
echo "\n";

// Step 4: Template Health Checks
echo "Step 4: Template Health Checks\n";
echo str_repeat('─', 80) . "\n";

function performHealthCheck(TemplateManager $manager): array
{
    $stats = [
        'total' => 0,
        'valid' => 0,
        'invalid' => 0,
        'by_category' => [],
        'by_difficulty' => [],
        'issues' => []
    ];
    
    $templates = $manager->loadAll();
    $stats['total'] = count($templates);
    
    foreach ($templates as $template) {
        // Count by category
        $category = $template->getCategory();
        $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
        
        // Count by difficulty
        $difficulty = $template->getMetadata('difficulty');
        if ($difficulty) {
            $stats['by_difficulty'][$difficulty] = ($stats['by_difficulty'][$difficulty] ?? 0) + 1;
        }
        
        // Validate
        if ($template->isValid()) {
            $stats['valid']++;
        } else {
            $stats['invalid']++;
            $stats['issues'][] = [
                'template' => $template->getName(),
                'errors' => $template->getErrors()
            ];
        }
    }
    
    return $stats;
}

echo "Running health check on all templates...\n";
$healthCheck = performHealthCheck($manager);

echo "\n📊 Health Check Results:\n";
echo "  Total: {$healthCheck['total']}\n";
echo "  Valid: {$healthCheck['valid']} (" . 
     round(($healthCheck['valid'] / $healthCheck['total']) * 100) . "%)\n";
echo "  Invalid: {$healthCheck['invalid']}\n\n";

if ($healthCheck['invalid'] > 0) {
    echo "⚠️ Issues found:\n";
    foreach ($healthCheck['issues'] as $issue) {
        echo "  Template: {$issue['template']}\n";
        foreach ($issue['errors'] as $error) {
            echo "    • {$error}\n";
        }
    }
    echo "\n";
}

echo "By Category:\n";
foreach ($healthCheck['by_category'] as $category => $count) {
    echo "  {$category}: {$count}\n";
}
echo "\n";

echo "By Difficulty:\n";
foreach ($healthCheck['by_difficulty'] as $difficulty => $count) {
    echo "  {$difficulty}: {$count}\n";
}
echo "\n";

// Step 5: Performance Monitoring
echo "Step 5: Performance Monitoring\n";
echo str_repeat('─', 80) . "\n";

echo "Testing template loading performance...\n";

// Cold load
$manager->clearCache();
$start = microtime(true);
$templates = $manager->loadAll();
$coldLoad = (microtime(true) - $start) * 1000;

// Warm load
$start = microtime(true);
$templates = $manager->loadAll();
$warmLoad = (microtime(true) - $start) * 1000;

echo "  Cold load: " . number_format($coldLoad, 2) . "ms\n";
echo "  Warm load: " . number_format($warmLoad, 2) . "ms\n";
echo "  Speedup: " . number_format($coldLoad / max($warmLoad, 0.001), 1) . "x\n\n";

// Step 6: Production Configuration Builder
echo "Step 6: Production Configuration Builder\n";
echo str_repeat('─', 80) . "\n";

function buildProductionConfig(array $baseConfig = []): array
{
    return array_merge([
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'model' => 'claude-sonnet-4-5',
        'max_iterations' => 10,
        'retry_max_attempts' => 3,
        'retry_delay_ms' => 1000,
        'timeout' => 30.0,
    ], $baseConfig);
}

$prodConfig = buildProductionConfig([
    'max_iterations' => 15  // Override
]);

echo "Production configuration ready:\n";
foreach ($prodConfig as $key => $value) {
    if ($key !== 'api_key') {
        echo "  {$key}: {$value}\n";
    }
}
echo "\n";

// Step 7: Automated Testing
echo "Step 7: Automated Template Testing\n";
echo str_repeat('─', 80) . "\n";

function testTemplateInstantiation(Template $template, array $config): bool
{
    try {
        $manager = TemplateManager::getInstance();
        $agent = $manager->instantiateFromTemplate($template, $config);
        return $agent !== null;
    } catch (\Throwable $e) {
        error_log("Template instantiation failed: " . $e->getMessage());
        return false;
    }
}

echo "Testing instantiation for all beginner templates...\n";
$beginnerTemplates = array_filter($manager->loadAll(), function($t) {
    return $t->getMetadata('difficulty') === 'beginner';
});

$passed = 0;
$failed = 0;

foreach ($beginnerTemplates as $template) {
    echo "  Testing: {$template->getName()}...\n";
    $success = testTemplateInstantiation($template, [
        'api_key' => getenv('ANTHROPIC_API_KEY')
    ]);
    
    if ($success) {
        echo "    ✅ Passed\n";
        $passed++;
    } else {
        echo "    ❌ Failed\n";
        $failed++;
    }
}

echo "\n✓ Test Results: {$passed} passed, {$failed} failed\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 7 Complete! 🎉                                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ Production validation pipelines\n";
echo "  ✓ Safe template loading patterns\n";
echo "  ✓ Fallback strategies\n";
echo "  ✓ Health check automation\n";
echo "  ✓ Performance monitoring\n";
echo "  ✓ Production configuration\n";
echo "  ✓ Automated testing\n\n";

echo "Production Checklist:\n";
echo "  ☑ Validate all templates before deployment\n";
echo "  ☑ Implement fallback strategies\n";
echo "  ☑ Monitor template performance\n";
echo "  ☑ Run regular health checks\n";
echo "  ☑ Test template instantiation in CI/CD\n";
echo "  ☑ Log template usage and errors\n";
echo "  ☑ Version control template files\n\n";

echo "Congratulations! 🎊\n";
echo "You've completed the Template System tutorial series!\n\n";

echo "What's Next:\n";
echo "  📚 Review documentation: docs/templates/README.md\n";
echo "  📖 Browse catalog: docs/templates/TEMPLATE_CATALOG.md\n";
echo "  🔨 Create your own templates\n";
echo "  🤝 Share templates with your team\n";
echo "  🚀 Deploy to production\n";
