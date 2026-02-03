<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Storage;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating StorageService instances.
 */
class StorageServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::STORAGE;
    protected string $serviceClass = LocalStorageService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
