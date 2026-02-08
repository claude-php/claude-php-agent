#!/usr/bin/env php
<?php
/**
 * Cross-Vendor Dynamic Model Fusion (DMF) Example
 *
 * Demonstrates how to use Claude as the primary orchestrator while
 * delegating to OpenAI and Google Gemini for vendor-specific capabilities:
 *
 *   - OpenAI: web search, image generation, text-to-speech
 *   - Gemini: Google Search grounding, code execution, Nano Banana image gen
 *   - Generic: cross-vendor chat (ask any registered model)
 *
 * Set your API keys in .env:
 *   ANTHROPIC_API_KEY=your_key
 *   OPENAI_API_KEY=your_key
 *   GEMINI_API_KEY=your_key
 *
 * @see https://github.com/dalehurley/cross-vendor-dmf
 */

require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\CrossVendorToolFactory;
use ClaudeAgents\Vendor\ModelRegistry;
use ClaudeAgents\Vendor\VendorRegistry;
use ClaudePhp\ClaudePhp;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

$apiKey = $_ENV['ANTHROPIC_API_KEY']
    ?? getenv('ANTHROPIC_API_KEY')
    ?: throw new RuntimeException('ANTHROPIC_API_KEY not set');

$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              Cross-Vendor Dynamic Model Fusion (DMF) Example             ║\n";
echo "║           Claude orchestrates OpenAI + Gemini vendor-specific tools      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// Step 1: Register vendor API keys
// ─────────────────────────────────────────────────────────────────────────────

echo "Step 1: Detecting vendor API keys...\n\n";

$vendorRegistry = VendorRegistry::fromEnvironment();
$modelRegistry = ModelRegistry::default();

$vendors = $vendorRegistry->getAvailableVendors();
echo "  Available vendors: " . (empty($vendors) ? '(none)' : implode(', ', $vendors)) . "\n";

foreach (['anthropic', 'openai', 'google'] as $vendor) {
    $status = $vendorRegistry->isAvailable($vendor) ? 'YES' : 'not set';
    $envVar = VendorRegistry::getEnvVarName($vendor);
    echo "    {$vendor}: {$status} ({$envVar})\n";
}

