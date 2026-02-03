<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation;

use ClaudeAgents\Validation\Exceptions\ComponentValidationException;

/**
 * Service for validating PHP components by dynamic instantiation.
 *
 * Inspired by Langflow's component validation approach, this service:
 * 1. Extracts the class name from generated code
 * 2. Dynamically loads the class using ClassLoader
 * 3. Instantiates the class to trigger constructor validation
 * 4. Catches comprehensive exception types and returns ValidationResult
 *
 * This provides runtime validation beyond syntax and static analysis.
 */
class ComponentValidationService
{
    private ClassLoader $classLoader;
    private array $constructorArgs;
    private float $timeout;
    private bool $catchFatalErrors;

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - load_strategy: 'temp_file' or 'eval' (default: 'temp_file')
     *   - allow_eval: Allow eval strategy (default: false)
     *   - temp_dir: Directory for temp files (default: sys_get_temp_dir())
     *   - cleanup_temp_files: Auto-cleanup temp files (default: true)
     *   - constructor_args: Args to pass to constructor (default: [])
     *   - timeout: Timeout in seconds (default: 5.0)
     *   - catch_fatal_errors: Catch fatal errors (default: true)
     */
    public function __construct(array $options = [])
    {
        // Extract ClassLoader options
        $loaderOptions = [
            'load_strategy' => $options['load_strategy'] ?? 'temp_file',
            'allow_eval' => $options['allow_eval'] ?? false,
            'temp_dir' => $options['temp_dir'] ?? sys_get_temp_dir(),
            'cleanup_temp_files' => $options['cleanup_temp_files'] ?? true,
        ];

        $this->classLoader = new ClassLoader($loaderOptions);
        $this->constructorArgs = $options['constructor_args'] ?? [];
        $this->timeout = $options['timeout'] ?? 5.0;
        $this->catchFatalErrors = $options['catch_fatal_errors'] ?? true;
    }

    /**
     * Validate component code by instantiation.
     *
     * @param string $code PHP code containing the component class
     * @param array<string, mixed> $context Additional context:
     *   - constructor_args: Override default constructor args
     *   - expected_class_name: Expected class name (for verification)
     * @return ValidationResult Validation result
     */
    public function validate(string $code, array $context = []): ValidationResult
    {
        $startTime = microtime(true);

        try {
            // Extract class name
            $classInfo = $this->extractClassInfo($code);
            $className = $classInfo['class_name'];
            $namespace = $classInfo['namespace'];

            if ($className === null) {
                return ValidationResult::failure(
                    errors: ['No class definition found in code'],
                    metadata: [
                        'validator' => 'component_validation',
                        'load_strategy' => $this->classLoader instanceof ClassLoader ? 'temp_file' : 'eval',
                        'code_length' => strlen($code),
                    ]
                );
            }

            // Verify expected class name if provided
            $expectedClassName = $context['expected_class_name'] ?? null;
            if ($expectedClassName !== null && $className !== $expectedClassName) {
                return ValidationResult::failure(
                    errors: [
                        "Expected class name '{$expectedClassName}' but found '{$className}'",
                    ],
                    metadata: [
                        'validator' => 'component_validation',
                        'class_name' => $className,
                        'expected_class_name' => $expectedClassName,
                        'namespace' => $namespace,
                    ]
                );
            }

            // Load the class
            $fqcn = $this->loadClass($code, $className);

            // Instantiate the class
            $constructorArgs = $context['constructor_args'] ?? $this->constructorArgs;
            $instance = $this->instantiateClass($fqcn, $constructorArgs);

            $duration = microtime(true) - $startTime;

            return ValidationResult::success(
                metadata: [
                    'validator' => 'component_validation',
                    'class_name' => $className,
                    'namespace' => $namespace,
                    'fully_qualified_class_name' => $fqcn,
                    'load_strategy' => $this->getLoadStrategy(),
                    'instantiation_time_ms' => round($duration * 1000, 2),
                    'constructor_args_count' => count($constructorArgs),
                    'instance_class' => get_class($instance),
                ]
            );
        } catch (ComponentValidationException $e) {
            $duration = microtime(true) - $startTime;

            return ValidationResult::failure(
                errors: [$e->getDetailedMessage()],
                metadata: [
                    'validator' => 'component_validation',
                    'class_name' => $e->getClassName(),
                    'exception_type' => get_class($e->getOriginalException() ?? $e),
                    'exception_message' => $e->getMessage(),
                    'load_strategy' => $this->getLoadStrategy(),
                    'duration_ms' => round($duration * 1000, 2),
                    'code_snippet' => $e->getCodeSnippet(3),
                ]
            );
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            return ValidationResult::failure(
                errors: [
                    sprintf('%s: %s', get_class($e), $e->getMessage()),
                ],
                metadata: [
                    'validator' => 'component_validation',
                    'exception_type' => get_class($e),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'load_strategy' => $this->getLoadStrategy(),
                    'duration_ms' => round($duration * 1000, 2),
                ]
            );
        }
    }

