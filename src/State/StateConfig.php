<?php

declare(strict_types=1);

namespace ClaudeAgents\State;

/**
 * Configuration for state management.
 */
class StateConfig
{
    /**
     * @param int $maxConversationHistory Maximum conversation history items (0 = unlimited)
     * @param int $maxActionHistory Maximum action history items (0 = unlimited)
     * @param bool $compressHistory Whether to compress large histories
     * @param bool $atomicWrites Use atomic writes for state persistence
     * @param int $backupRetention Number of backup files to retain
     * @param int $version State schema version
     */
    public function __construct(
        public readonly int $maxConversationHistory = 1000,
        public readonly int $maxActionHistory = 1000,
        public readonly bool $compressHistory = false,
        public readonly bool $atomicWrites = true,
        public readonly int $backupRetention = 5,
        public readonly int $version = 1,
    ) {
        if ($maxConversationHistory < 0) {
            throw new \InvalidArgumentException('maxConversationHistory must be non-negative');
        }
        if ($maxActionHistory < 0) {
            throw new \InvalidArgumentException('maxActionHistory must be non-negative');
        }
        if ($backupRetention < 0) {
            throw new \InvalidArgumentException('backupRetention must be non-negative');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('version must be at least 1');
        }
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create configuration for unlimited history.
     */
    public static function unlimited(): self
    {
        return new self(
            maxConversationHistory: 0,
            maxActionHistory: 0,
        );
    }

    /**
     * Create configuration for production use.
     */
    public static function production(): self
    {
        return new self(
            maxConversationHistory: 500,
            maxActionHistory: 500,
            compressHistory: true,
            atomicWrites: true,
            backupRetention: 10,
        );
    }
}
