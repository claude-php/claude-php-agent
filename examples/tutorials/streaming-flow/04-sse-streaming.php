<?php

/**
 * Tutorial 4: Server-Sent Events (SSE) Streaming
 * 
 * Learn how to:
 * - Set up SSE endpoints for web clients
 * - Format events for SSE protocol
 * - Handle client connections
 * - Build real-time web UIs
 * 
 * Estimated time: 15 minutes
 * 
 * Usage:
 *   php 04-sse-streaming.php serve
 *   Then open: http://localhost:8080
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Check if running as CLI or web
if (php_sapi_name() === 'cli') {
    if ($argc > 1 && $argv[1] === 'serve') {
        echo "========================================\n";
        echo "Tutorial 4: SSE Streaming Server\n";
        echo "========================================\n\n";
        echo "Starting server on http://localhost:8080\n";
        echo "Press Ctrl+C to stop\n\n";
        echo "Open http://localhost:8080 in your browser\n\n";
        
        passthru('php -S localhost:8080 ' . __FILE__);
        exit(0);
    } else {
        echo "Usage: php 04-sse-streaming.php serve\n";
        exit(1);
    }
}

// Handle web requests
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if ($path === '/') {
    serveHTMLClient();
} elseif ($path === '/stream') {
    serveSSEStream();
} else {
    http_response_code(404);
    echo '404 Not Found';
}

/**
 * Serve the HTML client
 */
