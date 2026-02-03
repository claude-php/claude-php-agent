<?php

declare(strict_types=1);

namespace ClaudeAgents\Services;

/**
 * Enumeration of all available service types.
 *
 * Provides type-safe service resolution and naming convention.
 */
enum ServiceType: string
{
    case SETTINGS = 'settings';
    case CACHE = 'cache';
    case STORAGE = 'storage';
    case VARIABLE = 'variable';
    case TRACING = 'tracing';
    case TELEMETRY = 'telemetry';
    case SESSION = 'session';
    case TRANSACTION = 'transaction';
    case FLOW_EXECUTOR = 'flow_executor';
    case EVENT_MANAGER = 'event_manager';

    /**
     * Get the fully qualified class name for this service type.
     *
     * @return string Expected service class name
     */
    public function getClassName(): string
    {
        return match ($this) {
            self::SETTINGS => 'ClaudeAgents\\Services\\Settings\\SettingsService',
            self::CACHE => 'ClaudeAgents\\Services\\Cache\\CacheService',
            self::STORAGE => 'ClaudeAgents\\Services\\Storage\\StorageService',
            self::VARIABLE => 'ClaudeAgents\\Services\\Variable\\VariableService',
            self::TRACING => 'ClaudeAgents\\Services\\Tracing\\TracingService',
            self::TELEMETRY => 'ClaudeAgents\\Services\\Telemetry\\TelemetryService',
            self::SESSION => 'ClaudeAgents\\Services\\Session\\SessionService',
            self::TRANSACTION => 'ClaudeAgents\\Services\\Transaction\\TransactionService',
            self::FLOW_EXECUTOR => 'ClaudeAgents\\Execution\\StreamingFlowExecutor',
            self::EVENT_MANAGER => 'ClaudeAgents\\Events\\FlowEventManager',
        };
    }

    /**
     * Get the factory class name for this service type.
     *
     * @return string Expected factory class name
     */
    public function getFactoryClassName(): string
    {
        return match ($this) {
            self::SETTINGS => 'ClaudeAgents\\Services\\Settings\\SettingsServiceFactory',
            self::CACHE => 'ClaudeAgents\\Services\\Cache\\CacheServiceFactory',
            self::STORAGE => 'ClaudeAgents\\Services\\Storage\\StorageServiceFactory',
            self::VARIABLE => 'ClaudeAgents\\Services\\Variable\\VariableServiceFactory',
            self::TRACING => 'ClaudeAgents\\Services\\Tracing\\TracingServiceFactory',
            self::TELEMETRY => 'ClaudeAgents\\Services\\Telemetry\\TelemetryServiceFactory',
            self::SESSION => 'ClaudeAgents\\Services\\Session\\SessionServiceFactory',
            self::TRANSACTION => 'ClaudeAgents\\Services\\Transaction\\TransactionServiceFactory',
            self::FLOW_EXECUTOR => 'ClaudeAgents\\Services\\Execution\\StreamingFlowExecutorServiceFactory',
            self::EVENT_MANAGER => 'ClaudeAgents\\Services\\Execution\\FlowEventManagerServiceFactory',
        };
    }
}
