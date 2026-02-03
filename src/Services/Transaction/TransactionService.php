<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Transaction;

use ClaudeAgents\Services\ServiceInterface;

/**
 * Transaction service for database transaction management.
 *
 * Provides begin/commit/rollback with nested transaction support.
 * This is a placeholder for future database integration.
 */
class TransactionService implements ServiceInterface
{
    private bool $ready = false;
    private int $transactionLevel = 0;

    /**
     * @var array<callable> Transaction completion callbacks
     */
    private array $afterCommitCallbacks = [];

    public function getName(): string
    {
        return 'transaction';
    }

    public function initialize(): void
    {
        $this->ready = true;
    }

    public function teardown(): void
    {
        // Rollback any pending transactions
        while ($this->transactionLevel > 0) {
            $this->rollback();
        }

        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'begin' => [
                    'parameters' => [],
                    'return' => 'void',
                    'description' => 'Begin a transaction',
                ],
                'commit' => [
                    'parameters' => [],
                    'return' => 'void',
                    'description' => 'Commit a transaction',
                ],
                'rollback' => [
                    'parameters' => [],
                    'return' => 'void',
                    'description' => 'Rollback a transaction',
                ],
                'inTransaction' => [
                    'parameters' => ['callback' => 'callable'],
                    'return' => 'mixed',
                    'description' => 'Execute callback within a transaction',
                ],
            ],
        ];
    }

    /**
     * Begin a transaction.
     *
     * Supports nested transactions using savepoints.
     *
     * @return void
     */
    public function begin(): void
    {
        $this->transactionLevel++;

        // Future: Implement actual database BEGIN or SAVEPOINT
    }

    /**
     * Commit a transaction.
     *
     * @return void
     * @throws \RuntimeException If not in a transaction
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            throw new \RuntimeException('Not in a transaction');
        }

        $this->transactionLevel--;

        // Execute callbacks only on final commit
        if ($this->transactionLevel === 0) {
            foreach ($this->afterCommitCallbacks as $callback) {
                $callback();
            }
            $this->afterCommitCallbacks = [];
        }

        // Future: Implement actual database COMMIT or RELEASE SAVEPOINT
    }

    /**
     * Rollback a transaction.
     *
     * @return void
     * @throws \RuntimeException If not in a transaction
     */
    public function rollback(): void
    {
        if ($this->transactionLevel === 0) {
            throw new \RuntimeException('Not in a transaction');
        }

        $this->transactionLevel--;

        // Clear callbacks on rollback
        if ($this->transactionLevel === 0) {
            $this->afterCommitCallbacks = [];
        }

        // Future: Implement actual database ROLLBACK or ROLLBACK TO SAVEPOINT
    }

    /**
     * Execute a callback within a transaction.
     *
     * Automatically commits on success, rolls back on exception.
     *
     * @param callable $callback Callback to execute
     * @return mixed Callback return value
     * @throws \Throwable Re-throws any exception from callback
     */
    public function inTransaction(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Register a callback to execute after successful commit.
     *
     * Useful for cache invalidation, event dispatching, etc.
     *
     * @param callable $callback Callback to execute
     * @return void
     */
    public function afterCommit(callable $callback): void
    {
        $this->afterCommitCallbacks[] = $callback;
    }

    /**
     * Check if currently in a transaction.
     *
     * @return bool
     */
    public function isInTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Get the current transaction level.
     *
     * @return int
     */
    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }
}
