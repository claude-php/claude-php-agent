<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Transaction;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating TransactionService instances.
 */
class TransactionServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::TRANSACTION;
    protected string $serviceClass = TransactionService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return new TransactionService();
    }
}
