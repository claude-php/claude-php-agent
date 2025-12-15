<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Formatting utilities for display and output.
 */
class Formatter
{
    /**
     * Format bytes in human-readable format.
     *
     * @param int $bytes Number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted bytes
     */
    public static function bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        if ($factor >= count($units)) {
            $factor = count($units) - 1;
        }

        $value = $bytes / (1024 ** $factor);

        return number_format($value, $precision) . ' ' . $units[$factor];
    }

    /**
     * Format number with thousands separator.
     *
     * @param int|float $number Number to format
     * @param int $decimals Decimal places
     * @param string $decimalSeparator Decimal separator
     * @param string $thousandsSeparator Thousands separator
     * @return string Formatted number
     */
    public static function number(
        int|float $number,
        int $decimals = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
    ): string {
        return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Format percentage.
     *
     * @param float $value Value (0-1 or 0-100)
     * @param int $decimals Decimal places
     * @param bool $normalize Normalize 0-1 to percentage
     * @return string Formatted percentage
     */
    public static function percentage(float $value, int $decimals = 1, bool $normalize = true): string
    {
        if ($normalize && $value <= 1) {
            $value *= 100;
        }

        return number_format($value, $decimals) . '%';
    }

    /**
     * Truncate string from middle (useful for long hashes, paths).
     *
     * @param string $string String to truncate
     * @param int $maxLength Maximum length
     * @param string $separator Middle separator
     * @return string Truncated string
     */
    public static function truncateMiddle(string $string, int $maxLength, string $separator = '...'): string
    {
        if (strlen($string) <= $maxLength) {
            return $string;
        }

        $separatorLength = strlen($separator);
        $charsToShow = $maxLength - $separatorLength;
        $frontChars = (int)ceil($charsToShow / 2);
        $backChars = (int)floor($charsToShow / 2);

        return substr($string, 0, $frontChars) . $separator . substr($string, -$backChars);
    }

    /**
     * Format money/currency.
     *
     * @param float $amount Amount
     * @param string $currency Currency symbol
     * @param int $decimals Decimal places
     * @return string Formatted currency
     */
    public static function money(float $amount, string $currency = '$', int $decimals = 2): string
    {
        return $currency . number_format($amount, $decimals);
    }

    /**
     * Format array as comma-separated list.
     *
     * @param array<mixed> $items Items to format
     * @param string $separator Separator
     * @param string|null $lastSeparator Last separator (e.g., 'and')
     * @return string Formatted list
     */
    public static function list(array $items, string $separator = ', ', ?string $lastSeparator = null): string
    {
        if (empty($items)) {
            return '';
        }

        if (count($items) === 1) {
            return (string)$items[0];
        }

        if ($lastSeparator === null) {
            return implode($separator, $items);
        }

        $last = array_pop($items);

        return implode($separator, $items) . " {$lastSeparator} {$last}";
    }

    /**
     * Format table for console output.
     *
     * @param array<array<string>> $rows Table rows
     * @param array<string>|null $headers Column headers
     * @return string Formatted table
     */
    public static function table(array $rows, ?array $headers = null): string
    {
        if (empty($rows)) {
            return '';
        }

        // Calculate column widths
        $allRows = $headers ? array_merge([$headers], $rows) : $rows;
        $columnCount = max(array_map('count', $allRows));
        $widths = array_fill(0, $columnCount, 0);

        foreach ($allRows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string)$cell));
            }
        }

        $output = '';
        $separator = '+' . implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)) . '+';

        // Headers
        if ($headers) {
            $output .= $separator . "\n";
            $output .= '|' . implode('|', array_map(
                fn ($cell, $width) => ' ' . str_pad((string)$cell, $width) . ' ',
                $headers,
                $widths
            )) . "|\n";
        }

        $output .= $separator . "\n";

        // Rows
        foreach ($rows as $row) {
            $output .= '|' . implode('|', array_map(
                fn ($cell, $width) => ' ' . str_pad((string)($cell ?? ''), $width) . ' ',
                array_pad($row, $columnCount, ''),
                $widths
            )) . "|\n";
        }

        $output .= $separator;

        return $output;
    }

    /**
     * Format boolean as Yes/No.
     *
     * @param bool $value Boolean value
     * @param string $trueText Text for true
     * @param string $falseText Text for false
     * @return string Formatted boolean
     */
    public static function boolean(bool $value, string $trueText = 'Yes', string $falseText = 'No'): string
    {
        return $value ? $trueText : $falseText;
    }

    /**
     * Format date and time.
     *
     * @param int|\DateTimeInterface $timestamp Timestamp or DateTime
     * @param string $format Date format
     * @return string Formatted date
     */
    public static function date(int|\DateTimeInterface $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        if ($timestamp instanceof \DateTimeInterface) {
            return $timestamp->format($format);
        }

        return date($format, $timestamp);
    }

    /**
     * Format array as JSON with pretty printing.
     *
     * @param mixed $data Data to format
     * @param int $flags JSON encode flags
     * @return string Formatted JSON
     */
    public static function json(mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return JsonHelper::encode($data, $flags);
    }

    /**
     * Format array as YAML-like output.
     *
     * @param array<mixed> $array Array to format
     * @param int $indent Current indentation level
     * @return string YAML-like string
     */
    public static function yaml(array $array, int $indent = 0): string
    {
        $output = '';
        $spacing = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output .= "{$spacing}{$key}:\n";
                $output .= self::yaml($value, $indent + 1);
            } else {
                $output .= "{$spacing}{$key}: {$value}\n";
            }
        }

        return $output;
    }

    /**
     * Format key-value pairs.
     *
     * @param array<string, mixed> $data Key-value pairs
     * @param string $separator Separator between key and value
     * @param string $delimiter Delimiter between pairs
     * @return string Formatted string
     */
    public static function keyValue(
        array $data,
        string $separator = ': ',
        string $delimiter = "\n"
    ): string {
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key . $separator . $value;
        }

        return implode($delimiter, $lines);
    }

    /**
     * Format progress bar.
     *
     * @param float $progress Progress (0-1)
     * @param int $width Bar width in characters
     * @param string $filled Filled character
     * @param string $empty Empty character
     * @return string Progress bar
     */
    public static function progressBar(
        float $progress,
        int $width = 20,
        string $filled = '=',
        string $empty = ' '
    ): string {
        $progress = max(0, min(1, $progress));
        $filledWidth = (int)round($width * $progress);
        $emptyWidth = $width - $filledWidth;

        $bar = str_repeat($filled, $filledWidth) . str_repeat($empty, $emptyWidth);
        $percentage = self::percentage($progress, 0);

        return "[{$bar}] {$percentage}";
    }

    /**
     * Format file size (alias for bytes).
     *
     * @param int $bytes File size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted file size
     */
    public static function fileSize(int $bytes, int $precision = 2): string
    {
        return self::bytes($bytes, $precision);
    }

    /**
     * Format phone number.
     *
     * @param string $phone Phone number
     * @param string $format Format pattern (# = digit)
     * @return string Formatted phone number
     */
    public static function phone(string $phone, string $format = '(###) ###-####'): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $result = '';
        $digitIndex = 0;

        for ($i = 0; $i < strlen($format); $i++) {
            if ($format[$i] === '#') {
                if ($digitIndex < strlen($digits)) {
                    $result .= $digits[$digitIndex++];
                }
            } else {
                $result .= $format[$i];
            }
        }

        return $result;
    }

    /**
     * Format credit card number (with masking).
     *
     * @param string $cardNumber Card number
     * @param bool $mask Mask the number
     * @return string Formatted card number
     */
    public static function creditCard(string $cardNumber, bool $mask = true): string
    {
        $digits = preg_replace('/\D/', '', $cardNumber);

        if ($mask && strlen($digits) >= 12) {
            $digits = str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
        }

        return implode(' ', str_split($digits, 4));
    }

    /**
     * Format class name (strip namespace).
     *
     * @param string|object $class Class name or object
     * @param bool $shortName Return short name only
     * @return string Formatted class name
     */
    public static function className(string|object $class, bool $shortName = true): string
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (! $shortName) {
            return $class;
        }

        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * Format ordinal number (1st, 2nd, 3rd, etc.).
     *
     * @param int $number Number to format
     * @return string Ordinal number
     */
    public static function ordinal(int $number): string
    {
        $suffix = 'th';

        if ($number % 100 < 11 || $number % 100 > 13) {
            switch ($number % 10) {
                case 1:
                    $suffix = 'st';

                    break;
                case 2:
                    $suffix = 'nd';

                    break;
                case 3:
                    $suffix = 'rd';

                    break;
            }
        }

        return $number . $suffix;
    }
}
