<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Telemetry;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating TelemetryService instances.
 */
class TelemetryServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::TELEMETRY;
    protected string $serviceClass = TelemetryService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
