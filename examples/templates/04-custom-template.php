<?php

/**
 * Example 4: Export Custom Templates
 * 
 * This example demonstrates:
 * - Creating custom agents
 * - Exporting agents as templates
 * - Saving templates to files
 * - Loading and using custom templates
 * 
 * NOTE: Requires ANTHROPIC_API_KEY environment variable
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "=== Template System: Export Custom Templates ===\n\n";

// Check for API key
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

$manager = TemplateManager::getInstance();
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Example 1: Create and export a custom agent
echo "=== Example 1: Export Custom Agent ===\n";

// Create a custom weather assistant agent
$weatherTool = Tool::create('get_weather')
    ->description('Get current weather for a location')
    ->parameter('city', 'string', 'City name')
    ->required('city')
    ->handler(function (array $input): string {
        // Mock weather data
        $weather = [
            'London' => 'Cloudy, 15°C',
            'Paris' => 'Sunny, 22°C',
            'Tokyo' => 'Rainy, 18°C',
        ];
        return $weather[$input['city']] ?? 'Weather data not available';
    });

$weatherAgent = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a friendly weather assistant. Provide weather information and travel advice.')
    ->withTool($weatherTool)
    ->maxIterations(5);

echo "Created custom weather assistant agent\n";

// Export the agent as a template
echo "Exporting agent as template...\n";
$template = $manager->exportAgent($weatherAgent, [
    'name' => 'Weather Assistant',
    'description' => 'A friendly agent that provides weather information and travel advice for cities around the world.',
    'category' => 'custom',
    'tags' => ['weather', 'travel', 'assistant', 'custom'],
    'author' => 'Your Name',
    'metadata' => [
        'icon' => '🌤️',
        'difficulty' => 'beginner',
        'estimated_setup' => '10 minutes',
        'use_cases' => [
            'Weather queries',
            'Travel planning',
            'City information'
        ]
    ]
]);

echo "Template created!\n";
echo "  Name: " . $template->getName() . "\n";
echo "  Category: " . $template->getCategory() . "\n";
echo "  Tags: " . implode(', ', $template->getTags()) . "\n\n";

// Save template to file
$customPath = $manager->getTemplatesPath() . '/custom';
if (!is_dir($customPath)) {
    mkdir($customPath, 0755, true);
}

$templatePath = 'custom/weather-assistant.json';
echo "Saving template to: {$templatePath}\n";
$manager->saveTemplate($template, $templatePath);
echo "✅ Template saved!\n\n";

// Verify we can load it back
echo "=== Example 2: Load and Use Custom Template ===\n";
$manager->clearCache(); // Force reload

$loadedTemplate = $manager->getByName('Weather Assistant');
echo "Loaded template: " . $loadedTemplate->getName() . "\n";
echo "Description: " . $loadedTemplate->getDescription() . "\n\n";

// Instantiate from the custom template
echo "Instantiating agent from custom template...\n";
$weatherAgent2 = $manager->instantiate('Weather Assistant', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$weatherTool] // Need to provide tools again
]);

echo "Running agent: What's the weather in Paris?\n";
$result = $weatherAgent2->run("What's the weather in Paris?");
echo "Result: " . $result->getAnswer() . "\n\n";

// Example 3: Export template to PHP format
echo "=== Example 3: Export to PHP Format ===\n";
$phpPath = 'custom/weather-assistant.php';
echo "Saving template as PHP to: {$phpPath}\n";

$fullPath = $manager->getTemplatesPath() . '/' . $phpPath;
file_put_contents($fullPath, $template->toPhp());
echo "✅ PHP template saved!\n\n";

// Example 4: View template as JSON
echo "=== Example 4: Template JSON Preview ===\n";
$json = $template->toJson();
echo substr($json, 0, 500) . "...\n\n";

// Example 5: Export multiple templates
echo "=== Example 5: Batch Export ===\n";

$agent1 = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a code reviewer.');

$agent2 = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a documentation writer.');

$agents = [$agent1, $agent2];
$metadataList = [
    [
        'name' => 'Code Reviewer Agent',
        'description' => 'Reviews code for quality and best practices',
        'category' => 'custom',
        'tags' => ['code', 'review']
    ],
    [
        'name' => 'Documentation Writer Agent',
        'description' => 'Writes clear and comprehensive documentation',
        'category' => 'custom',
        'tags' => ['docs', 'writing']
    ]
];

$exporter = $manager->getExporter();
$templates = $exporter->exportMultiple($agents, $metadataList);

echo "Exported " . count($templates) . " templates:\n";
foreach ($templates as $t) {
    echo "  - " . $t->getName() . "\n";
}

echo "\n✅ Example completed!\n";
echo "\n💡 Tips:\n";
echo "  - Custom templates are stored in templates/custom/\n";
echo "  - Templates can be in JSON or PHP format\n";
echo "  - Share templates with your team or community\n";
