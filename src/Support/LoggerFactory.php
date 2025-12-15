<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Factory for creating standardized loggers.
 */
class LoggerFactory
{
    /**
     * Create a logger instance.
     * Returns provided logger or creates a NullLogger.
     *
     * @param LoggerInterface|null $logger Custom logger instance
     * @return LoggerInterface Logger instance
     */
    public static function create(?LoggerInterface $logger = null): LoggerInterface
    {
        if ($logger !== null) {
            return $logger;
        }

        // Try to use PSR-3 NullLogger if available
        if (class_exists(NullLogger::class)) {
            return new NullLogger();
        }

        // Fallback to simple null implementation
        return self::createNull();
    }

    /**
     * Create a simple null logger (does nothing).
     *
     * @return LoggerInterface Null logger
     */
    public static function createNull(): LoggerInterface
    {
        return new class () implements LoggerInterface {
            public function emergency(string|\Stringable $message, array $context = []): void
            {
            }

            public function alert(string|\Stringable $message, array $context = []): void
            {
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
            }

            public function error(string|\Stringable $message, array $context = []): void
            {
            }

            public function warning(string|\Stringable $message, array $context = []): void
            {
            }

            public function notice(string|\Stringable $message, array $context = []): void
            {
            }

            public function info(string|\Stringable $message, array $context = []): void
            {
            }

            public function debug(string|\Stringable $message, array $context = []): void
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
        };
    }

    /**
     * Create a simple console logger.
     *
     * @param string $minLevel Minimum log level
     * @return LoggerInterface Console logger
     */
    public static function createConsole(string $minLevel = LogLevel::INFO): LoggerInterface
    {
        return new ConsoleLogger($minLevel);
    }

    /**
     * Create a file logger.
     *
     * @param string $filepath Log file path
     * @param string $minLevel Minimum log level
     * @return LoggerInterface File logger
     */
    public static function createFile(string $filepath, string $minLevel = LogLevel::INFO): LoggerInterface
    {
        return new FileLogger($filepath, $minLevel);
    }

    /**
     * Create a memory logger (useful for testing).
     *
     * @return MemoryLogger Memory logger
     */
    public static function createMemory(): MemoryLogger
    {
        return new MemoryLogger();
    }
}

/**
 * Simple console logger implementation.
 */
class ConsoleLogger implements LoggerInterface
{
    private const LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private int $minLevelValue;

    public function __construct(string $minLevel = LogLevel::INFO)
    {
        $this->minLevelValue = self::LEVELS[$minLevel] ?? self::LEVELS[LogLevel::INFO];
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelValue = self::LEVELS[$level] ?? 999;

        if ($levelValue > $this->minLevelValue) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelStr = strtoupper($level);
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);

        echo "[{$timestamp}] {$levelStr}: {$message}{$contextStr}\n";
    }
}

/**
 * Simple file logger implementation.
 */
class FileLogger implements LoggerInterface
{
    private const LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private int $minLevelValue;

    public function __construct(
        private readonly string $filepath,
        string $minLevel = LogLevel::INFO,
    ) {
        $this->minLevelValue = self::LEVELS[$minLevel] ?? self::LEVELS[LogLevel::INFO];

        // Ensure directory exists
        $dir = dirname($filepath);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelValue = self::LEVELS[$level] ?? 999;

        if ($levelValue > $this->minLevelValue) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelStr = strtoupper($level);
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);

        $logLine = "[{$timestamp}] {$levelStr}: {$message}{$contextStr}\n";

        file_put_contents($this->filepath, $logLine, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Memory logger for testing.
 */
class MemoryLogger implements LoggerInterface
{
    /**
     * @var array<array{level: string, message: string, context: array<mixed>}>
     */
    private array $logs = [];

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Get all logged messages.
     *
     * @return array<array{level: string, message: string, context: array<mixed>}>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Clear all logs.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Get logs of a specific level.
     *
     * @param string $level Log level
     * @return array<array{level: string, message: string, context: array<mixed>}>
     */
    public function getLogsOfLevel(string $level): array
    {
        return array_filter($this->logs, fn ($log) => $log['level'] === $level);
    }

    /**
     * Check if any logs contain a message.
     *
     * @param string $needle Substring to search for
     * @return bool True if found
     */
    public function hasMessage(string $needle): bool
    {
        foreach ($this->logs as $log) {
            if (str_contains($log['message'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
