<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;

/**
 * Calculator tool for safe mathematical operations.
 */
class CalculatorTool
{
    /**
     * Create a calculator tool.
     *
     * @param array{
     *     allow_functions?: bool,
     *     max_precision?: int
     * } $config Configuration options
     */
    public static function create(array $config = []): Tool
    {
        $allowFunctions = $config['allow_functions'] ?? false;
        $maxPrecision = $config['max_precision'] ?? 10;

        return Tool::create('calculate')
            ->description(
                'Perform mathematical calculations. ' .
                'Supports basic arithmetic (+, -, *, /), parentheses, and decimal numbers.' .
                ($allowFunctions ? ' Also supports math functions like sqrt, sin, cos, etc.' : '')
            )
            ->stringParam('expression', 'Mathematical expression to evaluate (e.g., "25 * 17 + 42")')
            ->handler(function (array $input) use ($allowFunctions, $maxPrecision): ToolResult {
                $expression = trim($input['expression']);

                if (empty($expression)) {
                    return ToolResult::error('Expression cannot be empty');
                }

                // Define safe pattern based on configuration
                if ($allowFunctions) {
                    // Allow math functions
                    $pattern = '/^[0-9+\-*\/().,\s]+|sqrt|sin|cos|tan|log|exp|pow|abs|ceil|floor|round/i';
                } else {
                    // Basic arithmetic only
                    $pattern = '/^[0-9+\-*\/().\s]+$/';
                }

                if (! preg_match($pattern, $expression)) {
                    return ToolResult::error(
                        'Invalid expression: only numbers and allowed operators/functions are permitted'
                    );
                }

                // Additional security: prevent dangerous patterns
                $dangerous = ['exec', 'system', 'passthru', 'shell_exec', '`', '$', 'eval'];
                foreach ($dangerous as $danger) {
                    if (stripos($expression, $danger) !== false) {
                        return ToolResult::error('Expression contains forbidden content');
                    }
                }

                try {
                    // Evaluate the expression safely
                    $result = @eval("return {$expression};");

                    if ($result === false) {
                        return ToolResult::error('Failed to evaluate expression');
                    }

                    // Round to specified precision
                    if (is_float($result)) {
                        $result = round($result, $maxPrecision);
                    }

                    return ToolResult::success([
                        'result' => $result,
                        'expression' => $expression,
                    ]);
                } catch (\Throwable $e) {
                    return ToolResult::error("Calculation error: {$e->getMessage()}");
                }
            });
    }
}
