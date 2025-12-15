<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Utility for formatting token usage metadata consistently across agents.
 */
class TokenUsageFormatter
{
    /**
     * Format token usage from a response object.
     *
     * @param object|array<mixed> $response Response object or array with usage property
     * @return array{input: int, output: int, total: int}
     */
    public static function format(object|array $response): array
    {
        if (is_array($response)) {
            $usage = $response['usage'] ?? null;
        } else {
            $usage = $response->usage ?? null;
        }

        $inputTokens = 0;
        $outputTokens = 0;

        if (is_array($usage)) {
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;
        } elseif (is_object($usage)) {
            $inputTokens = $usage->input_tokens ?? 0;
            $outputTokens = $usage->output_tokens ?? 0;
        }

        return [
            'input' => $inputTokens,
            'output' => $outputTokens,
            'total' => $inputTokens + $outputTokens,
        ];
    }

    /**
     * Create a simplified token usage array with just input and output.
     *
     * @param object|array<mixed> $response Response object or array with usage property
     * @return array{input: int, output: int}
     */
    public static function formatSimple(object|array $response): array
    {
        $usage = self::format($response);

        return [
            'input' => $usage['input'],
            'output' => $usage['output'],
        ];
    }
}
