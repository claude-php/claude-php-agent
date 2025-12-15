<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * DateTime tool for date/time operations and formatting.
 */
class DateTimeTool
{
    /**
     * Create a datetime tool.
     *
     * @param array{
     *     default_timezone?: string,
     *     default_format?: string
     * } $config Configuration options
     */
    public static function create(array $config = []): Tool
    {
        $defaultTimezone = $config['default_timezone'] ?? 'UTC';
        $defaultFormat = $config['default_format'] ?? 'Y-m-d H:i:s T';

        return Tool::create('datetime')
            ->description(
                'Get current date/time, format dates, calculate date differences, ' .
                'or perform date arithmetic. Supports timezone conversions.'
            )
            ->stringParam(
                'operation',
                'Operation to perform',
                true,
                ['now', 'format', 'parse', 'add', 'subtract', 'diff']
            )
            ->stringParam('date', 'Date string (for format, parse, add, subtract, diff operations)', false)
            ->stringParam('timezone', "Timezone (e.g., 'America/New_York', 'Europe/London')", false)
            ->stringParam('format', "Format string (e.g., 'Y-m-d', 'F j, Y')", false)
            ->stringParam('interval', "Interval to add/subtract (e.g., '+1 day', '-2 weeks', '+3 months')", false)
            ->stringParam('date2', 'Second date for diff operation', false)
            ->handler(function (array $input) use ($defaultTimezone, $defaultFormat): ToolResult {
                $operation = $input['operation'];
                $timezone = $input['timezone'] ?? $defaultTimezone;
                $format = $input['format'] ?? $defaultFormat;

                try {
                    $tz = new DateTimeZone($timezone);

                    switch ($operation) {
                        case 'now':
                            $dt = new DateTime('now', $tz);

                            return ToolResult::success([
                                'datetime' => $dt->format($format),
                                'timestamp' => $dt->getTimestamp(),
                                'timezone' => $timezone,
                            ]);

                        case 'format':
                            if (empty($input['date'])) {
                                return ToolResult::error('Date parameter is required for format operation');
                            }
                            $dt = new DateTime($input['date'], $tz);

                            return ToolResult::success([
                                'formatted' => $dt->format($format),
                                'timestamp' => $dt->getTimestamp(),
                            ]);

                        case 'parse':
                            if (empty($input['date'])) {
                                return ToolResult::error('Date parameter is required for parse operation');
                            }
                            $dt = new DateTime($input['date'], $tz);

                            return ToolResult::success([
                                'year' => (int) $dt->format('Y'),
                                'month' => (int) $dt->format('m'),
                                'day' => (int) $dt->format('d'),
                                'hour' => (int) $dt->format('H'),
                                'minute' => (int) $dt->format('i'),
                                'second' => (int) $dt->format('s'),
                                'day_of_week' => $dt->format('l'),
                                'timestamp' => $dt->getTimestamp(),
                            ]);

                        case 'add':
                            if (empty($input['date'])) {
                                return ToolResult::error('Date parameter is required for add operation');
                            }
                            if (empty($input['interval'])) {
                                return ToolResult::error('Interval parameter is required for add operation');
                            }
                            $dt = new DateTime($input['date'], $tz);
                            $dt->modify($input['interval']);

                            return ToolResult::success([
                                'result' => $dt->format($format),
                                'timestamp' => $dt->getTimestamp(),
                            ]);

                        case 'subtract':
                            if (empty($input['date'])) {
                                return ToolResult::error('Date parameter is required for subtract operation');
                            }
                            if (empty($input['interval'])) {
                                return ToolResult::error('Interval parameter is required for subtract operation');
                            }
                            $dt = new DateTime($input['date'], $tz);
                            // Ensure interval starts with - for subtraction
                            $interval = $input['interval'];
                            if (! str_starts_with($interval, '-')) {
                                if (str_starts_with($interval, '+')) {
                                    $interval = '-' . substr($interval, 1);
                                } else {
                                    $interval = '-' . $interval;
                                }
                            }
                            $dt->modify($interval);

                            return ToolResult::success([
                                'result' => $dt->format($format),
                                'timestamp' => $dt->getTimestamp(),
                            ]);

                        case 'diff':
                            if (empty($input['date'])) {
                                return ToolResult::error('Date parameter is required for diff operation');
                            }
                            if (empty($input['date2'])) {
                                return ToolResult::error('Date2 parameter is required for diff operation');
                            }
                            $dt1 = new DateTime($input['date'], $tz);
                            $dt2 = new DateTime($input['date2'], $tz);
                            $diff = $dt1->diff($dt2);

                            return ToolResult::success([
                                'years' => $diff->y,
                                'months' => $diff->m,
                                'days' => $diff->d,
                                'hours' => $diff->h,
                                'minutes' => $diff->i,
                                'seconds' => $diff->s,
                                'total_days' => $diff->days,
                                'is_negative' => $diff->invert === 1,
                            ]);

                        default:
                            return ToolResult::error("Unknown operation: {$operation}");
                    }
                } catch (Exception $e) {
                    return ToolResult::error("DateTime error: {$e->getMessage()}");
                }
            });
    }
}
