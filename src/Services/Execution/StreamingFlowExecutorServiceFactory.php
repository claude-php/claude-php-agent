<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Execution;

use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating StreamingFlowExecutor service instances.
 *
 * Automatically resolves FlowEventManager dependency if registered.
 */
class StreamingFlowExecutorServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::FLOW_EXECUTOR;
    protected string $serviceClass = StreamingFlowExecutor::class;

    /**
     * Create the StreamingFlowExecutor service.
     *
     * @param array<string, ServiceInterface> $dependencies Resolved dependencies
     * @return ServiceInterface The StreamingFlowExecutor instance
     */
    public function create(array $dependencies = []): ServiceInterface
    {
        // Get or create event manager
        $eventManager = $dependencies[ServiceType::EVENT_MANAGER->value] ?? null;

        if (!$eventManager instanceof FlowEventManager) {
            // Create standalone event manager if not provided
            $eventQueue = new EventQueue(maxSize: 100);
            $eventManager = new FlowEventManager($eventQueue);
            $eventManager->registerDefaultEvents();
        }

        // Get the queue from the event manager
        $eventQueue = $eventManager->getQueue();

        // Create executor
        return new StreamingFlowExecutor($eventManager, $eventQueue);
    }

    /**
     * Get dependencies for this service.
     *
     * @return array<ServiceType>
     */
    public function getDependencies(): array
    {
        // Optional dependency on EVENT_MANAGER
        return [ServiceType::EVENT_MANAGER];
    }
}
