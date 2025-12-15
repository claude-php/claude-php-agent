<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\Handlers\LogHandler;
use ClaudeAgents\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LogHandlerTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testHandleTextEventWithoutLoggingChunks(): void
    {
        $this->logger->expects($this->never())
            ->method('log');

        $handler = new LogHandler($this->logger, logEachChunk: false);
        $handler->handle(StreamEvent::text('Test text'));
    }

    public function testHandleTextEventWithLoggingChunks(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                'Stream text chunk received',
                $this->callback(function ($context) {
                    return $context['text'] === 'Test text'
                        && $context['length'] === 9
                        && $context['type'] === StreamEvent::TYPE_TEXT;
                })
            );

        $handler = new LogHandler($this->logger, logEachChunk: true);
        $handler->handle(StreamEvent::text('Test text'));
    }

    public function testHandleErrorEvent(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                'Stream error: Test error',
                $this->callback(function ($context) {
                    return isset($context['error_data'])
                        && $context['error_data']['code'] === 500;
                })
            );

        $handler = new LogHandler($this->logger);
        $handler->handle(StreamEvent::error('Test error', ['code' => 500]));
    }

    public function testHandleToolUseEvent(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                'Tool use in stream',
                $this->callback(function ($context) {
                    return $context['tool_name'] === 'calculator'
                        && isset($context['tool_data']);
                })
            );

        $handler = new LogHandler($this->logger);
        $handler->handle(StreamEvent::toolUse(['name' => 'calculator', 'input' => ['a' => 5]]));
    }

    public function testHandleMetadataEvent(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                'Stream metadata received',
                $this->callback(function ($context) {
                    return $context['metadata']['version'] === '1.0';
                })
            );

        $handler = new LogHandler($this->logger);
        $handler->handle(StreamEvent::metadata(['version' => '1.0']));
    }

    public function testHandlePingEvent(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                'Stream ping/heartbeat received',
                $this->callback(function ($context) {
                    return isset($context['timestamp']);
                })
            );

        $handler = new LogHandler($this->logger);
        $handler->handle(StreamEvent::ping());
    }

    public function testCustomLogLevels(): void
    {
        $this->logger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                $this->anything(),
                $this->anything()
            );

        $handler = new LogHandler(
            $this->logger,
            textLogLevel: LogLevel::WARNING,
            logEachChunk: true
        );
        $handler->handle(StreamEvent::text('Test'));
    }

    public function testGetName(): void
    {
        $handler = new LogHandler($this->logger);

        $this->assertEquals('log', $handler->getName());
    }
}