    /**
     * Extract class name and namespace from code.
     *
     * Uses multiple strategies:
     * 1. Token parsing (primary)
     * 2. Regex fallback (for broken code)
     *
     * @return array{class_name: string|null, namespace: string|null}
     */
    public function extractClassInfo(string $code): array
    {
        // Try token parsing first (more reliable)
        $info = $this->extractClassInfoFromTokens($code);
        if ($info['class_name'] !== null) {
            return $info;
        }

        // Fallback to regex
        return $this->extractClassInfoFromRegex($code);
    }

    /**
     * Extract class name using token parsing.
     *
     * @return array{class_name: string|null, namespace: string|null}
     */
    private function extractClassInfoFromTokens(string $code): array
    {
        $className = null;
        $namespace = null;

        try {
            $tokens = @token_get_all($code);
            $tokenCount = count($tokens);

            for ($i = 0; $i < $tokenCount; $i++) {
                if (! is_array($tokens[$i])) {
                    continue;
                }

                [$id, $text] = $tokens[$i];

                // Look for namespace
                if ($id === T_NAMESPACE) {
                    $j = $i + 1;
                    $namespaceParts = [];

                    while ($j < $tokenCount) {
                        if (! is_array($tokens[$j])) {
                            if ($tokens[$j] === ';') {
                                break;
                            }
                            $j++;
                            continue;
                        }

                        $tokenId = $tokens[$j][0];
                        $tokenText = $tokens[$j][1];

                        if ($tokenId === T_STRING || $tokenId === T_NAME_QUALIFIED || $tokenId === T_NS_SEPARATOR) {
                            $namespaceParts[] = $tokenText;
                        } elseif ($tokenId === T_WHITESPACE) {
                            // Skip whitespace
                        } else {
                            break;
                        }

                        $j++;
                    }

                    if (! empty($namespaceParts)) {
                        $namespace = implode('', $namespaceParts);
                    }
                }

                // Look for class
                if ($id === T_CLASS) {
                    // Skip abstract classes
                    if ($i > 0 && is_array($tokens[$i - 1]) && $tokens[$i - 1][0] === T_ABSTRACT) {
                        continue;
                    }

                    // Find class name (next T_STRING token)
                    for ($j = $i + 1; $j < $tokenCount; $j++) {
                        if (! is_array($tokens[$j])) {
                            continue;
                        }

                        if ($tokens[$j][0] === T_STRING) {
                            $className = $tokens[$j][1];
                            break;
                        }

                        if ($tokens[$j][0] !== T_WHITESPACE) {
                            break;
                        }
                    }

                    if ($className !== null) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Token parsing failed, will fall back to regex
        }

        return [
            'class_name' => $className,
            'namespace' => $namespace,
        ];
    }

    /**
     * Extract class name using regex (fallback for broken code).
     *
     * @return array{class_name: string|null, namespace: string|null}
     */
    private function extractClassInfoFromRegex(string $code): array
    {
        $className = null;
        $namespace = null;

        // Extract namespace
        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/i', $code, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name (avoid abstract classes)
        if (preg_match('/^(?!.*\babstract\b).*\bclass\s+(\w+)\s*(?:extends|implements|{)/mi', $code, $matches)) {
            $className = $matches[1];
        }

        return [
            'class_name' => $className,
            'namespace' => $namespace,
        ];
    }

    /**
     * Load class from code.
     *
     * @throws ComponentValidationException
     */
    private function loadClass(string $code, string $className): string
    {
        try {
            return $this->classLoader->loadClass($code, $className);
        } catch (\Throwable $e) {
            throw new ComponentValidationException(
                "Failed to load class: {$e->getMessage()}",
                $className,
                $code,
                $e
            );
        }
    }

    /**
     * Instantiate class with constructor arguments.
     *
     * @param string $fqcn Fully qualified class name
     * @param array<mixed> $constructorArgs Constructor arguments
     * @return object Instance of the class
     * @throws ComponentValidationException
     */
    private function instantiateClass(string $fqcn, array $constructorArgs = []): object
    {
        try {
            // Handle different instantiation scenarios
            if (empty($constructorArgs)) {
                return new $fqcn();
            }

            // Use reflection for constructor with args
            $reflection = new \ReflectionClass($fqcn);
            return $reflection->newInstanceArgs($constructorArgs);
        } catch (\ParseError $e) {
            throw new ComponentValidationException(
                "Parse error during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        } catch (\Error $e) {
            throw new ComponentValidationException(
                "Fatal error during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        } catch (\TypeError $e) {
            throw new ComponentValidationException(
                "Type error during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        } catch (\ArgumentCountError $e) {
            throw new ComponentValidationException(
                "Argument count error during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        } catch (\Exception $e) {
            throw new ComponentValidationException(
                "Exception during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        } catch (\Throwable $e) {
            throw new ComponentValidationException(
                "Error during instantiation: {$e->getMessage()}",
                $fqcn,
                '',
                $e
            );
        }
    }

    /**
     * Get the current load strategy.
     */
    private function getLoadStrategy(): string
    {
        // This is a simple implementation - in reality, ClassLoader handles this
        return 'temp_file'; // Default strategy
    }

    /**
     * Get the class loader instance.
     */
    public function getClassLoader(): ClassLoader
    {
        return $this->classLoader;
    }
}
