<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Transaction\TransactionService;
use PHPUnit\Framework\TestCase;

class TransactionServiceTest extends TestCase
{
    private TransactionService $service;

    protected function setUp(): void
    {
        $this->service = new TransactionService();
        $this->service->initialize();
    }

    public function testGetName(): void
    {
        $this->assertSame('transaction', $this->service->getName());
    }

    public function testBeginAndCommit(): void
    {
        $this->assertFalse($this->service->isInTransaction());

        $this->service->begin();

        $this->assertTrue($this->service->isInTransaction());
        $this->assertSame(1, $this->service->getTransactionLevel());

        $this->service->commit();

        $this->assertFalse($this->service->isInTransaction());
    }

    public function testBeginAndRollback(): void
    {
        $this->service->begin();
        $this->assertTrue($this->service->isInTransaction());

        $this->service->rollback();

        $this->assertFalse($this->service->isInTransaction());
    }

    public function testNestedTransactions(): void
    {
        $this->service->begin();
        $this->assertSame(1, $this->service->getTransactionLevel());

        $this->service->begin();
        $this->assertSame(2, $this->service->getTransactionLevel());

        $this->service->commit();
        $this->assertSame(1, $this->service->getTransactionLevel());

        $this->service->commit();
        $this->assertSame(0, $this->service->getTransactionLevel());
    }

    public function testInTransactionSuccess(): void
    {
        $executed = false;

        $result = $this->service->inTransaction(function () use (&$executed) {
            $executed = true;
            return 'result';
        });

        $this->assertTrue($executed);
        $this->assertSame('result', $result);
        $this->assertFalse($this->service->isInTransaction());
    }

    public function testInTransactionRollback(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        try {
            $this->service->inTransaction(function () {
                throw new \RuntimeException('Test error');
            });
        } finally {
            $this->assertFalse($this->service->isInTransaction());
        }
    }

    public function testAfterCommit(): void
    {
        $callbackExecuted = false;

        $this->service->begin();
        $this->service->afterCommit(function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

        $this->assertFalse($callbackExecuted);

        $this->service->commit();

        $this->assertTrue($callbackExecuted);
    }

    public function testAfterCommitNotCalledOnRollback(): void
    {
        $callbackExecuted = false;

        $this->service->begin();
        $this->service->afterCommit(function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

        $this->service->rollback();

        $this->assertFalse($callbackExecuted);
    }

    public function testCommitWithoutBegin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not in a transaction');

        $this->service->commit();
    }

    public function testRollbackWithoutBegin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not in a transaction');

        $this->service->rollback();
    }

    public function testTeardownRollsBackPendingTransactions(): void
    {
        $this->service->begin();
        $this->service->begin();

        $this->assertSame(2, $this->service->getTransactionLevel());

        $this->service->teardown();

        $this->assertFalse($this->service->isInTransaction());
    }
}
