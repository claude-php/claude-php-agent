<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * String manipulation utilities.
 */
class StringHelper
{
    /**
     * Truncate string to specified length.
     *
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append (e.g., '...')
     * @return string Truncated string
     */
    public static function truncate(string $string, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Extract an excerpt from text around a keyword.
     *
     * @param string $text Full text
     * @param string $keyword Keyword to center on
     * @param int $length Maximum excerpt length
     * @return string Excerpt
     */
    public static function excerpt(string $text, string $keyword = '', int $length = 200): string
    {
        if (empty($keyword)) {
            return self::truncate($text, $length);
        }

        $pos = mb_stripos($text, $keyword);
        if ($pos === false) {
            return self::truncate($text, $length);
        }

        $start = max(0, $pos - (int)($length / 2));
        $excerpt = mb_substr($text, $start, $length);

        if ($start > 0) {
            $excerpt = '...' . ltrim($excerpt);
        }

        if ($start + $length < mb_strlen($text)) {
            $excerpt = rtrim($excerpt) . '...';
        }

        return $excerpt;
    }

    /**
     * Convert string to URL-safe slug.
     *
     * @param string $string String to slugify
     * @param string $separator Separator character
     * @return string Slug
     */
    public static function slugify(string $string, string $separator = '-'): string
    {
        // Convert to lowercase
        $string = mb_strtolower($string);

        // Remove special characters
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);

        // Replace whitespace and multiple separators
        $string = preg_replace('/[\s-]+/', $separator, $string);

        // Trim separators
        return trim($string, $separator);
    }

    /**
     * Mask sensitive data (e.g., API keys).
     *
     * @param string $string String to mask
     * @param int $visibleStart Characters visible at start
     * @param int $visibleEnd Characters visible at end
     * @param string $mask Mask character
     * @return string Masked string
     */
    public static function mask(string $string, int $visibleStart = 4, int $visibleEnd = 4, string $mask = '*'): string
    {
        $length = mb_strlen($string);

        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat($mask, $length);
        }

        $start = mb_substr($string, 0, $visibleStart);
        $end = mb_substr($string, -$visibleEnd);
        $maskedLength = $length - $visibleStart - $visibleEnd;

