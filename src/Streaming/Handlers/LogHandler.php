<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming\Handlers;

use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Streaming\StreamEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Stream handler that logs to a PSR-3 logger.
 */
class LogHandler implements StreamHandlerInterface
{
    private LoggerInterface $logger;
    private string $textLogLevel;
    private string $errorLogLevel;
    private string $toolUseLogLevel;
    private bool $logEachChunk;

    /**
     * @param LoggerInterface $logger PSR-3 logger instance
     * @param string $textLogLevel Log level for text events (default: debug)
     * @param string $errorLogLevel Log level for error events (default: error)
     * @param string $toolUseLogLevel Log level for tool use events (default: info)
     * @param bool $logEachChunk Whether to log each text chunk (default: false)
     */
    public function __construct(
        LoggerInterface $logger,
        string $textLogLevel = LogLevel::DEBUG,
        string $errorLogLevel = LogLevel::ERROR,
        string $toolUseLogLevel = LogLevel::INFO,
        bool $logEachChunk = false,
    ) {
        $this->logger = $logger;
        $this->textLogLevel = $textLogLevel;
        $this->errorLogLevel = $errorLogLevel;
        $this->toolUseLogLevel = $toolUseLogLevel;
        $this->logEachChunk = $logEachChunk;
    }

    public function handle(StreamEvent $event): void
    {
        if ($event->isText()) {
            if ($this->logEachChunk) {
                $this->logger->log(
                    $this->textLogLevel,
                    'Stream text chunk received',
                    [
                        'text' => $event->getText(),
                        'length' => strlen($event->getText()),
                        'type' => $event->getType(),
                    ]
                );
            }
        } elseif ($event->isError()) {
            $this->logger->log(
                $this->errorLogLevel,
                'Stream error: ' . $event->getText(),
                [
                    'error_data' => $event->getData(),
                    'timestamp' => $event->getTimestamp(),
                ]
            );
        } elseif ($event->isToolUse()) {
            $toolData = $event->getToolUse();
            $this->logger->log(
                $this->toolUseLogLevel,
                'Tool use in stream',
                [
                    'tool_name' => $toolData['name'] ?? 'unknown',
                    'tool_data' => $toolData,
                ]
            );
        } elseif ($event->isMetadata()) {
            $this->logger->log(
                LogLevel::INFO,
                'Stream metadata received',
                ['metadata' => $event->getData()]
            );
        } elseif ($event->isPing()) {
            $this->logger->log(
                LogLevel::DEBUG,
                'Stream ping/heartbeat received',
                ['timestamp' => $event->getTimestamp()]
            );
        }
    }

    public function getName(): string
    {
        return 'log';
    }
}
