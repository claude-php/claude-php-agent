<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Settings;

use ClaudeAgents\Services\ServiceInterface;

/**
 * Application-wide settings management service.
 *
 * Loads configuration from files with environment variable overrides.
 */
class SettingsService implements ServiceInterface
{
    private bool $ready = false;

    /**
     * @var array<string, mixed> Configuration settings
     */
    private array $settings = [];

    /**
     * @var array<string, mixed> Default settings
     */
    private array $defaults = [
        'storage.directory' => './storage',
        'cache.driver' => 'array',
        'cache.ttl' => 3600,
        'tracing.enabled' => false,
        'tracing.providers' => [],
        'telemetry.enabled' => false,
        'session.driver' => 'file',
        'session.lifetime' => 7200,
        'variable.encryption_key' => null,
    ];

    /**
     * @param string|null $configFile Path to configuration file (JSON or PHP array)
     * @param array<string, mixed> $overrides Manual configuration overrides
     */
    public function __construct(
        private ?string $configFile = null,
        array $overrides = []
    ) {
        $this->settings = array_merge($this->defaults, $overrides);
    }

    public function getName(): string
    {
        return 'settings';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // Load from configuration file if provided
        if ($this->configFile !== null && file_exists($this->configFile)) {
            $this->loadFromFile($this->configFile);
        }

        // Apply environment variable overrides
        $this->loadFromEnvironment();

        $this->ready = true;
    }

    public function teardown(): void
    {
        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'get' => [
                    'parameters' => ['key' => 'string', 'default' => 'mixed'],
                    'return' => 'mixed',
                    'description' => 'Get a configuration value by key',
                ],
                'set' => [
                    'parameters' => ['key' => 'string', 'value' => 'mixed'],
                    'return' => 'void',
                    'description' => 'Set a configuration value',
                ],
                'has' => [
                    'parameters' => ['key' => 'string'],
                    'return' => 'bool',
                    'description' => 'Check if a configuration key exists',
                ],
                'all' => [
                    'parameters' => [],
                    'return' => 'array',
                    'description' => 'Get all configuration settings',
                ],
            ],
        ];
    }

    /**
     * Get a configuration value by key.
     *
     * Supports dot notation for nested values (e.g., 'cache.ttl').
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Support dot notation
        if (str_contains($key, '.')) {
            $value = $this->settings;
            foreach (explode('.', $key) as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }

            return $value;
        }

        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a configuration value.
     *
     * Supports dot notation for nested values.
     *
     * @param string $key Configuration key
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        // Support dot notation
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $lastSegment = array_pop($segments);
            $current = &$this->settings;

            foreach ($segments as $segment) {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }

            $current[$lastSegment] = $value;
        } else {
            $this->settings[$key] = $value;
        }
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key Configuration key
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        if (str_contains($key, '.')) {
            $value = $this->settings;
            foreach (explode('.', $key) as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return false;
                }
                $value = $value[$segment];
            }

            return true;
        }

        return array_key_exists($key, $this->settings);
    }

    /**
     * Get all configuration settings.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->settings;
    }

    /**
     * Reload configuration from file and environment.
     *
     * @return void
     */
    public function reload(): void
    {
        $this->settings = $this->defaults;

        if ($this->configFile !== null && file_exists($this->configFile)) {
            $this->loadFromFile($this->configFile);
        }

        $this->loadFromEnvironment();
    }

    /**
     * Load configuration from a file.
     *
     * Supports JSON and PHP array files.
     *
     * @param string $file File path
     * @return void
     * @throws \RuntimeException If file cannot be loaded
     */
    private function loadFromFile(string $file): void
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === 'json') {
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \RuntimeException("Failed to read config file: {$file}");
            }

            $config = json_decode($contents, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in config file: {$file}");
            }
        } elseif ($extension === 'php') {
            $config = require $file;
            if (! is_array($config)) {
                throw new \RuntimeException("Config file must return an array: {$file}");
            }
        } else {
            throw new \RuntimeException("Unsupported config file format: {$extension}");
        }

        $this->settings = array_merge($this->settings, $config);
    }

    /**
     * Load configuration from environment variables.
     *
     * Environment variables should be prefixed with CLAUDE_AGENT_
     * and use underscores (e.g., CLAUDE_AGENT_CACHE_DRIVER).
     *
     * @return void
     */
    private function loadFromEnvironment(): void
    {
        $prefix = 'CLAUDE_AGENT_';

        foreach ($_ENV as $key => $value) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            // Convert CLAUDE_AGENT_CACHE_DRIVER to cache.driver
            $configKey = strtolower(substr($key, strlen($prefix)));
            $configKey = str_replace('_', '.', $configKey);

            // Parse value (support boolean and numeric types)
            $parsedValue = $this->parseValue($value);

            $this->set($configKey, $parsedValue);
        }
    }

    /**
     * Parse environment variable value.
     *
     * Converts string values to appropriate types.
     *
     * @param string $value Raw string value
     * @return mixed Parsed value
     */
    private function parseValue(string $value): mixed
    {
        // Boolean
        if (in_array(strtolower($value), ['true', 'false', 'yes', 'no', 'on', 'off'], true)) {
            return in_array(strtolower($value), ['true', 'yes', 'on'], true);
        }

        // Null
        if (strtolower($value) === 'null') {
            return null;
        }

        // Numeric
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // JSON
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // String
        return $value;
    }
}
