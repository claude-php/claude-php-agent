<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Cache;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating CacheService instances.
 */
class CacheServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::CACHE;
    protected string $serviceClass = CacheService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
