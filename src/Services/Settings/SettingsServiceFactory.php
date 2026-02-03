<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Settings;

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceType;

/**
 * Factory for creating SettingsService instances.
 */
class SettingsServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::SETTINGS;
    protected string $serviceClass = SettingsService::class;

    /**
     * @param string|null $configFile Path to configuration file
     * @param array<string, mixed> $overrides Manual configuration overrides
     */
    public function __construct(
        private ?string $configFile = null,
        private array $overrides = []
    ) {
    }

    public function create(array $dependencies = []): ServiceInterface
    {
        return new SettingsService($this->configFile, $this->overrides);
    }
}
