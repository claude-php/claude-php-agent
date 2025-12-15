<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Safe JSON encoding/decoding utilities with error handling.
 */
class JsonHelper
{
    /**
     * Safely encode data to JSON with error handling.
     *
     * @param mixed $data Data to encode
     * @param int $flags JSON encode flags
     * @param int $depth Maximum depth
     * @throws \JsonException If encoding fails
     * @return string JSON string
     */
    public static function encode(mixed $data, int $flags = 0, int $depth = 512): string
    {
        try {
            return json_encode($data, $flags | JSON_THROW_ON_ERROR, $depth);
        } catch (\JsonException $e) {
            throw new \JsonException("JSON encoding failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Safely decode JSON string with error handling.
     *
     * @param string $json JSON string
     * @param bool $assoc Return as associative array
     * @param int $depth Maximum depth
     * @param int $flags JSON decode flags
     * @throws \JsonException If decoding fails
     * @return mixed Decoded data
     */
    public static function decode(string $json, bool $assoc = true, int $depth = 512, int $flags = 0): mixed
    {
        try {
            return json_decode($json, $assoc, $depth, $flags | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \JsonException("JSON decoding failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Pretty-print JSON with formatting.
     *
     * @param mixed $data Data to encode
     * @throws \JsonException If encoding fails
     * @return string Formatted JSON string
     */
    public static function prettyPrint(mixed $data): string
    {
        return self::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param string $json String to validate
     * @return bool True if valid JSON
     */
    public static function isValid(string $json): bool
    {
        if (empty($json)) {
            return false;
        }

        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Safely encode with fallback value on error.
     *
     * @param mixed $data Data to encode
     * @param string $fallback Fallback value if encoding fails
     * @return string JSON string or fallback
     */
    public static function encodeOrFallback(mixed $data, string $fallback = '{}'): string
    {
        try {
            return self::encode($data);
        } catch (\JsonException) {
            return $fallback;
        }
    }

    /**
     * Safely decode with fallback value on error.
     *
     * @param string $json JSON string
     * @param mixed $fallback Fallback value if decoding fails
     * @return mixed Decoded data or fallback
     */
    public static function decodeOrFallback(string $json, mixed $fallback = []): mixed
    {
        try {
            return self::decode($json);
        } catch (\JsonException) {
            return $fallback;
        }
    }

    /**
     * Validate JSON against expected structure.
     *
     * @param string $json JSON string
     * @param callable $validator Validation callback
     * @return bool True if valid
     */
    public static function validate(string $json, callable $validator): bool
    {
        try {
            $data = self::decode($json);

            return $validator($data);
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Minify JSON string (remove whitespace).
     *
     * @param string $json JSON string
     * @throws \JsonException If JSON is invalid
     * @return string Minified JSON
     */
    public static function minify(string $json): string
    {
        return self::encode(self::decode($json));
    }

    /**
     * Convert JSON to array, ensuring result is always an array.
     *
     * @param string $json JSON string
     * @return array<mixed> Array representation
     */
    public static function toArray(string $json): array
    {
        $result = self::decodeOrFallback($json, []);

        return is_array($result) ? $result : [$result];
    }

    /**
     * Merge multiple JSON strings into a single array.
     *
     * @param string ...$jsons JSON strings to merge
     * @throws \JsonException If any JSON is invalid
     * @return string Merged JSON array
     */
    public static function mergeArrays(string ...$jsons): string
    {
        $merged = [];
        foreach ($jsons as $json) {
            $data = self::decode($json);
            if (is_array($data)) {
                $merged = array_merge($merged, $data);
            }
        }

        return self::encode($merged);
    }

    /**
     * Extract a value from JSON using dot notation.
     *
     * @param string $json JSON string
     * @param string $path Dot-notation path (e.g., 'user.name')
     * @param mixed $default Default value if path not found
     * @return mixed Value at path or default
     */
    public static function get(string $json, string $path, mixed $default = null): mixed
    {
        try {
            $data = self::decode($json);

            return ArrayHelper::dotGet($data, $path, $default);
        } catch (\JsonException) {
            return $default;
        }
    }
}
