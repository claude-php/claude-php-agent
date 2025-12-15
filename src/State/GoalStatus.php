<?php

declare(strict_types=1);

namespace ClaudeAgents\State;

/**
 * Enumeration of valid goal statuses.
 */
enum GoalStatus: string
{
    case NOT_STARTED = 'not_started';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    /**
     * Get all valid status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Check if a status value is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::values(), true);
    }

    /**
     * Get status from string, with fallback.
     */
    public static function fromString(string $status): self
    {
        return self::tryFrom($status) ?? self::NOT_STARTED;
    }
}
