<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\Handlers\ConsoleHandler;
use ClaudeAgents\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;

class ConsoleHandlerTest extends TestCase
{
    public function testHandleTextEvent(): void
    {
        $handler = new ConsoleHandler();
        $event = StreamEvent::text('Hello World');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals('Hello World', $output);
    }

    public function testHandleWithPrefix(): void
    {
        $handler = new ConsoleHandler(prefix: '>> ');
        $event = StreamEvent::text('Test');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals('>> Test', $output);
    }

    public function testHandleWithNewlineOnPeriod(): void
    {
        $handler = new ConsoleHandler(newline: true);
        $event = StreamEvent::text('Sentence.');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals("Sentence.\n", $output);
    }

    public function testHandleWithoutNewlineWhenNoPeriod(): void
    {
        $handler = new ConsoleHandler(newline: true);
        $event = StreamEvent::text('No period');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals('No period', $output);
    }

    public function testIgnoresNonTextEvents(): void
    {
        $handler = new ConsoleHandler();
        $event = StreamEvent::toolUse(['name' => 'test']);

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals('', $output);
    }

    public function testHandleDeltaEvent(): void
    {
        $handler = new ConsoleHandler();
        $event = StreamEvent::delta('Delta text');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals('Delta text', $output);
    }

    public function testGetName(): void
    {
        $handler = new ConsoleHandler();

        $this->assertEquals('console', $handler->getName());
    }

    public function testHandleMultipleEvents(): void
    {
        $handler = new ConsoleHandler();

        ob_start();
        $handler->handle(StreamEvent::text('Hello '));
        $handler->handle(StreamEvent::text('World'));
        $output = ob_get_clean();

        $this->assertEquals('Hello World', $output);
    }

    public function testHandleWithPrefixAndNewline(): void
    {
        $handler = new ConsoleHandler(newline: true, prefix: '[AI] ');
        $event = StreamEvent::text('Done.');

        ob_start();
        $handler->handle($event);
        $output = ob_get_clean();

        $this->assertEquals("[AI] Done.\n", $output);
    }
}
