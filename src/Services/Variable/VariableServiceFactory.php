<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Variable;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating VariableService instances.
 */
class VariableServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::VARIABLE;
    protected string $serviceClass = VariableService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
