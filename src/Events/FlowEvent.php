<?php

declare(strict_types=1);

namespace ClaudeAgents\Events;

/**
 * Flow execution event for streaming and progress tracking.
 *
 * Represents a single event in the flow execution lifecycle,
 * from flow start to token streaming to completion.
 *
 * Inspired by Langflow's event system with PHP adaptations.
 *
 * @example
 * ```php
 * // Create a token event
 * $event = FlowEvent::token('Hello', ['iteration' => 1]);
 *
 * // Create a flow started event
 * $event = FlowEvent::flowStarted(['input' => 'Task description']);
 *
 * // Access event data
 * echo $event->type; // 'token.received'
 * echo $event->data['token']; // 'Hello'
 * ```
 */
class FlowEvent
{
    // Flow lifecycle events
    public const FLOW_STARTED = 'flow.started';
    public const FLOW_COMPLETED = 'flow.completed';
    public const FLOW_FAILED = 'flow.failed';
    public const FLOW_PAUSED = 'flow.paused';
    public const FLOW_RESUMED = 'flow.resumed';

    // Token streaming events
    public const TOKEN_RECEIVED = 'token.received';
    public const TOKEN_CHUNK = 'token.chunk';

    // Iteration events
    public const ITERATION_STARTED = 'iteration.started';
    public const ITERATION_COMPLETED = 'iteration.completed';
    public const ITERATION_FAILED = 'iteration.failed';

    // Tool execution events
    public const TOOL_EXECUTION_STARTED = 'tool.started';
    public const TOOL_EXECUTION_COMPLETED = 'tool.completed';
    public const TOOL_EXECUTION_FAILED = 'tool.failed';

    // Progress events
    public const PROGRESS_UPDATE = 'progress.update';
    public const STEP_STARTED = 'step.started';
    public const STEP_COMPLETED = 'step.completed';

    // Message events (compatible with Langflow)
    public const MESSAGE_ADDED = 'add_message';
    public const MESSAGE_REMOVED = 'remove_message';

    // Vertex/node events (Langflow compatibility)
    public const VERTEX_STARTED = 'vertex.started';
    public const VERTEX_COMPLETED = 'end_vertex';
    public const VERTICES_SORTED = 'vertices_sorted';

    // Build events (Langflow compatibility)
    public const BUILD_STARTED = 'build_start';
    public const BUILD_COMPLETED = 'build_end';

    // Error and warning events
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const INFO = 'info';

    /**
     * @param string $type Event type constant
     * @param array<string, mixed> $data Event payload data
     * @param float $timestamp Unix timestamp with microseconds
     * @param string|null $id Optional event identifier
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly float $timestamp,
        public readonly ?string $id = null
    ) {
    }

    /**
     * Create a flow started event.
     *
     * @param array<string, mixed> $data
     */
    public static function flowStarted(array $data = []): self
    {
        return new self(self::FLOW_STARTED, $data, microtime(true));
    }

    /**
     * Create a flow completed event.
     *
     * @param array<string, mixed> $data
     */
    public static function flowCompleted(array $data = []): self
    {
        return new self(self::FLOW_COMPLETED, $data, microtime(true));
    }

    /**
     * Create a flow failed event.
     *
     * @param string $error Error message
     * @param array<string, mixed> $data Additional error data
     */
    public static function flowFailed(string $error, array $data = []): self
    {
        return new self(self::FLOW_FAILED, array_merge(['error' => $error], $data), microtime(true));
    }

    /**
     * Create a token received event.
     *
     * @param string $token Token text
     * @param array<string, mixed> $data Additional data
     */
    public static function token(string $token, array $data = []): self
    {
        return new self(self::TOKEN_RECEIVED, array_merge(['token' => $token], $data), microtime(true));
    }

    /**
     * Create an iteration started event.
     *
     * @param int $iteration Iteration number
     * @param array<string, mixed> $data Additional data
     */
    public static function iterationStarted(int $iteration, array $data = []): self
    {
        return new self(self::ITERATION_STARTED, array_merge(['iteration' => $iteration], $data), microtime(true));
    }

    /**
     * Create an iteration completed event.
     *
     * @param int $iteration Iteration number
     * @param array<string, mixed> $data Additional data
     */
    public static function iterationCompleted(int $iteration, array $data = []): self
    {
        return new self(self::ITERATION_COMPLETED, array_merge(['iteration' => $iteration], $data), microtime(true));
    }

    /**
     * Create a tool execution started event.
     *
     * @param string $toolName Tool name
     * @param array<string, mixed> $input Tool input
     * @param array<string, mixed> $data Additional data
     */
    public static function toolStarted(string $toolName, array $input = [], array $data = []): self
    {
        return new self(
            self::TOOL_EXECUTION_STARTED,
            array_merge(['tool' => $toolName, 'input' => $input], $data),
            microtime(true)
        );
    }

    /**
     * Create a tool execution completed event.
     *
     * @param string $toolName Tool name
     * @param mixed $result Tool result
     * @param array<string, mixed> $data Additional data
     */
    public static function toolCompleted(string $toolName, mixed $result, array $data = []): self
    {
        return new self(
            self::TOOL_EXECUTION_COMPLETED,
            array_merge(['tool' => $toolName, 'result' => $result], $data),
            microtime(true)
        );
    }

    /**
     * Create a progress update event.
     *
     * @param float $percent Progress percentage (0-100)
     * @param array<string, mixed> $data Additional data
     */
    public static function progress(float $percent, array $data = []): self
    {
        return new self(self::PROGRESS_UPDATE, array_merge(['percent' => $percent], $data), microtime(true));
    }

    /**
     * Create an error event.
     *
     * @param string $message Error message
     * @param array<string, mixed> $data Additional data
     */
    public static function error(string $message, array $data = []): self
    {
        return new self(self::ERROR, array_merge(['message' => $message], $data), microtime(true));
    }

    /**
     * Check if this is a token event.
     */
    public function isToken(): bool
    {
        return $this->type === self::TOKEN_RECEIVED || $this->type === self::TOKEN_CHUNK;
    }

    /**
     * Check if this is a flow lifecycle event.
     */
    public function isFlowEvent(): bool
    {
        return in_array($this->type, [
            self::FLOW_STARTED,
            self::FLOW_COMPLETED,
            self::FLOW_FAILED,
            self::FLOW_PAUSED,
            self::FLOW_RESUMED,
        ]);
    }

    /**
     * Check if this is an error event.
     */
    public function isError(): bool
    {
        return $this->type === self::ERROR || $this->type === self::FLOW_FAILED;
    }

    /**
     * Check if this is a progress event.
     */
    public function isProgress(): bool
    {
        return $this->type === self::PROGRESS_UPDATE;
    }

    /**
     * Check if this is a tool event.
     */
    public function isToolEvent(): bool
    {
        return in_array($this->type, [
            self::TOOL_EXECUTION_STARTED,
            self::TOOL_EXECUTION_COMPLETED,
            self::TOOL_EXECUTION_FAILED,
        ]);
    }

    /**
     * Get event duration from another event.
     */
    public function getDurationFrom(FlowEvent $startEvent): float
    {
        return $this->timestamp - $startEvent->timestamp;
    }

    /**
     * Convert event to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'id' => $this->id,
        ];
    }

    /**
     * Convert event to JSON format for SSE.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Convert event to SSE format.
     */
    public function toSSE(): string
    {
        $output = "event: {$this->type}\n";
        $output .= 'data: ' . $this->toJson() . "\n\n";
        return $output;
    }
}
