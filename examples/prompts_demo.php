#!/usr/bin/env php
<?php
/**
 * Prompts System - Comprehensive Demo
 *
 * Demonstrates all features of the Prompts system including templates,
 * chat templates, few-shot learning, prompt library, and composition.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Prompts\PromptTemplate;
use ClaudeAgents\Prompts\ChatTemplate;
use ClaudeAgents\Prompts\FewShotTemplate;
use ClaudeAgents\Prompts\PromptLibrary;
use ClaudeAgents\Prompts\PromptComposer;

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║              Prompts System - Comprehensive Demo                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// EXAMPLE 1: Basic PromptTemplate
// ============================================================================

echo "═══ Example 1: Basic PromptTemplate ═══\n\n";

$template = PromptTemplate::create(
    'Summarize the following text in {length}:\n\n{text}'
);

$prompt = $template->format([
    'text' => 'PHP is a popular server-side scripting language widely used for web development.',
    'length' => '5 words',
]);

echo "Template variables: " . implode(', ', $template->getVariables()) . "\n";
echo "\nFormatted prompt:\n$prompt\n\n";

// ============================================================================
// EXAMPLE 2: ChatTemplate for Conversations
// ============================================================================

echo "\n═══ Example 2: ChatTemplate for Conversations ═══\n\n";

$chatTemplate = ChatTemplate::create()
    ->system('You are a {role} expert specializing in {specialty}')
    ->user('I need help with {problem}')
    ->assistant('I\'d be happy to help with {problem}. Let me {action}.')
    ->user('Can you also {additional_request}?');

$messages = $chatTemplate->format([
    'role' => 'PHP',
    'specialty' => 'performance optimization',
    'problem' => 'slow database queries',
    'action' => 'analyze your query patterns',
    'additional_request' => 'suggest indexes',
]);

echo "Chat template with " . count($messages) . " messages:\n\n";
foreach ($messages as $i => $msg) {
    echo "[" . strtoupper($msg['role']) . "] " . $msg['content'] . "\n\n";
}

// ============================================================================
// EXAMPLE 3: FewShotTemplate for Classification
// ============================================================================

echo "═══ Example 3: FewShotTemplate for Classification ═══\n\n";

$sentimentExamples = [
    ['input' => 'I absolutely love this product!', 'output' => 'positive'],
    ['input' => 'This is the worst experience ever', 'output' => 'negative'],
    ['input' => 'It works as expected', 'output' => 'neutral'],
];

$fewShotTemplate = FewShotTemplate::forClassification(
    $sentimentExamples,
    ['positive', 'negative', 'neutral']
);

$sentimentPrompt = $fewShotTemplate->format([
    'input' => 'Amazing service and great support!'
]);

echo "Few-shot sentiment classification:\n\n";
echo $sentimentPrompt . "\n\n";

// ============================================================================
// EXAMPLE 4: FewShotTemplate for Extraction
// ============================================================================

echo "═══ Example 4: FewShotTemplate for Extraction ═══\n\n";

$extractionExamples = [
    [
        'input' => 'John Smith works at Google in New York',
        'output' => 'Person: John Smith, Organization: Google, Location: New York'
    ],
    [
        'input' => 'Marie Curie won the Nobel Prize in 1903',
        'output' => 'Person: Marie Curie, Award: Nobel Prize, Date: 1903'
    ],
];

$extractionTemplate = FewShotTemplate::forExtraction(
    $extractionExamples,
    'named entities'
);

$extractionPrompt = $extractionTemplate->format([
    'input' => 'Albert Einstein developed the theory of relativity in 1905'
]);

echo "Entity extraction with examples:\n\n";
echo $extractionPrompt . "\n\n";

// ============================================================================
// EXAMPLE 5: PromptLibrary - Pre-built Templates
// ============================================================================

echo "═══ Example 5: PromptLibrary - Pre-built Templates ═══\n\n";

// Summarization
$summaryTemplate = PromptLibrary::summarization();
$summaryPrompt = $summaryTemplate->format([
    'text' => 'Long article about PHP development and best practices...',
    'length' => '2 sentences',
]);
echo "Summarization prompt:\n$summaryPrompt\n\n";

// Code review
$codeReviewTemplate = PromptLibrary::codeReview();
$codeReviewPrompt = $codeReviewTemplate->format([
    'code' => 'function calculate($x, $y) { return $x + $y; }',
    'language' => 'PHP',
]);
echo "Code review prompt:\n$codeReviewPrompt\n\n";

// Question answering
$qaTemplate = PromptLibrary::questionAnswering();
$qaPrompt = $qaTemplate->format([
    'context' => 'PHP 8.0 introduced named arguments, union types, and JIT compilation.',
    'question' => 'What features were added in PHP 8.0?',
]);
echo "Q&A prompt:\n$qaPrompt\n\n";

// ============================================================================
// EXAMPLE 6: PromptComposer - Basic Composition
// ============================================================================

echo "═══ Example 6: PromptComposer - Basic Composition ═══\n\n";

$composer = PromptComposer::create()
    ->addText('Task: Analyze the following code')
    ->addText('Language: {language}')
    ->addText('Code:\n{code}')
    ->addText('Provide analysis focusing on:\n- Security\n- Performance\n- Best practices')
    ->withSeparator("\n\n");

$composedPrompt = $composer->compose([
    'language' => 'PHP',
    'code' => 'class User { private $name; public function getName() { return $this->name; } }',
]);

echo "Composed prompt:\n$composedPrompt\n\n";

// ============================================================================
// EXAMPLE 7: PromptComposer - Conditional Sections
// ============================================================================

echo "═══ Example 7: PromptComposer - Conditional Sections ═══\n\n";

$conditionalComposer = PromptComposer::create()
    ->addText('Main task: {task}')
    ->addIfVariable('context', 'Context: {context}')
    ->addIfVariable('examples', 'Examples:\n{examples}')
    ->addConditional(
        'Note: This is a priority task',
        fn($values) => ($values['priority'] ?? '') === 'high'
    )
    ->addText('Please provide detailed output.');

// With all optional fields
$prompt1 = $conditionalComposer->compose([
    'task' => 'Code optimization',
    'context' => 'Legacy codebase',
    'examples' => '- Example 1\n- Example 2',
    'priority' => 'high',
]);

echo "With all optional fields:\n$prompt1\n\n";

// Without optional fields
$prompt2 = $conditionalComposer->compose([
    'task' => 'Code optimization',
]);

echo "Without optional fields:\n$prompt2\n\n";

// ============================================================================
// EXAMPLE 8: PromptComposer - Lists
// ============================================================================

echo "═══ Example 8: PromptComposer - Lists ═══\n\n";

$listComposer = PromptComposer::create()
    ->addText('Project Requirements:')
    ->addList('requirements', '✓ {item}')
    ->addText('Constraints:')
    ->addList('constraints', '⚠ {item}')
    ->addText('Deliverables:')
    ->addList('deliverables', '→ {item}');

$listPrompt = $listComposer->compose([
    'requirements' => ['Fast response time', 'Secure authentication', 'Scalable architecture'],
    'constraints' => ['Budget: $10k', 'Timeline: 2 months', 'Team: 3 developers'],
    'deliverables' => ['Working prototype', 'Documentation', 'Test suite'],
]);

echo "List composition:\n$listPrompt\n\n";

// ============================================================================
// EXAMPLE 9: PromptComposer - Examples Section
// ============================================================================

echo "═══ Example 9: PromptComposer - Examples Section ═══\n\n";

$exampleComposer = PromptComposer::create()
    ->addText('Task: Classify programming languages by paradigm')
    ->addExamples('examples', 'Language: ', 'Paradigm: ')
    ->addText('Now classify: {input}');

$examplePrompt = $exampleComposer->compose([
    'examples' => [
        ['input' => 'Python', 'output' => 'Multi-paradigm (OOP, Procedural, Functional)'],
        ['input' => 'Haskell', 'output' => 'Functional'],
        ['input' => 'Java', 'output' => 'Object-Oriented'],
    ],
    'input' => 'Rust',
]);

echo "Examples composition:\n$examplePrompt\n\n";

// ============================================================================
// EXAMPLE 10: PromptComposer - Built-in Patterns
// ============================================================================

echo "═══ Example 10: PromptComposer - Built-in Patterns ═══\n\n";

// Chain of Thought
$cotComposer = PromptComposer::chainOfThought();
$cotPrompt = $cotComposer->compose([
    'problem' => 'If a train travels 120 miles in 2 hours, what is its average speed?'
]);

echo "Chain of Thought pattern:\n$cotPrompt\n\n";

// RAG (Retrieval Augmented Generation)
$ragComposer = PromptComposer::rag();
$ragPrompt = $ragComposer->compose([
    'context' => 'PHP 8.1 introduced enums, fibers, and readonly properties.',
    'question' => 'What are the new features in PHP 8.1?',
]);

echo "RAG pattern:\n$ragPrompt\n\n";

// ============================================================================
// EXAMPLE 11: Template Validation
// ============================================================================

echo "═══ Example 11: Template Validation ═══\n\n";

$validationTemplate = PromptTemplate::create('Translate {text} from {source} to {target}');

try {
    echo "Attempting validation with incomplete data...\n";
    $validationTemplate->validate(['text' => 'Hello']);
} catch (\InvalidArgumentException $e) {
    echo "✓ Caught validation error (as expected):\n";
    echo "  " . $e->getMessage() . "\n\n";
}

try {
    echo "Validating with complete data...\n";
    $validationTemplate->validate([
        'text' => 'Hello',
        'source' => 'English',
        'target' => 'Spanish',
    ]);
    echo "✓ Validation passed!\n\n";
} catch (\InvalidArgumentException $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXAMPLE 12: Partial Templates
// ============================================================================

echo "═══ Example 12: Partial Templates ═══\n\n";

$baseTranslation = PromptTemplate::create(
    'Translate {text} from {source_language} to {target_language}'
);

// Create specialized templates
$toSpanish = $baseTranslation->partial(['target_language' => 'Spanish']);
$toFrench = $baseTranslation->partial(['target_language' => 'French']);

$spanishPrompt = $toSpanish->format([
    'text' => 'Hello world',
    'source_language' => 'English',
]);

$frenchPrompt = $toFrench->format([
    'text' => 'Hello world',
    'source_language' => 'English',
]);

echo "Spanish translation template:\n$spanishPrompt\n\n";
echo "French translation template:\n$frenchPrompt\n\n";

// ============================================================================
// EXAMPLE 13: Complex Real-World Scenario
// ============================================================================

echo "═══ Example 13: Complex Real-World Scenario ═══\n\n";

// Code review with context-aware prompting
$codeReviewComposer = PromptComposer::create()
    ->addText('=== CODE REVIEW REQUEST ===')
    ->addText('Language: {language}')
    ->addIfVariable('project_context', 'Project Context: {project_context}')
    ->addText('Code to Review:\n```{language}\n{code}\n```')
    ->addIfVariable('previous_issues', 'Previous Issues Found:\n{previous_issues}')
    ->addList('focus_areas', '• {item}', 'Focus Areas:')
    ->addIfVariable('coding_standards', 'Coding Standards: {coding_standards}')
    ->addText('Please provide:')
    ->addList('output_format', '{item}', '')
    ->withSeparator("\n\n");

$complexPrompt = $codeReviewComposer->compose([
    'language' => 'PHP',
    'project_context' => 'E-commerce payment processing module',
    'code' => 'function processPayment($amount) { /* ... */ }',
    'previous_issues' => '- Missing input validation\n- No error handling',
    'focus_areas' => ['Security vulnerabilities', 'Error handling', 'Input validation'],
    'coding_standards' => 'PSR-12, strict types',
    'output_format' => [
        '1. Security analysis',
        '2. Code quality assessment',
        '3. Specific recommendations',
        '4. Priority of fixes',
    ],
]);

echo "Complex code review prompt:\n$complexPrompt\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                            Demo Complete!                              ║\n";
echo "╠════════════════════════════════════════════════════════════════════════╣\n";
echo "║ Demonstrated Features:                                                 ║\n";
echo "║  ✓ PromptTemplate - Basic variable substitution                       ║\n";
echo "║  ✓ ChatTemplate - Multi-turn conversations                            ║\n";
echo "║  ✓ FewShotTemplate - Few-shot learning with examples                  ║\n";
echo "║  ✓ PromptLibrary - Pre-built common templates                         ║\n";
echo "║  ✓ PromptComposer - Complex composition with conditions               ║\n";
echo "║  ✓ Validation - Error handling and helpful messages                   ║\n";
echo "║  ✓ Partial Templates - Template specialization                        ║\n";
echo "║  ✓ Real-world scenarios - Production-ready examples                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n";

