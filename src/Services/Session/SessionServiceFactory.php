<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Session;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating SessionService instances.
 */
class SessionServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::SESSION;
    protected string $serviceClass = SessionService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