function serveHTMLClient(): void {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tutorial 4: SSE Streaming</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', system-ui, sans-serif;
                background: #f5f5f5;
                padding: 20px;
                line-height: 1.6;
            }
            .container {
                max-width: 900px;
                margin: 0 auto;
            }
            h1 {
                color: #2563eb;
                margin-bottom: 10px;
            }
            .subtitle {
                color: #64748b;
                margin-bottom: 30px;
            }
            .card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            textarea {
                width: 100%;
                padding: 12px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                font-family: inherit;
                font-size: 14px;
                resize: vertical;
            }
            button {
                background: #2563eb;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 500;
                margin-top: 10px;
                transition: background 0.2s;
            }
            button:hover { background: #1d4ed8; }
            button:disabled {
                background: #cbd5e1;
                cursor: not-allowed;
            }
            .status {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
                margin-left: 12px;
            }
            .status.idle { background: #e2e8f0; color: #475569; }
            .status.running {
                background: #dbeafe;
                color: #1d4ed8;
                animation: pulse 1.5s ease-in-out infinite;
            }
            @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
            .progress-bar {
                width: 100%;
                height: 24px;
                background: #e2e8f0;
                border-radius: 12px;
                overflow: hidden;
                margin: 15px 0;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #2563eb, #3b82f6);
                transition: width 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 12px;
                font-weight: 600;
            }
            .output {
                min-height: 300px;
                max-height: 500px;
                overflow-y: auto;
                background: #1e293b;
                color: #e2e8f0;
                padding: 15px;
                border-radius: 6px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .event-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                margin-right: 6px;
            }
            .event-badge.flow { background: #3b82f6; color: white; }
            .event-badge.tool { background: #f59e0b; color: white; }
            .event-badge.progress { background: #10b981; color: white; }
            .event-badge.error { background: #ef4444; color: white; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🌊 Tutorial 4: SSE Streaming</h1>
            <p class="subtitle">Real-time Server-Sent Events demonstration</p>

            <div class="card">
                <h3>Task Input</h3>
                <textarea id="task" rows="2" placeholder="Enter your task...">Use the echo tool to repeat 'Hello from SSE!'</textarea>
                <button id="startBtn">Start Streaming</button>
                <span id="status" class="status idle">Idle</span>
            </div>

            <div class="card">
                <h3>Progress</h3>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar" style="width: 0%">0%</div>
                </div>
                <small id="progressText">Ready to start...</small>
            </div>

            <div class="card">
                <h3>Output Stream</h3>
                <div class="output" id="output">Waiting for events...</div>
            </div>
        </div>

        <script>
            const taskInput = document.getElementById('task');
            const startBtn = document.getElementById('startBtn');
            const statusEl = document.getElementById('status');
            const outputEl = document.getElementById('output');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            let eventSource = null;
            let responseText = '';

            startBtn.addEventListener('click', startStreaming);

            function startStreaming() {
                const task = taskInput.value.trim();
                if (!task) {
                    alert('Please enter a task');
                    return;
                }

                // Reset
                outputEl.textContent = '';
                responseText = '';
                updateProgress(0, 'Starting...');
                
                // Update UI
                startBtn.disabled = true;
                statusEl.textContent = 'Running';
                statusEl.className = 'status running';

                // Close existing connection
                if (eventSource) eventSource.close();

                // Create SSE connection
                const url = '/stream?task=' + encodeURIComponent(task);
                eventSource = new EventSource(url);
                
                eventSource.addEventListener('flow_started', (e) => {
                    const data = JSON.parse(e.data);
                    addOutput('flow', '🚀 Flow started');
                });

                eventSource.addEventListener('token', (e) => {
                    const data = JSON.parse(e.data);
                    responseText += data.data.token;
                    outputEl.textContent += data.data.token;
                    outputEl.scrollTop = outputEl.scrollHeight;
                });

                eventSource.addEventListener('tool_start', (e) => {
                    const data = JSON.parse(e.data);
                    addOutput('tool', `🔧 Using tool: ${data.data.tool}`);
                });

                eventSource.addEventListener('tool_end', (e) => {
                    const data = JSON.parse(e.data);
                    addOutput('tool', `✅ Tool result: ${data.data.result}`);
                });

                eventSource.addEventListener('progress', (e) => {
                    const data = JSON.parse(e.data);
                    const percent = Math.round(data.data.progress_percent || 0);
                    const current = data.data.current_iteration || 0;
                    const total = data.data.total_iterations || 0;
                    updateProgress(percent, `${current}/${total} iterations`);
                });

                eventSource.addEventListener('error_event', (e) => {
                    const data = JSON.parse(e.data);
                    addOutput('error', `❌ Error: ${data.data.error || data.data.message}`);
                });

                eventSource.addEventListener('end', (e) => {
                    addOutput('flow', '✅ Flow completed');
                    updateProgress(100, 'Complete!');
                    cleanup();
                });

                eventSource.onerror = () => {
                    addOutput('error', '❌ Connection error');
                    cleanup();
                };
            }

            function addOutput(type, message) {
                const badge = `<span class="event-badge ${type}">${type.toUpperCase()}</span>`;
                outputEl.innerHTML += `\n${badge}${message}`;
                outputEl.scrollTop = outputEl.scrollHeight;
            }

            function updateProgress(percent, text) {
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                progressText.textContent = text;
            }

            function cleanup() {
                startBtn.disabled = false;
                statusEl.textContent = 'Idle';
                statusEl.className = 'status idle';
                if (eventSource) eventSource.close();
            }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Serve SSE stream
 */
function serveSSEStream(): void {
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        http_response_code(500);
        echo "data: " . json_encode(['error' => 'API key not set']) . "\n\n";
        return;
    }

    $task = $_GET['task'] ?? 'Say hello';

    // Set SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    // Disable buffering
    if (ob_get_level()) ob_end_clean();

    // Create components
    $eventQueue = new EventQueue(maxSize: 200);
    $eventManager = new FlowEventManager($eventQueue);
    $eventManager->registerDefaultEvents();
    $executor = new StreamingFlowExecutor($eventManager, $eventQueue);

    // Create agent
    $client = new ClaudePhp(apiKey: $apiKey);
    $config = new AgentConfig(
        model: 'claude-3-5-sonnet-20241022',
        maxTokens: 512,
        maxIterations: 3
    );

    $echoTool = Tool::create(
        name: 'echo',
        description: 'Echo the message',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
            ],
            'required' => ['message'],
        ],
        handler: fn($input) => "Echo: {$input['message']}"
    );

    $agent = Agent::create($client, $config, [$echoTool]);

    try {
        // Stream events as SSE
        foreach ($executor->streamSSE($agent, $task) as $sseData) {
            echo $sseData;
            flush();
            
            // Check if client disconnected
            if (connection_aborted()) break;
        }
    } catch (Exception $e) {
        echo "event: error_event\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }
}
