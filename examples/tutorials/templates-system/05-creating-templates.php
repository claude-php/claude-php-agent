<?php

/**
 * Tutorial 5: Creating Custom Templates
 * 
 * Learn to export and create your own templates.
 * 
 * Time: ~15 minutes
 * Level: Intermediate
 * 
 * Topics:
 * - Exporting existing agents
 * - Creating templates from scratch
 * - Template validation
 * - Saving templates
 * - Sharing templates with team
 * 
 * Prerequisites:
 * - ANTHROPIC_API_KEY environment variable set
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         Tutorial 5: Creating Custom Templates                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Check prerequisites
if (!getenv('ANTHROPIC_API_KEY')) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

$manager = TemplateManager::getInstance();
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Step 1: Export an Existing Agent
echo "Step 1: Export an Existing Agent\n";
echo str_repeat('─', 80) . "\n";

echo "Creating a custom weather agent...\n";
$weatherTool = Tool::create('get_weather')
    ->description('Get weather for a city')
    ->parameter('city', 'string', 'City name')
    ->required('city')
    ->handler(function (array $input): string {
        $weather = [
            'London' => '☁️ Cloudy, 15°C',
            'Paris' => '☀️ Sunny, 22°C',
            'Tokyo' => '🌧️ Rainy, 18°C',
        ];
        return $weather[$input['city']] ?? '❓ Weather data not available';
    });

$weatherAgent = Agent::create($client)
    ->withModel('claude-sonnet-4-5')
    ->withSystemPrompt('You are a friendly weather assistant providing accurate weather information.')
    ->withTool($weatherTool)
    ->maxIterations(5);

echo "✓ Weather agent created\n\n";

echo "Exporting agent as template...\n";
$template = $manager->exportAgent($weatherAgent, [
    'name' => 'Weather Assistant Agent',
    'description' => 'Provides weather information and travel advice for cities worldwide.',
    'category' => 'custom',
    'tags' => ['weather', 'travel', 'assistant', 'custom'],
    'author' => 'Tutorial User',
    'version' => '1.0.0',
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

echo "✓ Template exported\n";
echo "  Name: {$template->getName()}\n";
echo "  Category: {$template->getCategory()}\n";
echo "  Tags: " . implode(', ', $template->getTags()) . "\n\n";

// Step 2: Validate Template
echo "Step 2: Validate Template\n";
echo str_repeat('─', 80) . "\n";

echo "Running validation checks...\n";
if ($template->isValid()) {
    echo "✅ Template is valid!\n";
    echo "  • All required fields present\n";
    echo "  • Category is valid\n";
    echo "  • Version follows semver\n";
    echo "  • Agent type specified\n";
} else {
    echo "❌ Validation failed:\n";
    foreach ($template->getErrors() as $error) {
        echo "  • {$error}\n";
    }
}
echo "\n";

// Step 3: Save Template to File
echo "Step 3: Save Template to File\n";
echo str_repeat('─', 80) . "\n";

$customDir = $manager->getTemplatesPath() . '/custom';
if (!is_dir($customDir)) {
    mkdir($customDir, 0755, true);
    echo "✓ Created custom templates directory\n";
}

echo "Saving template as JSON...\n";
$manager->saveTemplate($template, 'custom/weather-assistant.json');
echo "✓ Template saved to: custom/weather-assistant.json\n\n";

echo "Saving template as PHP...\n";
$phpPath = $manager->getTemplatesPath() . '/custom/weather-assistant.php';
file_put_contents($phpPath, $template->toPhp());
echo "✓ Template saved to: custom/weather-assistant.php\n\n";

// Step 4: Create Template from Scratch
echo "Step 4: Create Template from Scratch\n";
echo str_repeat('─', 80) . "\n";

echo "Building a custom email agent template...\n";
$emailTemplate = Template::fromArray([
    'name' => 'Email Assistant Agent',
    'description' => 'Helps draft, review, and organize professional emails with proper formatting and tone.',
    'category' => 'custom',
    'tags' => ['email', 'writing', 'professional', 'assistant'],
    'version' => '1.0.0',
    'author' => 'Tutorial User',
    'requirements' => [
        'php' => '>=8.1',
        'extensions' => ['json', 'mbstring'],
        'packages' => ['claude-php/agent']
    ],
    'metadata' => [
        'icon' => '📧',
        'difficulty' => 'beginner',
        'estimated_setup' => '10 minutes',
        'use_cases' => [
            'Drafting business emails',
            'Email proofreading',
            'Tone adjustment',
            'Response suggestions'
        ],
        'target_audience' => 'Business professionals',
        'domain' => 'Communication'
    ],
    'config' => [
        'agent_type' => 'Agent',
        'model' => 'claude-sonnet-4-5',
        'max_iterations' => 5,
        'system_prompt' => 'You are a professional email writing assistant. Help users craft clear, professional emails with appropriate tone and formatting.',
        'temperature' => 0.6,
        'max_tokens' => 2048
    ]
]);

echo "✓ Template created from scratch\n";
echo "  Name: {$emailTemplate->getName()}\n";
echo "  Use Cases: " . count($emailTemplate->getMetadata('use_cases')) . "\n\n";

echo "Validating template...\n";
if ($emailTemplate->isValid()) {
    echo "✅ Template is valid\n";
    $manager->saveTemplate($emailTemplate, 'custom/email-assistant.json');
    echo "✓ Saved to: custom/email-assistant.json\n";
} else {
    echo "❌ Validation errors:\n";
    foreach ($emailTemplate->getErrors() as $error) {
        echo "  • {$error}\n";
    }
}
echo "\n";

// Step 5: Load and Test Custom Template
echo "Step 5: Load and Test Custom Template\n";
echo str_repeat('─', 80) . "\n";

$manager->clearCache(); // Force reload
echo "Reloading templates from disk...\n";

$loadedTemplate = $manager->getByName('Weather Assistant Agent');
echo "✓ Loaded custom template: {$loadedTemplate->getName()}\n";
echo "  Description: {$loadedTemplate->getDescription()}\n\n";

echo "Instantiating from custom template...\n";
$weatherAgent2 = $manager->instantiate('Weather Assistant Agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$weatherTool]
]);
echo "✓ Agent instantiated from custom template\n\n";

echo "Testing the agent...\n";
$result = $weatherAgent2->run("What's the weather in Paris?");
echo "✓ Agent response: {$result->getAnswer()}\n\n";

// Step 6: Batch Template Creation
echo "Step 6: Batch Template Creation\n";
echo str_repeat('─', 80) . "\n";

echo "Creating multiple templates at once...\n";

$templates = [
    [
        'name' => 'Code Reviewer',
        'description' => 'Reviews code for quality and best practices',
        'category' => 'custom',
        'tags' => ['code', 'review', 'quality'],
        'config' => [
            'agent_type' => 'Agent',
            'model' => 'claude-sonnet-4-5',
            'system_prompt' => 'You are a code reviewer.'
        ]
    ],
    [
        'name' => 'Documentation Writer',
        'description' => 'Writes clear technical documentation',
        'category' => 'custom',
        'tags' => ['documentation', 'writing', 'technical'],
        'config' => [
            'agent_type' => 'Agent',
            'model' => 'claude-sonnet-4-5',
            'system_prompt' => 'You are a technical writer.'
        ]
    ]
];

$created = 0;
foreach ($templates as $templateData) {
    $t = Template::fromArray($templateData);
    if ($t->isValid()) {
        $filename = strtolower(str_replace(' ', '-', $t->getName())) . '.json';
        $manager->saveTemplate($t, "custom/{$filename}");
        $created++;
        echo "  ✓ Created: {$t->getName()}\n";
    }
}
echo "\n✓ Created {$created} templates\n\n";

// Summary
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║ Tutorial 5 Complete!                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "What You Learned:\n";
echo "  ✓ Exporting agents as templates\n";
echo "  ✓ Creating templates from scratch\n";
echo "  ✓ Template validation workflow\n";
echo "  ✓ Saving in multiple formats\n";
echo "  ✓ Loading and testing custom templates\n";
echo "  ✓ Batch template creation\n\n";

echo "Files Created:\n";
echo "  📄 custom/weather-assistant.json\n";
echo "  📄 custom/weather-assistant.php\n";
echo "  📄 custom/email-assistant.json\n";
echo "  📄 custom/code-reviewer.json\n";
echo "  📄 custom/documentation-writer.json\n\n";

echo "Best Practices:\n";
echo "  💡 Always validate before saving\n";
echo "  💡 Include comprehensive metadata\n";
echo "  💡 Use meaningful tags for searchability\n";
echo "  💡 Test templates after creation\n";
echo "  💡 Document use cases clearly\n\n";

echo "Next Steps:\n";
echo "  → Run Tutorial 6: php 06-template-collections.php\n";
echo "  → Learn to organize templates for teams\n";
