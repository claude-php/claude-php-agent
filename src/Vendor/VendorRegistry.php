<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

/**
 * Central registry for vendor API keys and configurations.
 *
 * Manages which vendors are available based on configured API keys
 * and optional per-vendor configuration overrides.
 */
class VendorRegistry
{
    /**
     * Environment variable names for each vendor.
     */
    private const ENV_KEYS = [
        'anthropic' => 'ANTHROPIC_API_KEY',
        'openai' => 'OPENAI_API_KEY',
        'google' => 'GEMINI_API_KEY',
    ];

    /**
     * @var array<string, string> Vendor name => API key
     */
    private array $keys = [];

    /**
     * @var array<string, VendorConfig> Vendor name => config
     */
    private array $configs = [];

    /**
     * Create a registry auto-populated from environment variables.
     *
     * Checks for ANTHROPIC_API_KEY, OPENAI_API_KEY, and GEMINI_API_KEY.
     */
    public static function fromEnvironment(): self
    {
        $registry = new self();

        foreach (self::ENV_KEYS as $vendor => $envVar) {
            $key = getenv($envVar);
            if ($key === false) {
                $key = $_ENV[$envVar] ?? null;
            }
            if (is_string($key) && $key !== '') {
                $registry->registerKey($vendor, $key);
            }
        }

        return $registry;
    }

    /**
     * Register an API key for a vendor.
     */
    public function registerKey(string $vendor, string $apiKey): void
    {
        $this->keys[$vendor] = $apiKey;
    }

    /**
     * Get the API key for a vendor.
     *
     * @throws \RuntimeException If the vendor key is not registered
     */
    public function getKey(string $vendor): string
    {
        if (! isset($this->keys[$vendor])) {
            $envVar = self::ENV_KEYS[$vendor] ?? strtoupper($vendor) . '_API_KEY';

            throw new \RuntimeException(
                "API key for vendor '{$vendor}' is not registered. "
                    . "Set the {$envVar} environment variable or call registerKey()."
            );
        }

        return $this->keys[$vendor];
    }

    /**
     * Check if a vendor has a registered API key.
     */
    public function isAvailable(string $vendor): bool
    {
        return isset($this->keys[$vendor]) && $this->keys[$vendor] !== '';
    }

    /**
     * Get all vendors that have registered API keys.
     *
     * @return string[]
     */
    public function getAvailableVendors(): array
    {
        return array_keys($this->keys);
    }

    /**
     * Set a configuration override for a vendor.
     */
    public function setConfig(string $vendor, VendorConfig $config): void
    {
        $this->configs[$vendor] = $config;
    }

    /**
     * Get the configuration for a vendor.
     */
    public function getConfig(string $vendor): ?VendorConfig
    {
        return $this->configs[$vendor] ?? null;
    }

    /**
     * Check if any non-Anthropic vendors are available.
     */
    public function hasExternalVendors(): bool
    {
        foreach ($this->keys as $vendor => $key) {
            if ($vendor !== 'anthropic' && $key !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the environment variable name for a vendor.
     */
    public static function getEnvVarName(string $vendor): string
    {
        return self::ENV_KEYS[$vendor] ?? strtoupper($vendor) . '_API_KEY';
    }
}
