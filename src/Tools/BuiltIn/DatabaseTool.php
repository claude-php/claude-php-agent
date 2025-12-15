<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use PDO;
use PDOException;

/**
 * Database tool for safe SQL query execution.
 */
class DatabaseTool
{
    /**
     * Create a database tool.
     *
     * @param array{
     *     connection: PDO,
     *     read_only?: bool,
     *     max_results?: int,
     *     timeout?: int,
     *     allowed_tables?: array<string>
     * } $config Configuration options
     */
    public static function create(array $config): Tool
    {
        if (! isset($config['connection'])) {
            throw new \InvalidArgumentException('PDO connection is required');
        }

        $connection = $config['connection'];
        $readOnly = $config['read_only'] ?? true; // Default to read-only for safety
        $maxResults = $config['max_results'] ?? 1000;
        $timeout = $config['timeout'] ?? 30;
        $allowedTables = $config['allowed_tables'] ?? [];

        return Tool::create('database')
            ->description(
                'Execute SQL queries against the database. ' .
                ($readOnly ? 'READ-ONLY MODE - only SELECT queries allowed. ' : 'All SQL operations allowed. ') .
                'Use parameterized queries to prevent SQL injection.'
            )
            ->stringParam('query', 'SQL query to execute')
            ->arrayParam('parameters', 'Query parameters for binding (prevents SQL injection)', false)
            ->numberParam('limit', 'Maximum number of results to return', false, 1, $maxResults)
            ->handler(function (array $input) use (
                $connection,
                $readOnly,
                $maxResults,
                $timeout,
                $allowedTables
            ): ToolResult {
                $query = trim($input['query']);
                $parameters = $input['parameters'] ?? [];
                $limit = (int) ($input['limit'] ?? $maxResults);

                if (empty($query)) {
                    return ToolResult::error('Query parameter is required');
                }

                // Security: Validate query type if read-only mode
                if ($readOnly) {
                    if (! preg_match('/^\s*SELECT\s+/i', $query)) {
                        return ToolResult::error(
                            'Only SELECT queries allowed in read-only mode'
                        );
                    }

                    // Additional security: check for potentially dangerous operations
                    $dangerous = ['INTO OUTFILE', 'LOAD_FILE', 'LOAD DATA', 'CALL', 'EXEC'];
                    foreach ($dangerous as $danger) {
                        if (stripos($query, $danger) !== false) {
                            return ToolResult::error(
                                "Query contains forbidden operation: {$danger}"
                            );
                        }
                    }
                }

                // Security: Check for allowed tables if configured
                if (! empty($allowedTables)) {
                    $foundTable = false;
                    foreach ($allowedTables as $table) {
                        if (stripos($query, $table) !== false) {
                            $foundTable = true;

                            break;
                        }
                    }

                    if (! $foundTable) {
                        return ToolResult::error(
                            'Query must reference one of the allowed tables: ' . implode(', ', $allowedTables)
                        );
                    }
                }

                try {
                    // Set timeout if supported
                    $connection->setAttribute(PDO::ATTR_TIMEOUT, $timeout);
                    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $startTime = microtime(true);

                    // Prepare and execute
                    $stmt = $connection->prepare($query);
                    $stmt->execute($parameters);

                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                    // Handle different query types
                    $queryType = strtoupper(explode(' ', trim($query))[0]);

                    if ($queryType === 'SELECT' || $queryType === 'SHOW' || $queryType === 'DESCRIBE') {
                        // Fetch results with limit
                        $results = [];
                        $rowCount = 0;

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $results[] = $row;
                            $rowCount++;

                            if ($rowCount >= $limit) {
                                break;
                            }
                        }

                        $hasMore = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

                        return ToolResult::success([
                            'rows' => $results,
                            'count' => $rowCount,
                            'has_more' => $hasMore,
                            'execution_time_ms' => $executionTime,
                            'columns' => ! empty($results) ? array_keys($results[0]) : [],
                        ]);
                    }
                    // For INSERT, UPDATE, DELETE, etc.
                    $affectedRows = $stmt->rowCount();

                    return ToolResult::success([
                        'affected_rows' => $affectedRows,
                        'execution_time_ms' => $executionTime,
                        'query_type' => $queryType,
                    ]);

                } catch (PDOException $e) {
                    return ToolResult::error("Database error: {$e->getMessage()}");
                } catch (\Throwable $e) {
                    return ToolResult::error("Query execution error: {$e->getMessage()}");
                }
            });
    }

    /**
     * Create a read-only database tool (convenience method).
     *
     * @param PDO $connection Database connection
     * @param array{
     *     max_results?: int,
     *     timeout?: int,
     *     allowed_tables?: array<string>
     * } $config Additional configuration
     */
    public static function readOnly(PDO $connection, array $config = []): Tool
    {
        return self::create(array_merge($config, [
            'connection' => $connection,
            'read_only' => true,
        ]));
    }

    /**
     * Create a full-access database tool (USE WITH CAUTION).
     *
     * @param PDO $connection Database connection
     * @param array{
     *     max_results?: int,
     *     timeout?: int,
     *     allowed_tables?: array<string>
     * } $config Additional configuration
     */
    public static function fullAccess(PDO $connection, array $config = []): Tool
    {
        return self::create(array_merge($config, [
            'connection' => $connection,
            'read_only' => false,
        ]));
    }
}
