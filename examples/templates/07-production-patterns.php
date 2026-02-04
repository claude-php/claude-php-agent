<?php

/**
 * Example 7: Production Patterns
 * 
 * This example demonstrates:
 * - Using production-ready templates
 * - Error handling best practices
 * - Monitoring and logging
 * - Template validation in production
 * 
 * NOTE: Requires ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use ClaudeAgents\Templates\Exceptions\TemplateInstantiationException;
use ClaudeAgents\Tools\Tool;

echo "=== Template System: Production Patterns ===\n\n";

// Check for API key
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

$manager = TemplateManager::getInstance();

// Pattern 1: Safe template loading with error handling
echo "=== Pattern 1: Safe Template Loading ===\n";

function loadTemplateSafely(TemplateManager $manager, string $name): ?\ClaudeAgents\Templates\Template
{
    try {
        return $manager->getByName($name);
    } catch (TemplateNotFoundException $e) {
        error_log("Template not found: {$name}");
        return null;
    }
}

$template = loadTemplateSafely($manager, 'Production Agent');
if ($template) {
    echo "✅ Loaded template: {$template->getName()}\n";
    
    // Validate before use
    if ($template->isValid()) {
        echo "✅ Template validation passed\n";
    } else {
        echo "❌ Template validation failed:\n";
        foreach ($template->getErrors() as $error) {
            echo "  • {$error}\n";
        }
    }
} else {
    echo "❌ Template not found\n";
}
echo "\n";

// Pattern 2: Safe instantiation with fallback
echo "=== Pattern 2: Safe Instantiation with Fallback ===\n";

function instantiateWithFallback(
    TemplateManager $manager,
    string $primaryTemplate,
    string $fallbackTemplate,
    array $config
): ?\ClaudeAgents\Agent {
    try {
        echo "Attempting to instantiate: {$primaryTemplate}\n";
        return $manager->instantiate($primaryTemplate, $config);
    } catch (TemplateInstantiationException $e) {
        error_log("Failed to instantiate {$primaryTemplate}: " . $e->getMessage());
        
        try {
            echo "Falling back to: {$fallbackTemplate}\n";
            return $manager->instantiate($fallbackTemplate, $config);
        } catch (TemplateInstantiationException $e2) {
            error_log("Fallback also failed: " . $e2->getMessage());
            return null;
        }
    }
}

$calculator = Tool::create('calculate')
    ->description('Perform calculations')
    ->parameter('expression', 'string', 'Expression')
    ->required('expression')
    ->handler(function (array $input): string {
        return (string) eval("return {$input['expression']};");
    });

$agent = instantiateWithFallback(
    $manager,
    'production-agent',
    'react-agent',
    [
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'tools' => [$calculator]
    ]
);

if ($agent) {
    echo "✅ Agent instantiated successfully\n";
} else {
    echo "❌ Failed to instantiate any agent\n";
}
echo "\n";

// Pattern 3: Template validation pipeline
echo "=== Pattern 3: Template Validation Pipeline ===\n";

function validateTemplateForProduction(\ClaudeAgents\Templates\Template $template): array
{
    $issues = [];
    
    // Check basic validation
    if (!$template->isValid()) {
        $issues = array_merge($issues, $template->getErrors());
    }
    
    // Check requirements
    $requirements = $template->getRequirements();
    
    // Verify PHP version
    if (isset($requirements['php'])) {
        $required = str_replace('>=', '', $requirements['php']);
        if (version_compare(PHP_VERSION, $required, '<')) {
            $issues[] = "PHP version {$required} or higher required, current: " . PHP_VERSION;
        }
    }
    
    // Verify extensions
    if (isset($requirements['extensions'])) {
        foreach ($requirements['extensions'] as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Required extension not loaded: {$ext}";
            }
        }
    }
    
    // Check metadata completeness
    $metadata = $template->getMetadata();
    if (empty($metadata['difficulty'])) {
        $issues[] = "Missing difficulty level in metadata";
    }
    
    if (empty($metadata['use_cases'])) {
        $issues[] = "No use cases documented";
    }
    
    return $issues;
}

$productionTemplate = $manager->getByName('Production Agent');
echo "Validating: {$productionTemplate->getName()}\n";

$issues = validateTemplateForProduction($productionTemplate);
if (empty($issues)) {
    echo "✅ All production validation checks passed\n";
} else {
    echo "⚠️  Production validation issues found:\n";
    foreach ($issues as $issue) {
        echo "  • {$issue}\n";
    }
}
echo "\n";

// Pattern 4: Template caching and performance
echo "=== Pattern 4: Template Caching ===\n";

// Warm cache
echo "Warming template cache...\n";
$start = microtime(true);
$manager->loadAll();
$warmTime = (microtime(true) - $start) * 1000;
echo sprintf("✅ Loaded all templates in %.2fms\n", $warmTime);

// Cached access
$start = microtime(true);
$manager->loadAll();
$cachedTime = (microtime(true) - $start) * 1000;
echo sprintf("✅ Cached access in %.2fms (%.1fx faster)\n", $cachedTime, $warmTime / $cachedTime);
echo "\n";

// Pattern 5: Configuration management
echo "=== Pattern 5: Configuration Management ===\n";

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

$productionConfig = buildProductionConfig([
    'max_iterations' => 15, // Override default
]);

echo "Production configuration:\n";
foreach ($productionConfig as $key => $value) {
    if ($key !== 'api_key') { // Don't log API key
        echo "  {$key}: {$value}\n";
    }
}
echo "\n";

// Pattern 6: Health check for templates
echo "=== Pattern 6: Template Health Check ===\n";

function healthCheckTemplates(TemplateManager $manager): array
{
    $stats = [
        'total' => 0,
        'valid' => 0,
        'invalid' => 0,
        'categories' => [],
        'issues' => []
    ];
    
    $templates = $manager->loadAll();
    $stats['total'] = count($templates);
    
    foreach ($templates as $template) {
        // Count by category
        $category = $template->getCategory();
        $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;
        
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

$healthCheck = healthCheckTemplates($manager);
echo "Template Health Check Results:\n";
echo "  Total templates: {$healthCheck['total']}\n";
echo "  Valid: {$healthCheck['valid']} ({$healthCheck['valid']} / {$healthCheck['total']})\n";
echo "  Invalid: {$healthCheck['invalid']}\n";

if (!empty($healthCheck['issues'])) {
    echo "\n  Issues found:\n";
    foreach ($healthCheck['issues'] as $issue) {
        echo "    {$issue['template']}:\n";
        foreach ($issue['errors'] as $error) {
            echo "      • {$error}\n";
        }
    }
}

echo "\n  Templates by category:\n";
foreach ($healthCheck['categories'] as $category => $count) {
    echo "    {$category}: {$count}\n";
}

echo "\n✅ Example completed!\n";
echo "\n💡 Production Tips:\n";
echo "  - Always validate templates before use\n";
echo "  - Implement fallback strategies\n";
echo "  - Use proper error handling\n";
echo "  - Cache templates for performance\n";
echo "  - Run health checks in CI/CD pipelines\n";
echo "  - Monitor template usage in production\n";
