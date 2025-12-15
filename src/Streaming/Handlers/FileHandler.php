<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming\Handlers;

use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Streaming\StreamEvent;

/**
 * Stream handler that writes to a file.
 */
class FileHandler implements StreamHandlerInterface
{
    private mixed $fileHandle;
    private bool $includeTimestamps;
    private bool $includeEventTypes;

    /**
     * @param string $filePath Path to the output file
     * @param bool $append Whether to append to existing file
     * @param bool $includeTimestamps Whether to include timestamps in output
     * @param bool $includeEventTypes Whether to include event types in output
     * @throws \RuntimeException If file cannot be opened
     */
    public function __construct(
        string $filePath,
        bool $append = false,
        bool $includeTimestamps = false,
        bool $includeEventTypes = false,
    ) {
        $mode = $append ? 'a' : 'w';
        $this->fileHandle = fopen($filePath, $mode);

        if ($this->fileHandle === false) {
            throw new \RuntimeException("Failed to open file: {$filePath}");
        }

        $this->includeTimestamps = $includeTimestamps;
        $this->includeEventTypes = $includeEventTypes;
    }

    public function handle(StreamEvent $event): void
    {
        $output = '';

        if ($this->includeTimestamps) {
            $output .= '[' . date('Y-m-d H:i:s', $event->getTimestamp()) . '] ';
        }

        if ($this->includeEventTypes) {
            $output .= '[' . $event->getType() . '] ';
        }

        if ($event->isText()) {
            $output .= $event->getText();
        } elseif ($event->isError()) {
            $output .= 'ERROR: ' . $event->getText() . "\n";
            $output .= json_encode($event->getData(), JSON_PRETTY_PRINT) . "\n";
        } elseif ($event->isToolUse()) {
            $output .= 'TOOL_USE: ' . json_encode($event->getData()) . "\n";
        } elseif ($event->isMetadata()) {
            $output .= 'METADATA: ' . json_encode($event->getData()) . "\n";
        }

        if (! empty($output)) {
            fwrite($this->fileHandle, $output);
            fflush($this->fileHandle);
        }
    }

    public function getName(): string
    {
        return 'file';
    }

    /**
     * Close the file handle.
     */
    public function __destruct()
    {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }
}
