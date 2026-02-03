<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Validators;

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

/**
 * Validator that validates PHP components by instantiation.
 *
 * This validator wraps ComponentValidationService to integrate with
 * the ValidationCoordinator system. It validates components by:
 * 1. Extracting the class name from code
 * 2. Dynamically loading the class
 * 3. Instantiating it to trigger constructor validation
 *
 * This provides runtime validation beyond syntax and static analysis.
 */
class ComponentInstantiationValidator implements ValidatorInterface
{
    private ComponentValidationService $service;
    private int $priority;
    private bool $enabled;

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - load_strategy: 'temp_file' or 'eval' (default: 'temp_file')
     *   - allow_eval: Allow eval strategy (default: false)
     *   - temp_dir: Directory for temp files (default: sys_get_temp_dir())
     *   - cleanup_temp_files: Auto-cleanup temp files (default: true)
     *   - constructor_args: Args to pass to constructor (default: [])
     *   - timeout: Timeout in seconds (default: 5.0)
     *   - catch_fatal_errors: Catch fatal errors (default: true)
     *   - priority: Validator priority (default: 50)
     *   - enabled: Enable/disable validator (default: true)
     */
    public function __construct(array $options = [])
    {
        $this->service = new ComponentValidationService($options);
        $this->priority = $options['priority'] ?? 50;
        $this->enabled = $options['enabled'] ?? true;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(string $code, array $context = []): ValidationResult
    {
        if (! $this->enabled) {
            return ValidationResult::success(
                warnings: ['Component instantiation validator is disabled'],
                metadata: ['validator' => 'component_instantiation', 'enabled' => false]
            );
        }

        return $this->service->validate($code, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'component_instantiation';
    }

    /**
     * {@inheritDoc}
     */
    public function canHandle(string $code): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // Check if code contains a class definition
        // Quick check before more expensive validation
        return str_contains($code, 'class ') &&
               (str_contains($code, '<?php') || str_starts_with(trim($code), '<?php'));
    }

    /**
     * {@inheritDoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if validator is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable or disable the validator.
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get the underlying validation service.
     */
    public function getService(): ComponentValidationService
    {
        return $this->service;
    }
}
