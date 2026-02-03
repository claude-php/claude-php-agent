<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Execution;

use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating FlowEventManager service instances.
 */
class FlowEventManagerServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::EVENT_MANAGER;
    protected string $serviceClass = FlowEventManager::class;

    /**
     * Create the FlowEventManager service.
     *
     * @param array<string, ServiceInterface> $dependencies Resolved dependencies (none required)
     * @return ServiceInterface The FlowEventManager instance
     */
    public function create(array $dependencies = []): ServiceInterface
    {
        // Create event queue with default size
        $eventQueue = new EventQueue(maxSize: 100);

        // Create event manager with queue
        $eventManager = new FlowEventManager($eventQueue);

        // Register default Langflow-compatible events
        $eventManager->registerDefaultEvents();

        return $eventManager;
    }
}
