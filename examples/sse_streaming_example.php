<?php

declare(strict_types=1);

require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Streaming\SSEStreamAdapter;
use ClaudeAgents\Streaming\SSEServer;
use ClaudePhp\ClaudePhp;

/**
 * Example: Server-Sent Events (SSE) streaming for code generation.
 *
 * This demonstrates real-time streaming of code generation progress
 * to web clients using SSE.
 *
 * Usage:
 * 1. Run this script: php examples/sse_streaming_example.php
 * 2. Open your browser and use the EventSource API:
 *
 * ```javascript
 * const eventSource = new EventSource('http://localhost:8000/sse_streaming_example.php');
 * 
 * eventSource.addEventListener('code.generating', (e) => {
 *     console.log('Generating...', JSON.parse(e.data));
 * });
 * 
 * eventSource.addEventListener('code.generated', (e) => {
 *     const data = JSON.parse(e.data);
 *     console.log(`Generated ${data.line_count} lines`);
 * });
 * 
 * eventSource.addEventListener('validation.passed', (e) => {
 *     console.log('Validation passed!');
 * });
 * 
 * eventSource.addEventListener('component.completed', (e) => {
 *     console.log('Complete!', JSON.parse(e.data));
 *     eventSource.close();
 * });
 * ```
 */

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    header('Content-Type: text/plain');
    echo "Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

// Setup SSE headers
SSEServer::setupHeaders();

// Send initial comment
SSEServer::sendComment('Code Generation SSE Stream Starting...');

try {
    $client = new ClaudePhp(apiKey: $apiKey);

    // Setup validation
    $validator = new ValidationCoordinator();
    $validator->addValidator(new PHPSyntaxValidator());

    // Create agent
    $agent = new CodeGenerationAgent($client, [
        'max_validation_retries' => 2,
        'validation_coordinator' => $validator,
    ]);

    // Create SSE adapter
    $sseAdapter = new SSEStreamAdapter([
        'auto_flush' => true,
        'include_comments' => true,
    ]);

    // Attach SSE callback to agent
    $agent->onUpdate($sseAdapter->createCodeGenerationCallback());

    // Send start event
    SSEServer::sendEvent('stream.started', [
        'timestamp' => date('c'),
        'max_retries' => 2,
    ]);

    // Generate component
    $description = <<<DESC
Create a Logger class with:
- Namespace: App\Services
- Methods: debug(), info(), warning(), error()
- Each method accepts a message and optional context array
- Include PSR-3 compliant interface
DESC;

    $result = $agent->generateComponent($description);

    // Send completion event
    if ($result->isValid()) {
        SSEServer::sendEvent('stream.completed', [
            'success' => true,
            'code_length' => strlen($result->getCode()),
            'attempts' => $result->getMetadata()['attempts'],
            'summary' => $result->getSummary(),
        ]);
    } else {
        SSEServer::sendEvent('stream.failed', [
            'success' => false,
            'errors' => $result->getValidation()->getErrors(),
        ]);
    }
} catch (\Throwable $e) {
    SSEServer::sendEvent('stream.error', [
        'error' => $e->getMessage(),
        'type' => get_class($e),
    ]);
}

// Send final comment
SSEServer::sendComment('Stream complete');
