<?php

declare(strict_types=1);

namespace ClaudeAgents\Async;

use function Amp\async;

use Amp\Future;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Tools\ToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Executes multiple tools in parallel using AMPHP.
 *
 * This leverages AMPHP's concurrent execution capabilities to truly
 * run multiple tool executions in parallel.
 */
class ParallelToolExecutor
{
    private LoggerInterface $logger;

    /**
     * @param array<ToolInterface> $tools Available tools
     */
    public function __construct(
        private readonly array $tools,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Execute multiple tool calls in parallel using AMPHP.
     *
     * @param array<array{tool: string, input: array}> $calls Tool calls to execute
     * @return array<array{tool: string, result: mixed}> Results from each call
     */
    public function execute(array $calls): array
    {
        $this->logger->debug('Executing ' . count($calls) . ' tools in parallel');

        // Create async tasks for each tool call
        $futures = [];
        foreach ($calls as $index => $call) {
            $futures[$index] = $this->executeToolAsync($call);
        }

        // Wait for all to complete
        $results = [];
        foreach ($futures as $index => $future) {
            $results[$index] = $future->await();
        }

        $this->logger->debug('Completed ' . count($results) . ' tool executions');

        return $results;
    }

    /**
     * Execute a single tool asynchronously.
     *
     * @param array{tool: string, input: array} $call
     * @return Future<array{tool: string, input: array, result: ToolResult}>
     */
    private function executeToolAsync(array $call): Future
    {
        return async(function () use ($call) {
            $toolName = $call['tool'] ?? '';
            $input = $call['input'] ?? [];

            $this->logger->debug("Executing tool: {$toolName}");

            // Find the tool
            $tool = $this->findTool($toolName);

            if ($tool === null) {
                $result = ToolResult::error("Unknown tool: {$toolName}");
            } else {
                try {
                    $result = $tool->execute($input);
                } catch (\Throwable $e) {
                    $this->logger->error("Tool {$toolName} failed: {$e->getMessage()}");
                    $result = ToolResult::error($e->getMessage());
                }
            }

            return [
                'tool' => $toolName,
                'input' => $input,
                'result' => $result,
            ];
        });
    }

    /**
     * Execute tools asynchronously and return promises.
     *
     * @param array<array{tool: string, input: array}> $calls
     * @return array<Promise> Promises for each tool execution
     */
    public function executeAsync(array $calls): array
    {
        $promises = [];

        foreach ($calls as $call) {
            $promise = new Promise();

            // Execute async and resolve promise when done
            $future = $this->executeToolAsync($call);

            async(function () use ($future, $promise) {
                try {
                    $result = $future->await();
                    $promise->resolve($result);
                } catch (\Throwable $e) {
                    $promise->reject($e);
                }
            });

            $promises[] = $promise;
        }

        return $promises;
    }

    /**
     * Find a tool by name.
     *
     * @param string $name Tool name
     * @return ToolInterface|null
     */
    private function findTool(string $name): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Wait for all promises to resolve.
     *
     * @param array<Promise> $promises
     * @param int $timeoutMs Timeout in milliseconds (unused with new implementation)
     * @return array<mixed> Resolved values
     */
    public static function waitAll(array $promises, int $timeoutMs = 30000): array
    {
        return Promise::all($promises);
    }

    /**
     * Execute a batch of tool calls with concurrency limit.
     *
     * @param array<array{tool: string, input: array}> $calls Tool calls
     * @param int $concurrency Maximum concurrent executions
     * @return array<array{tool: string, result: mixed}> Results
     */
    public function executeBatched(array $calls, int $concurrency = 5): array
    {
        $this->logger->debug('Executing ' . count($calls) . " tools with concurrency: {$concurrency}");

        $results = [];
        $batches = array_chunk($calls, $concurrency);

        foreach ($batches as $batch) {
            $batchResults = $this->execute($batch);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }
}