if (! $vendorRegistry->hasExternalVendors()) {
    echo "\n  No external vendor keys found.\n";
    echo "  Set OPENAI_API_KEY and/or GEMINI_API_KEY in your .env to enable DMF tools.\n";
    echo "  Continuing with a demonstration of tool registration...\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 2: Browse the model catalog
// ─────────────────────────────────────────────────────────────────────────────

echo "\nStep 2: Model catalog\n\n";

foreach (['openai', 'google'] as $vendor) {
    $models = $modelRegistry->getModelsForVendor($vendor);
    echo "  {$vendor} models (" . count($models) . "):\n";

    foreach ($models as $model) {
        $caps = implode(', ', array_map(fn($c) => $c->value, $model->capabilities));
        $default = $model->isDefault ? ' [default]' : '';
        echo "    - {$model->id}: {$model->description} ({$caps}){$default}\n";
    }
    echo "\n";
}

// Show default models per capability
echo "  Default models per capability:\n";
foreach (Capability::cases() as $cap) {
    $openaiDefault = $modelRegistry->getDefaultModel('openai', $cap);
    $googleDefault = $modelRegistry->getDefaultModel('google', $cap);

    $parts = [];
    if ($openaiDefault) {
        $parts[] = "openai={$openaiDefault}";
    }
    if ($googleDefault) {
        $parts[] = "google={$googleDefault}";
    }

    if (! empty($parts)) {
        echo "    {$cap->value}: " . implode(', ', $parts) . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 3: Create vendor tools automatically
// ─────────────────────────────────────────────────────────────────────────────

echo "\nStep 3: Creating vendor tools...\n\n";

$factory = new CrossVendorToolFactory($vendorRegistry, $modelRegistry);
$vendorTools = $factory->createAllTools();

if (empty($vendorTools)) {
    echo "  No vendor tools created (no external API keys set).\n";
    echo "  Tools that would be available with API keys:\n";
    echo "    OPENAI_API_KEY -> openai_web_search, openai_image_generation, openai_text_to_speech\n";
    echo "    GEMINI_API_KEY -> gemini_grounding, gemini_code_execution, gemini_image_generation\n";
    echo "    Both          -> vendor_chat (cross-vendor delegation)\n";
} else {
    echo "  Created " . count($vendorTools) . " vendor tools:\n";
    foreach ($vendorTools as $tool) {
        echo "    - {$tool->getName()}: {$tool->getDescription()}\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 4: Add local tools + vendor tools to an agent
// ─────────────────────────────────────────────────────────────────────────────

echo "\nStep 4: Wiring tools into a ReactAgent...\n\n";

// Create a local calculator tool alongside vendor tools
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'Math expression to evaluate (e.g. "125 * 8 + 30")')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        if (! preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return 'Error: Invalid expression';
        }

        try {
            $result = eval("return {$expr};");

            return (string) $result;
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    });

// Combine local tools with vendor tools
$allTools = array_merge([$calculator], $vendorTools);

echo '  Total tools available: ' . count($allTools) . "\n";
echo "  Local tools: calculate\n";
echo '  Vendor tools: ' . implode(', ', array_map(fn($t) => $t->getName(), $vendorTools)) . "\n";

// Create the agent with all tools
$agent = new ReactAgent($client, [
    'name' => 'dmf_agent',
    'system' => 'You are an AI agent with access to multiple AI vendors via tools. '
        . 'Use vendor-specific tools strategically: '
        . 'openai_web_search for real-time web data, '
        . 'gemini_grounding for fact-verified responses with citations, '
        . 'gemini_code_execution for running Python calculations, '
        . 'gemini_image_generation for creating images with Nano Banana, '
        . 'openai_image_generation for photorealistic images, '
        . 'openai_text_to_speech for converting text to audio, '
        . 'vendor_chat for getting a second opinion from another model. '
        . 'Choose the best tool for each sub-task.',
    'max_iterations' => 10,
    'tools' => $allTools,
]);

// ─────────────────────────────────────────────────────────────────────────────
// Step 5: Run a cross-vendor workflow (if keys are available)
// ─────────────────────────────────────────────────────────────────────────────

if (! empty($vendorTools)) {
    echo "\nStep 5: Running cross-vendor workflow...\n\n";

    // Track tool usage
    $agent->onToolExecution(function (string $tool, array $input, ToolResult $result): void {
        $inputStr = json_encode($input);
        $displayInput = strlen($inputStr) > 80 ? substr($inputStr, 0, 80) . '...' : $inputStr;
        $status = $result->isError() ? 'ERROR' : 'OK';
        echo "  Tool: {$tool} [{$status}]\n";
        echo "  Input: {$displayInput}\n";
        $content = $result->getContent();
        $displayContent = strlen($content) > 120 ? substr($content, 0, 120) . '...' : $content;
        echo "  Result: {$displayContent}\n\n";
    });

    $task = 'Use the available vendor tools to: '
        . '1) Search the web for "PHP 8.4 new features" and summarize the top findings, '
        . '2) Use a second vendor to verify one of the key facts from the search results. '
        . 'Present a brief, consolidated report.';

    echo "  Task: {$task}\n\n";
    echo str_repeat('─', 80) . "\n";

    $result = $agent->run($task);

    echo str_repeat('─', 80) . "\n\n";

    if ($result->isSuccess()) {
        echo "  Result: {$result->getAnswer()}\n\n";
        echo '  Iterations: ' . $result->getIterations() . "\n";

        $usage = $result->getTokenUsage();
        echo "  Tokens: {$usage['input']} in + {$usage['output']} out = {$usage['total']} total\n";

        $toolCalls = $result->getToolCalls();
        if (! empty($toolCalls)) {
            echo "\n  Tools used:\n";
            foreach ($toolCalls as $call) {
                echo "    - {$call['tool']}\n";
            }
        }
    } else {
        echo "  Error: {$result->getError()}\n";
    }
} else {
    echo "\nStep 5: Skipped (no vendor API keys set)\n";
    echo "  To run a live cross-vendor workflow, add OPENAI_API_KEY and/or\n";
    echo "  GEMINI_API_KEY to your .env file.\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────

echo "\n" . str_repeat('=', 80) . "\n";
echo "Cross-Vendor DMF Summary\n";
echo str_repeat('=', 80) . "\n\n";

echo "Architecture:\n";
echo "  Claude (orchestrator) -> delegates to vendor tools -> vendor APIs\n\n";

echo "Available vendor tools:\n";
echo "  OpenAI (OPENAI_API_KEY):\n";
echo "    - openai_web_search     Real-time web search with citations\n";
echo "    - openai_image_generation  GPT Image 1.5 generation\n";
echo "    - openai_text_to_speech Text-to-speech with voice styles\n";
echo "    - vendor_chat           Cross-vendor model delegation\n\n";
echo "  Google Gemini (GEMINI_API_KEY):\n";
echo "    - gemini_grounding      Google Search grounded responses\n";
echo "    - gemini_code_execution Server-side Python execution\n";
echo "    - gemini_image_generation  Nano Banana image gen/editing\n";
echo "    - vendor_chat           Cross-vendor model delegation\n\n";

echo "Usage:\n";
echo "  \$factory = new CrossVendorToolFactory(VendorRegistry::fromEnvironment());\n";
echo "  \$tools = \$factory->createAllTools();\n";
echo "  \$agent = Agent::create(\$client)->withTools(\$tools)->run(\$task);\n\n";

echo str_repeat('=', 80) . "\n";
echo "DMF example completed.\n";
echo str_repeat('=', 80) . "\n";
