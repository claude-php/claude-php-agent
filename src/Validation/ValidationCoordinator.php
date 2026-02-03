<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation;

use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Coordinates multiple validators to validate code.
 *
 * Validators are executed in priority order (lower priority = earlier execution).
 * Validation stops on first failure unless configured to continue.
 */
class ValidationCoordinator
{
    /**
     * @var array<ValidatorInterface>
     */
    private array $validators = [];

    private LoggerInterface $logger;
    private bool $stopOnFirstFailure;
    private bool $cacheResults;

    /**
     * @var array<string, ValidationResult>
     */
    private array $cache = [];

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - stop_on_first_failure: Stop validation on first failure (default: true)
     *   - cache_results: Cache validation results (default: true)
     *   - logger: PSR-3 logger instance
     */
    public function __construct(array $options = [])
    {
        $this->stopOnFirstFailure = $options['stop_on_first_failure'] ?? true;
        $this->cacheResults = $options['cache_results'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Add a validator.
     *
     * @param ValidatorInterface $validator Validator to add
     * @return self
     */
    public function addValidator(ValidatorInterface $validator): self
    {
        $this->validators[] = $validator;
        $this->sortValidators();

        $this->logger->debug("Added validator: {$validator->getName()}", [
            'priority' => $validator->getPriority(),
        ]);

        return $this;
    }

    /**
     * Add multiple validators.
     *
     * @param array<ValidatorInterface> $validators
     * @return self
     */
    public function addValidators(array $validators): self
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }

        return $this;
    }

    /**
     * Remove a validator by name.
     *
     * @param string $name Validator name
     * @return self
     */
    public function removeValidator(string $name): self
    {
        $this->validators = array_filter(
            $this->validators,
            fn (ValidatorInterface $v) => $v->getName() !== $name
        );

        return $this;
    }

    /**
     * Get all registered validators.
     *
     * @return array<ValidatorInterface>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }

    /**
     * Validate code using all registered validators.
     *
     * @param string $code Code to validate
     * @param array<string, mixed> $context Additional context
     * @return ValidationResult Combined validation result
     */
    public function validate(string $code, array $context = []): ValidationResult
    {
        $cacheKey = $this->getCacheKey($code, $context);

        if ($this->cacheResults && isset($this->cache[$cacheKey])) {
            $this->logger->debug('Using cached validation result');
            return $this->cache[$cacheKey];
        }

        if (empty($this->validators)) {
            $this->logger->warning('No validators registered');
            return ValidationResult::success(warnings: ['No validators configured']);
        }

        $this->logger->info('Starting validation', [
            'validators' => count($this->validators),
            'code_length' => strlen($code),
        ]);

        $results = [];
        $startTime = microtime(true);

        foreach ($this->validators as $validator) {
            if (! $validator->canHandle($code)) {
                $this->logger->debug("Validator {$validator->getName()} cannot handle this code, skipping");
                continue;
            }

            $this->logger->debug("Running validator: {$validator->getName()}");

            try {
                $validatorStart = microtime(true);
                $result = $validator->validate($code, $context);
                $duration = microtime(true) - $validatorStart;

                $this->logger->debug("Validator {$validator->getName()} completed", [
                    'valid' => $result->isValid(),
                    'errors' => $result->getErrorCount(),
                    'warnings' => $result->getWarningCount(),
                    'duration_ms' => round($duration * 1000, 2),
                ]);

                $results[] = $result;

                if ($this->stopOnFirstFailure && $result->isFailed()) {
                    $this->logger->info('Stopping validation on first failure', [
                        'failed_validator' => $validator->getName(),
                    ]);
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->error("Validator {$validator->getName()} threw exception: {$e->getMessage()}");
                $results[] = ValidationResult::failure([
                    "Validator {$validator->getName()} failed: {$e->getMessage()}",
                ]);

                if ($this->stopOnFirstFailure) {
                    break;
                }
            }
        }

        $totalDuration = microtime(true) - $startTime;

        // Merge all results
        $finalResult = $this->mergeResults($results);

        // Add metadata about validation process
        $metadata = array_merge($finalResult->getMetadata(), [
            'validator_count' => count($results),
            'duration_ms' => round($totalDuration * 1000, 2),
        ]);

        $finalResult = new ValidationResult(
            $finalResult->isValid(),
            $finalResult->getErrors(),
            $finalResult->getWarnings(),
            $metadata
        );

        $this->logger->info('Validation completed', [
            'valid' => $finalResult->isValid(),
            'errors' => $finalResult->getErrorCount(),
            'warnings' => $finalResult->getWarningCount(),
            'duration_ms' => round($totalDuration * 1000, 2),
        ]);

        // Cache the result
        if ($this->cacheResults) {
            $this->cache[$cacheKey] = $finalResult;
        }

        return $finalResult;
    }

    /**
     * Clear validation cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->logger->debug('Validation cache cleared');
    }

    /**
     * Sort validators by priority (lower = earlier).
     */
    private function sortValidators(): void
    {
        usort($this->validators, function (ValidatorInterface $a, ValidatorInterface $b) {
            return $a->getPriority() <=> $b->getPriority();
        });
    }

    /**
     * Merge multiple validation results.
     *
     * @param array<ValidationResult> $results
     */
    private function mergeResults(array $results): ValidationResult
    {
        if (empty($results)) {
            return ValidationResult::success();
        }

        $merged = array_shift($results);
        foreach ($results as $result) {
            $merged = $merged->merge($result);
        }

        return $merged;
    }

    /**
     * Generate cache key for code and context.
     *
     * @param array<string, mixed> $context
     */
    private function getCacheKey(string $code, array $context): string
    {
        return md5($code . serialize($context));
    }
}
