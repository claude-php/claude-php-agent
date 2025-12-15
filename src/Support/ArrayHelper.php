<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Array manipulation utilities.
 */
class ArrayHelper
{
    /**
     * Flatten a multi-dimensional array to a single level.
     *
     * @param array<mixed> $array Array to flatten
     * @param int $depth Maximum depth to flatten (0 = infinite)
     * @return array<mixed> Flattened array
     */
    public static function flatten(array $array, int $depth = 0): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && ($depth === 0 || $depth > 0)) {
                $flattened = self::flatten($item, $depth > 0 ? $depth - 1 : 0);
                $result = array_merge($result, $flattened);
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Extract a column of values from array of arrays/objects.
     *
     * @param array<mixed> $array Source array
     * @param string|int $key Key to extract
     * @return array<mixed> Values
     */
    public static function pluck(array $array, string|int $key): array
    {
        return array_map(function ($item) use ($key) {
            if (is_array($item)) {
                return $item[$key] ?? null;
            }
            if (is_object($item)) {
                return $item->$key ?? null;
            }

            return null;
        }, $array);
    }

    /**
     * Group array items by a key.
     *
     * @param array<mixed> $array Array to group
     * @param string|callable $keyOrCallback Key name or callback
     * @return array<string, array<mixed>> Grouped array
     */
    public static function groupBy(array $array, string|callable $keyOrCallback): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_callable($keyOrCallback)) {
                $key = $keyOrCallback($item);
            } elseif (is_array($item)) {
                $key = $item[$keyOrCallback] ?? 'null';
            } elseif (is_object($item)) {
                $key = $item->$keyOrCallback ?? 'null';
            } else {
                $key = 'null';
            }

            $result[$key][] = $item;
        }

        return $result;
    }

    /**
     * Index array by a key value.
     *
     * @param array<mixed> $array Array to index
     * @param string|callable $keyOrCallback Key name or callback
     * @return array<string, mixed> Indexed array
     */
    public static function keyBy(array $array, string|callable $keyOrCallback): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_callable($keyOrCallback)) {
                $key = $keyOrCallback($item);
            } elseif (is_array($item)) {
                $key = $item[$keyOrCallback] ?? null;
            } elseif (is_object($item)) {
                $key = $item->$keyOrCallback ?? null;
            } else {
                continue;
            }

            if ($key !== null) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Deep merge two or more arrays.
     *
     * @param array<mixed> ...$arrays Arrays to merge
     * @return array<mixed> Merged array
     */
    public static function deepMerge(array ...$arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::deepMerge($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get a value from array using dot notation.
     *
     * @param array<mixed> $array Source array
     * @param string $path Dot-notation path (e.g., 'user.name')
     * @param mixed $default Default value if not found
     * @return mixed Value or default
     */
    public static function dotGet(array $array, string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (! is_array($array) || ! array_key_exists($key, $array)) {
                return $default;
            }
            $array = $array[$key];
        }

        return $array;
    }

    /**
     * Set a value in array using dot notation.
     *
     * @param array<mixed> $array Target array (passed by reference)
     * @param string $path Dot-notation path
     * @param mixed $value Value to set
     */
    public static function dotSet(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (! isset($current[$key]) || ! is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Check if array has a key using dot notation.
     *
     * @param array<mixed> $array Source array
     * @param string $path Dot-notation path
     * @return bool True if path exists
     */
    public static function dotHas(array $array, string $path): bool
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (! is_array($array) || ! array_key_exists($key, $array)) {
                return false;
            }
            $array = $array[$key];
        }

        return true;
    }

    /**
     * Remove a key from array using dot notation.
     *
     * @param array<mixed> $array Target array (passed by reference)
     * @param string $path Dot-notation path
     */
    public static function dotUnset(array &$array, string $path): void
    {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);
        $current = &$array;

        foreach ($keys as $key) {
            if (! isset($current[$key]) || ! is_array($current[$key])) {
                return;
            }
            $current = &$current[$key];
        }

        unset($current[$lastKey]);
    }

    /**
     * Filter array recursively.
     *
     * @param array<mixed> $array Array to filter
     * @param callable $callback Filter callback
     * @return array<mixed> Filtered array
     */
    public static function filterRecursive(array $array, callable $callback): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::filterRecursive($value, $callback);
            }

            if (! $callback($value, $key)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Map array recursively.
     *
     * @param array<mixed> $array Array to map
     * @param callable $callback Map callback
     * @return array<mixed> Mapped array
     */
    public static function mapRecursive(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::mapRecursive($value, $callback);
            } else {
                $result[$key] = $callback($value, $key);
            }
        }

        return $result;
    }

    /**
     * Check if array is associative.
     *
     * @param array<mixed> $array Array to check
     * @return bool True if associative
     */
    public static function isAssociative(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Ensure value is an array.
     *
     * @param mixed $value Value to convert
     * @return array<mixed> Array representation
     */
    public static function ensure(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null) {
            return [];
        }

        return [$value];
    }

    /**
     * Get only specified keys from array.
     *
     * @param array<mixed> $array Source array
     * @param array<string|int> $keys Keys to include
     * @return array<mixed> Filtered array
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Get all keys except specified from array.
     *
     * @param array<mixed> $array Source array
     * @param array<string|int> $keys Keys to exclude
     * @return array<mixed> Filtered array
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Get first element that passes truth test.
     *
     * @param array<mixed> $array Source array
     * @param callable|null $callback Truth test callback
     * @param mixed $default Default value
     * @return mixed First matching element or default
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get last element that passes truth test.
     *
     * @param array<mixed> $array Source array
     * @param callable|null $callback Truth test callback
     * @param mixed $default Default value
     * @return mixed Last matching element or default
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        $filtered = array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);

        return empty($filtered) ? $default : end($filtered);
    }

    /**
     * Chunk array into smaller arrays.
     *
     * @param array<mixed> $array Source array
     * @param int $size Chunk size
     * @return array<array<mixed>> Chunked array
     */
    public static function chunk(array $array, int $size): array
    {
        return array_chunk($array, $size);
    }

    /**
     * Wrap value in array if not already an array.
     *
     * @param mixed $value Value to wrap
     * @return array<mixed> Wrapped value
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
