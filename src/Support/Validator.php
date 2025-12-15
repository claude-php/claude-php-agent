<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Input validation utilities.
 */
class Validator
{
    /**
     * Validate required fields are present.
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string> $required Required field names
     * @return array<string> Missing field names (empty if valid)
     */
    public static function required(array $data, array $required): array
    {
        $missing = [];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Validate data against JSON schema (basic implementation).
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, mixed> $schema JSON schema
     * @return array<string> Validation errors (empty if valid)
     */
    public static function schema(array $data, array $schema): array
    {
        $errors = [];

        // Check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            $missing = self::required($data, $schema['required']);
            foreach ($missing as $field) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Check properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $propertySchema) {
                if (! isset($data[$key])) {
                    continue;
                }

                $value = $data[$key];

                // Type validation
                if (isset($propertySchema['type'])) {
                    $expectedType = $propertySchema['type'];
                    $actualType = self::getType($value);

                    if ($expectedType !== $actualType) {
                        $errors[] = "Field '{$key}' must be of type {$expectedType}, got {$actualType}";
                    }
                }

                // Min/max for numbers
                if (is_numeric($value)) {
                    if (isset($propertySchema['minimum']) && $value < $propertySchema['minimum']) {
                        $errors[] = "Field '{$key}' must be >= {$propertySchema['minimum']}";
                    }
                    if (isset($propertySchema['maximum']) && $value > $propertySchema['maximum']) {
                        $errors[] = "Field '{$key}' must be <= {$propertySchema['maximum']}";
                    }
                }

                // String length
                if (is_string($value)) {
                    if (isset($propertySchema['minLength']) && strlen($value) < $propertySchema['minLength']) {
                        $errors[] = "Field '{$key}' must be at least {$propertySchema['minLength']} characters";
                    }
                    if (isset($propertySchema['maxLength']) && strlen($value) > $propertySchema['maxLength']) {
                        $errors[] = "Field '{$key}' must be at most {$propertySchema['maxLength']} characters";
                    }
                }

                // Enum validation
                if (isset($propertySchema['enum']) && is_array($propertySchema['enum'])) {
                    if (! in_array($value, $propertySchema['enum'], true)) {
                        $allowed = implode(', ', $propertySchema['enum']);
                        $errors[] = "Field '{$key}' must be one of: {$allowed}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get JSON schema type for a value.
     *
     * @param mixed $value Value to check
     * @return string JSON schema type
     */
    private static function getType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_array($value)) {
            return ArrayHelper::isAssociative($value) ? 'object' : 'array';
        }
        if (is_null($value)) {
            return 'null';
        }

        return 'unknown';
    }

    /**
     * Validate email address.
     *
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL.
     *
     * @param string $url URL to validate
     * @return bool True if valid
     */
    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate IP address.
     *
     * @param string $ip IP to validate
     * @param bool $allowIPv6 Allow IPv6 addresses
     * @return bool True if valid
     */
    public static function ip(string $ip, bool $allowIPv6 = true): bool
    {
        $flags = FILTER_FLAG_IPV4;
        if ($allowIPv6) {
            $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Validate that value is in a list of allowed values.
     *
     * @param mixed $value Value to check
     * @param array<mixed> $allowed Allowed values
     * @param bool $strict Use strict comparison
     * @return bool True if valid
     */
    public static function in(mixed $value, array $allowed, bool $strict = true): bool
    {
        return in_array($value, $allowed, $strict);
    }

    /**
     * Validate string matches regex pattern.
     *
     * @param string $value String to validate
     * @param string $pattern Regex pattern
     * @return bool True if matches
     */
    public static function regex(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validate string length is within range.
     *
     * @param string $value String to validate
     * @param int|null $min Minimum length
     * @param int|null $max Maximum length
     * @return bool True if valid
     */
    public static function length(string $value, ?int $min = null, ?int $max = null): bool
    {
        $len = strlen($value);

        if ($min !== null && $len < $min) {
            return false;
        }

        if ($max !== null && $len > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate number is within range.
     *
     * @param int|float $value Number to validate
     * @param int|float|null $min Minimum value
     * @param int|float|null $max Maximum value
     * @return bool True if valid
     */
    public static function range(int|float $value, int|float|null $min = null, int|float|null $max = null): bool
    {
        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate array has expected structure.
     *
     * @param array<mixed> $array Array to validate
     * @param array<string> $expectedKeys Expected keys
     * @return bool True if all keys present
     */
    public static function arrayStructure(array $array, array $expectedKeys): bool
    {
        return empty(array_diff($expectedKeys, array_keys($array)));
    }

    /**
     * Validate all array values pass a test.
     *
     * @param array<mixed> $array Array to validate
     * @param callable $callback Validation callback
     * @return bool True if all values pass
     */
    public static function arrayAll(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (! $callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate at least one array value passes a test.
     *
     * @param array<mixed> $array Array to validate
     * @param callable $callback Validation callback
     * @return bool True if any value passes
     */
    public static function arrayAny(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate UUID format.
     *
     * @param string $uuid UUID to validate
     * @return bool True if valid UUID
     */
    public static function uuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return self::regex($uuid, $pattern);
    }

    /**
     * Validate date string format.
     *
     * @param string $date Date string
     * @param string $format Expected format (default: Y-m-d)
     * @return bool True if valid
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) === $date;
    }

    /**
     * Validate value is a valid JSON string.
     *
     * @param string $value String to validate
     * @return bool True if valid JSON
     */
    public static function json(string $value): bool
    {
        return JsonHelper::isValid($value);
    }

    /**
     * Sanitize string for safe output.
     *
     * @param string $value String to sanitize
     * @return string Sanitized string
     */
    public static function sanitize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Create a validator instance with rules.
     *
     * @param array<string, mixed> $rules Validation rules
     * @return ValidationRules Fluent validator
     */
    public static function make(array $rules): ValidationRules
    {
        return new ValidationRules($rules);
    }
}

/**
 * Fluent validation rules builder.
 */
class ValidationRules
{
    /**
     * @param array<string, mixed> $rules
     */
    public function __construct(
        private array $rules,
    ) {
    }

    /**
     * Validate data against rules.
     *
     * @param array<string, mixed> $data Data to validate
     * @return array<string> Validation errors
     */
    public function validate(array $data): array
    {
        return Validator::schema($data, $this->rules);
    }

    /**
     * Check if data passes validation.
     *
     * @param array<string, mixed> $data Data to validate
     * @return bool True if valid
     */
    public function passes(array $data): bool
    {
        return empty($this->validate($data));
    }

    /**
     * Check if data fails validation.
     *
     * @param array<string, mixed> $data Data to validate
     * @return bool True if invalid
     */
    public function fails(array $data): bool
    {
        return ! $this->passes($data);
    }
}
