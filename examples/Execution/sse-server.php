<?php

/**
 * Server-Sent Events (SSE) Streaming Example
 *
 * Demonstrates:
 * - SSE endpoint for web-based streaming
 * - Real-time browser updates
 * - Event stream formatting
 * - Connection management
 *
 * Usage:
 *   1. Start server: php examples/Execution/sse-server.php
 *   2. Open browser: http://localhost:8000
 *   3. Watch real-time agent execution
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Check if running as CLI or web request
if (php_sapi_name() === 'cli') {
    // Start built-in PHP server
    if ($argc > 1 && $argv[1] === 'serve') {
        echo "Starting SSE server on http://localhost:8000\n";
        echo "Press Ctrl+C to stop\n\n";
        passthru('php -S localhost:8000 ' . __FILE__);
        exit(0);
    } else {
        echo "Usage: php sse-server.php serve\n";
        exit(1);
    }
}

// Handle web requests
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/') {
    // Serve HTML client
    serveHTMLClient();
} elseif ($path === '/stream') {
    // Serve SSE stream
    serveSSEStream();
} else {
    http_response_code(404);
    echo '404 Not Found';
}

/**
 * Serve the HTML client
 */
function serveHTMLClient(): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Agent Flow Streaming Demo</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #1a1a1a;
                color: #e0e0e0;
                padding: 20px;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            h1 {
                color: #4CAF50;
                margin-bottom: 10px;
            }
            .subtitle {
                color: #888;
                margin-bottom: 30px;
            }
            .controls {
                background: #2a2a2a;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            textarea {
                width: 100%;
                padding: 10px;
                background: #1a1a1a;
                border: 1px solid #444;
                border-radius: 4px;
                color: #e0e0e0;
                font-family: inherit;
                resize: vertical;
            }
            button {
                background: #4CAF50;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                margin-top: 10px;
            }
            button:hover { background: #45a049; }
            button:disabled {
                background: #666;
                cursor: not-allowed;
            }
            .output {
                background: #2a2a2a;
                padding: 20px;
                border-radius: 8px;
                min-height: 400px;
            }
            .response {
                background: #1a1a1a;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 15px;
                border-left: 4px solid #4CAF50;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .event {
                padding: 8px 12px;
                margin-bottom: 8px;
                border-radius: 4px;
                font-size: 14px;
            }
            .event.flow { background: #1e3a5f; color: #66b3ff; }
            .event.iteration { background: #3d2f5f; color: #b19cd9; }
            .event.tool { background: #5f3d2f; color: #ffb366; }
            .event.progress { background: #2f5f3d; color: #66ff99; }
            .event.error { background: #5f2f2f; color: #ff6666; }
            .progress-bar {
                width: 100%;
                height: 30px;
                background: #1a1a1a;
                border-radius: 15px;
                overflow: hidden;
                margin: 10px 0;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #66bb6a);
                transition: width 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
            }
            .status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
                margin-left: 10px;
            }
            .status.idle { background: #666; }
            .status.running { background: #4CAF50; animation: pulse 1.5s infinite; }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🤖 Agent Flow Streaming Demo</h1>
            <p class="subtitle">Real-time agent execution with Server-Sent Events</p>

            <div class="controls">
                <label for="task"><strong>Task:</strong></label>
                <textarea id="task" rows="3" placeholder="Enter your task here...">Calculate 15 * 23 and then add 100 to the result</textarea>
                <button id="startBtn">Start Agent</button>
                <span id="status" class="status idle">Idle</span>
            </div>

            <div class="output">
                <h2>Execution Output <span id="progress"></span></h2>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar" style="width: 0%">0%</div>
                </div>
                <div id="output"></div>
            </div>
        </div>

        <script>
            const taskInput = document.getElementById('task');
            const startBtn = document.getElementById('startBtn');
            const statusEl = document.getElementById('status');
            const outputEl = document.getElementById('output');
            const progressEl = document.getElementById('progress');
            const progressBar = document.getElementById('progressBar');
            let eventSource = null;

            startBtn.addEventListener('click', startAgent);

            function startAgent() {
                const task = taskInput.value.trim();
                if (!task) {
                    alert('Please enter a task');
                    return;
                }

                // Reset UI
                outputEl.innerHTML = '';
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';
                progressEl.textContent = '';
                
                // Update status
                startBtn.disabled = true;
                statusEl.textContent = 'Running';
                statusEl.className = 'status running';

                // Close existing connection
                if (eventSource) {
                    eventSource.close();
                }

                // Create new SSE connection
                const url = '/stream?task=' + encodeURIComponent(task);
                eventSource = new EventSource(url);
                
                let responseDiv = null;

                eventSource.addEventListener('flow_started', (e) => {
                    const data = JSON.parse(e.data);
                    addEvent('flow', `🚀 Flow started with agent: ${data.agent}`);
                });

                eventSource.addEventListener('token', (e) => {
                    const data = JSON.parse(e.data);
                    if (!responseDiv) {
                        responseDiv = document.createElement('div');
                        responseDiv.className = 'response';
                        outputEl.appendChild(responseDiv);
                    }
                    responseDiv.textContent += data.token;
                });

                eventSource.addEventListener('iteration_start', (e) => {
                    const data = JSON.parse(e.data);
                    addEvent('iteration', `⏱️ Iteration ${data.iteration} started`);
                });

                eventSource.addEventListener('tool_start', (e) => {
                    const data = JSON.parse(e.data);
                    addEvent('tool', `🔧 Executing tool: ${data.tool}`);
                });

                eventSource.addEventListener('tool_end', (e) => {
                    const data = JSON.parse(e.data);
                    addEvent('tool', `✅ Tool completed: ${data.tool}`);
                });

                eventSource.addEventListener('progress', (e) => {
                    const data = JSON.parse(e.data);
                    const percent = Math.round(data.progress_percent || 0);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                    progressEl.textContent = `(${data.current_iteration}/${data.total_iterations} iterations)`;
                });

                eventSource.addEventListener('error', (e) => {
                    const data = JSON.parse(e.data);
                    addEvent('error', `❌ Error: ${data.error}`);
                });

                eventSource.addEventListener('end', (e) => {
                    addEvent('flow', '✅ Flow completed');
                    statusEl.textContent = 'Idle';
                    statusEl.className = 'status idle';
                    startBtn.disabled = false;
                    eventSource.close();
                    responseDiv = null;
                });

                eventSource.onerror = () => {
                    addEvent('error', '❌ Connection error');
                    statusEl.textContent = 'Idle';
                    statusEl.className = 'status idle';
                    startBtn.disabled = false;
                    eventSource.close();
                };
            }

            function addEvent(type, message) {
                const div = document.createElement('div');
                div.className = 'event ' + type;
                div.textContent = message;
                outputEl.appendChild(div);
                outputEl.scrollTop = outputEl.scrollHeight;
            }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Serve SSE stream
 */
function serveSSEStream(): void
{
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        http_response_code(500);
        echo "data: " . json_encode(['error' => 'ANTHROPIC_API_KEY not set']) . "\n\n";
        return;
    }

    $task = $_GET['task'] ?? 'Say hello';

    // Set SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Disable nginx buffering

    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Create event system
    $eventQueue = new EventQueue(maxSize: 200);
    $eventManager = new FlowEventManager($eventQueue);
    $eventManager->registerDefaultEvents();

    // Create executor
    $executor = new StreamingFlowExecutor($eventManager, $eventQueue);

    // Create agent
    $client = new ClaudePhp(apiKey: $apiKey);
    $config = new AgentConfig(
        model: 'claude-sonnet-4-5',
        maxTokens: 512,
        maxIterations: 5
    );

    $calculatorTool = Tool::create(
        name: 'calculator',
        description: 'Perform arithmetic',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
            ],
            'required' => ['operation', 'a', 'b'],
        ],
        handler: function (array $input): string {
            $result = match ($input['operation']) {
                'add' => $input['a'] + $input['b'],
                'subtract' => $input['a'] - $input['b'],
                'multiply' => $input['a'] * $input['b'],
                'divide' => $input['b'] !== 0 ? $input['a'] / $input['b'] : 'Error: Division by zero',
                default => 'Error',
            };
            return "Result: {$result}";
        }
    );

    $agent = Agent::create($client, $config, [$calculatorTool]);

    try {
        // Stream events
        foreach ($executor->streamSSE($agent, $task) as $sseData) {
            echo $sseData;
            flush();
        }
    } catch (Exception $e) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }
}