        return $start . str_repeat($mask, $maskedLength) . $end;
    }

    /**
     * Generate a random string.
     *
     * @param int $length String length
     * @param string $characters Characters to use
     * @return string Random string
     */
    public static function random(int $length = 16, string $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $result = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Check if string starts with substring.
     *
     * @param string $haystack String to search in
     * @param string $needle Substring to find
     * @param bool $caseSensitive Case-sensitive comparison
     * @return bool True if starts with
     */
    public static function startsWith(string $haystack, string $needle, bool $caseSensitive = true): bool
    {
        if ($caseSensitive) {
            return str_starts_with($haystack, $needle);
        }

        return stripos($haystack, $needle) === 0;
    }

    /**
     * Check if string ends with substring.
     *
     * @param string $haystack String to search in
     * @param string $needle Substring to find
     * @param bool $caseSensitive Case-sensitive comparison
     * @return bool True if ends with
     */
    public static function endsWith(string $haystack, string $needle, bool $caseSensitive = true): bool
    {
        if ($caseSensitive) {
            return str_ends_with($haystack, $needle);
        }

        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return strcasecmp(substr($haystack, -$length), $needle) === 0;
    }

    /**
     * Check if string contains substring.
     *
     * @param string $haystack String to search in
     * @param string $needle Substring to find
     * @param bool $caseSensitive Case-sensitive comparison
     * @return bool True if contains
     */
    public static function contains(string $haystack, string $needle, bool $caseSensitive = true): bool
    {
        if ($caseSensitive) {
            return str_contains($haystack, $needle);
        }

        return stripos($haystack, $needle) !== false;
    }

    /**
     * Convert string to camelCase.
     *
     * @param string $string String to convert
     * @return string CamelCase string
     */
    public static function camelCase(string $string): string
    {
        $string = self::studlyCase($string);

        return lcfirst($string);
    }

    /**
     * Convert string to StudlyCase (PascalCase).
     *
     * @param string $string String to convert
     * @return string StudlyCase string
     */
    public static function studlyCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);

        return str_replace(' ', '', $string);
    }

    /**
     * Convert string to snake_case.
     *
     * @param string $string String to convert
     * @return string snake_case string
     */
    public static function snakeCase(string $string): string
    {
        $string = preg_replace('/\s+/', '_', $string);
        $string = preg_replace('/(.)(?=[A-Z])/u', '$1_', $string);

        return mb_strtolower($string);
    }

    /**
     * Convert string to kebab-case.
     *
     * @param string $string String to convert
     * @return string kebab-case string
     */
    public static function kebabCase(string $string): string
    {
        return str_replace('_', '-', self::snakeCase($string));
    }

    /**
     * Limit string to a number of words.
     *
     * @param string $string String to limit
     * @param int $words Number of words
     * @param string $end End string
     * @return string Limited string
     */
    public static function words(string $string, int $words = 100, string $end = '...'): string
    {
        $wordArray = preg_split('/\s+/', $string, $words + 1);

        if (count($wordArray) <= $words) {
            return $string;
        }

        array_pop($wordArray);

        return implode(' ', $wordArray) . $end;
    }

    /**
     * Replace first occurrence of substring.
     *
     * @param string $search Search string
     * @param string $replace Replacement string
     * @param string $subject Subject string
     * @return string Modified string
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $pos = strpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Replace last occurrence of substring.
     *
     * @param string $search Search string
     * @param string $replace Replacement string
     * @param string $subject Subject string
     * @return string Modified string
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        $pos = strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Remove prefix from string.
     *
     * @param string $string String to modify
     * @param string $prefix Prefix to remove
     * @return string String without prefix
     */
    public static function removePrefix(string $string, string $prefix): string
    {
        if (str_starts_with($string, $prefix)) {
            return substr($string, strlen($prefix));
        }

        return $string;
    }

    /**
     * Remove suffix from string.
     *
     * @param string $string String to modify
     * @param string $suffix Suffix to remove
     * @return string String without suffix
     */
    public static function removeSuffix(string $string, string $suffix): string
    {
        if (str_ends_with($string, $suffix)) {
            return substr($string, 0, -strlen($suffix));
        }

        return $string;
    }

    /**
     * Add prefix if not already present.
     *
     * @param string $string String to modify
     * @param string $prefix Prefix to add
     * @return string String with prefix
     */
    public static function ensurePrefix(string $string, string $prefix): string
    {
        if (! str_starts_with($string, $prefix)) {
            return $prefix . $string;
        }

        return $string;
    }

    /**
     * Add suffix if not already present.
     *
     * @param string $string String to modify
     * @param string $suffix Suffix to add
     * @return string String with suffix
     */
    public static function ensureSuffix(string $string, string $suffix): string
    {
        if (! str_ends_with($string, $suffix)) {
            return $string . $suffix;
        }

        return $string;
    }

    /**
     * Wrap string at specified width.
     *
     * @param string $string String to wrap
     * @param int $width Maximum line width
     * @param string $break Line break character
     * @param bool $cutLongWords Cut long words
     * @return string Wrapped string
     */
    public static function wrap(string $string, int $width = 75, string $break = "\n", bool $cutLongWords = false): string
    {
        return wordwrap($string, $width, $break, $cutLongWords);
    }

    /**
     * Pad string to specified length.
     *
     * @param string $string String to pad
     * @param int $length Target length
     * @param string $pad Padding character
     * @param int $type Padding type (STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH)
     * @return string Padded string
     */
    public static function pad(string $string, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        return str_pad($string, $length, $pad, $type);
    }

    /**
     * Reverse a string.
     *
     * @param string $string String to reverse
     * @return string Reversed string
     */
    public static function reverse(string $string): string
    {
        return strrev($string);
    }

    /**
     * Count occurrences of substring.
     *
     * @param string $haystack String to search in
     * @param string $needle Substring to count
     * @param bool $caseSensitive Case-sensitive comparison
     * @return int Number of occurrences
     */
    public static function count(string $haystack, string $needle, bool $caseSensitive = true): int
    {
        if ($caseSensitive) {
            return substr_count($haystack, $needle);
        }

        return substr_count(strtolower($haystack), strtolower($needle));
    }

    /**
     * Split string by delimiter.
     *
     * @param string $string String to split
     * @param string $delimiter Delimiter
     * @param int $limit Maximum splits
     * @return array<string> Parts
     */
    public static function split(string $string, string $delimiter, int $limit = PHP_INT_MAX): array
    {
        return explode($delimiter, $string, $limit);
    }

    /**
     * Convert line endings to specified format.
     *
     * @param string $string String to convert
     * @param string $lineEnding Target line ending (\n, \r\n, \r)
     * @return string Converted string
     */
    public static function normalizeLineEndings(string $string, string $lineEnding = "\n"): string
    {
        return preg_replace('/\r\n|\r|\n/', $lineEnding, $string);
    }
}
