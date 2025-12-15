<?php

declare(strict_types=1);

namespace ClaudeAgents\Config;

/**
 * Configuration for retry behavior.
 */
class RetryConfig
{
    public const DEFAULT_MAX_ATTEMPTS = 3;
    public const DEFAULT_DELAY_MS = 1000;
    public const DEFAULT_MULTIPLIER = 2.0;
    public const DEFAULT_MAX_DELAY_MS = 30000;

    public function __construct(
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $delayMs = self::DEFAULT_DELAY_MS,
        private readonly float $multiplier = self::DEFAULT_MULTIPLIER,
        private readonly int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
    ) {
    }

    /**
     * Create from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            maxAttempts: $config['max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS,
            delayMs: $config['delay_ms'] ?? self::DEFAULT_DELAY_MS,
            multiplier: $config['multiplier'] ?? self::DEFAULT_MULTIPLIER,
            maxDelayMs: $config['max_delay_ms'] ?? self::DEFAULT_MAX_DELAY_MS,
        );
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getDelayMs(): int
    {
        return $this->delayMs;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    public function getMaxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    /**
     * Calculate delay for a given attempt number.
     *
     * @param int $attempt The attempt number (1-based)
     * @return int Delay in milliseconds
     */
    public function getDelayForAttempt(int $attempt): int
    {
        $delay = (int) ($this->delayMs * pow($this->multiplier, $attempt - 1));

        return min($delay, $this->maxDelayMs);
    }
}
