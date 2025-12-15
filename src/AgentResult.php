<?php

declare(strict_types=1);

namespace ClaudeAgents;

/**
 * Represents the result of an agent execution.
 */
class AgentResult implements \JsonSerializable
{
    /**
     * @param bool $success Whether the agent completed successfully
     * @param string $answer The final answer/output from the agent
     * @param array<array<string, mixed>> $messages The conversation history
     * @param int $iterations Number of iterations taken
     * @param array<string, mixed> $metadata Additional metadata about the execution
     * @param string|null $error Error message if failed
     */
    public function __construct(
        private readonly bool $success,
        private readonly string $answer,
        private readonly array $messages,
        private readonly int $iterations,
        private readonly array $metadata = [],
        private readonly ?string $error = null,
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param string $answer The final answer
     * @param array<array<string, mixed>> $messages Conversation history
     * @param int $iterations Number of iterations
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function success(
        string $answer,
        array $messages,
        int $iterations,
        array $metadata = [],
    ): self {
        if (trim($answer) === '') {
            throw new \InvalidArgumentException('Answer cannot be empty for success result');
        }

        return new self(
            success: true,
            answer: $answer,
            messages: $messages,
            iterations: $iterations,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * @param string $error Error message
     * @param array<array<string, mixed>> $messages Conversation history
     * @param int $iterations Number of iterations
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function failure(
        string $error,
        array $messages = [],
        int $iterations = 0,
        array $metadata = [],
    ): self {
        if (trim($error) === '') {
            throw new \InvalidArgumentException('Error message cannot be empty for failure result');
        }

        return new self(
            success: false,
            answer: '',
            messages: $messages,
            iterations: $iterations,
            metadata: $metadata,
            error: $error,
        );
    }

    /**
     * Check if the execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the final answer.
     */
    public function getAnswer(): string
    {
        return $this->answer;
    }

    /**
     * Get the conversation history.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the number of iterations.
     */
    public function getIterations(): int
    {
        return $this->iterations;
    }

    /**
     * Get execution metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the error message if failed.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get total token usage from metadata.
     *
     * @return array{input: int, output: int, total: int}
     */
    public function getTokenUsage(): array
    {
        return $this->metadata['token_usage'] ?? [
            'input' => 0,
            'output' => 0,
            'total' => 0,
        ];
    }

    /**
     * Get tool calls made during execution.
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return $this->metadata['tool_calls'] ?? [];
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'answer' => $this->answer,
            'iterations' => $this->iterations,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags) ?: '{}';
    }

    /**
     * JsonSerializable implementation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create result from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $success = $data['success'] ?? false;
        $error = $data['error'] ?? null;

        if ($success) {
            return self::success(
                answer: $data['answer'] ?? '',
                messages: $data['messages'] ?? [],
                iterations: $data['iterations'] ?? 0,
                metadata: $data['metadata'] ?? [],
            );
        }

        return self::failure(
            error: $error ?? 'Unknown error',
            messages: $data['messages'] ?? [],
            iterations: $data['iterations'] ?? 0,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Create result from JSON string.
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON for AgentResult');
        }

        return self::fromArray($data);
    }

    /**
     * Get metadata value with optional default.
     *
     * @param mixed $default
     * @return mixed
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if metadata key exists.
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Create a new result with additional metadata.
     *
     * @param mixed $value
     */
    public function withMetadata(string $key, $value): self
    {
        return new self(
            success: $this->success,
            answer: $this->answer,
            messages: $this->messages,
            iterations: $this->iterations,
            metadata: array_merge($this->metadata, [$key => $value]),
            error: $this->error,
        );
    }

    /**
     * Check if this result is partial/streaming.
     */
    public function isPartial(): bool
    {
        return $this->getMetadataValue('is_partial', false);
    }

    /**
     * Compare results for quality (higher score is better).
     *
     * @param AgentResult $other
     * @return int Returns 1 if this is better, -1 if other is better, 0 if equal
     */
    public function compareTo(AgentResult $other): int
    {
        // Success beats failure
        if ($this->success && ! $other->success) {
            return 1;
        }
        if (! $this->success && $other->success) {
            return -1;
        }

        // Both success or both failure - compare by iterations (fewer is better)
        if ($this->success) {
            if ($this->iterations < $other->iterations) {
                return 1;
            }
            if ($this->iterations > $other->iterations) {
                return -1;
            }
        }

        return 0;
    }

    /**
     * Check if this result is better than another.
     */
    public function isBetterThan(AgentResult $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Get quality score (0.0 to 1.0).
     */
    public function getQualityScore(): float
    {
        if (! $this->success) {
            return 0.0;
        }

        // Base score for success
        $score = 0.5;

        // Bonus for efficiency (fewer iterations)
        if ($this->iterations > 0) {
            $score += min(0.3, 0.3 / $this->iterations);
        }

        // Bonus for having an answer
        if (! empty($this->answer)) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * String representation of the result.
     */
    public function __toString(): string
    {
        if ($this->success) {
            $answerPreview = strlen($this->answer) > 50
                ? substr($this->answer, 0, 50) . '...'
                : $this->answer;

            return "Success [{$this->iterations} iterations]: {$answerPreview}";
        }

        return "Failed [{$this->iterations} iterations]: {$this->error}";
    }
}
