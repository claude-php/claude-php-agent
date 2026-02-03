<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Generation\ComponentResult;
use ClaudeAgents\Support\CodeFormatter;
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudePhp\ClaudePhp;

/**
 * Agent for generating code with validation retry loops.
 *
 * Inspired by Langflow's AI-powered assistant, this agent:
 * - Generates code from natural language descriptions
 * - Validates generated code automatically
 * - Retries with improved prompts on validation failure
 * - Provides real-time streaming feedback
 */
class CodeGenerationAgent extends AbstractAgent
{
    private ValidationCoordinator $validationCoordinator;
    private int $maxValidationRetries;
    private bool $enableStreaming;

    /**
     * @var callable|null
     */
    private $onUpdate = null;

    /**
     * @var callable|null
     */
    private $onValidation = null;

    /**
     * @var callable|null
     */
    private $onRetry = null;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration options:
     *   - max_validation_retries: Maximum validation retry attempts (default: 3)
     *   - validation_coordinator: Custom ValidationCoordinator instance
     *   - enable_streaming: Enable streaming updates (default: true)
     *   - name: Agent name (default: 'code_generation_agent')
     *   - model: Model to use (default: 'claude-sonnet-4-5')
     *   - max_tokens: Max tokens per response (default: 4096)
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        parent::__construct($client, $options);

        $this->maxValidationRetries = $options['max_validation_retries'] ?? 3;
        $this->enableStreaming = $options['enable_streaming'] ?? true;

        // Initialize validation coordinator
        if (isset($options['validation_coordinator'])) {
            $this->validationCoordinator = $options['validation_coordinator'];
        } else {
            $this->validationCoordinator = new ValidationCoordinator([
                'logger' => $this->logger,
            ]);
        }
    }

    /**
     * Set progress update callback.
     *
     * @param callable $callback fn(string $type, array $data): void
     */
    public function onUpdate(callable $callback): self
    {
        $this->onUpdate = $callback;
        return $this;
    }

    /**
     * Set validation callback.
     *
     * @param callable $callback fn(ValidationResult $result, int $attempt): void
     */
    public function onValidation(callable $callback): self
    {
        $this->onValidation = $callback;
        return $this;
    }

    /**
     * Set retry callback.
     *
     * @param callable $callback fn(int $attempt, array $errors): void
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetry = $callback;
        return $this;
    }

    /**
     * Get the validation coordinator.
     */
    public function getValidationCoordinator(): ValidationCoordinator
    {
        return $this->validationCoordinator;
    }

    /**
     * Generate a component from natural language description.
     *
     * @param string $description Natural language description of the component
     * @param array<string, mixed> $context Additional context
     * @return ComponentResult Generated and validated component
     * @throws MaxRetriesException If max retries exceeded
     */
    public function generateComponent(string $description, array $context = []): ComponentResult
    {
        $this->logStart($description);
        $this->emitUpdate('code.generating', ['description' => $description]);

        $attempt = 0;
        $lastErrors = [];

        while ($attempt <= $this->maxValidationRetries) {
            try {
                // Generate code
                $this->logDebug("Generation attempt {$attempt}");
                $code = $this->generateCode($description, $context);

                $this->emitUpdate('code.generated', [
                    'attempt' => $attempt,
                    'code_length' => strlen($code),
                    'line_count' => substr_count($code, "\n") + 1,
                ]);

                // Validate code
                $this->emitUpdate('validation.started', ['attempt' => $attempt]);
                $validation = $this->validationCoordinator->validate($code, $context);

                if ($this->onValidation !== null) {
                    ($this->onValidation)($validation, $attempt);
                }

                if ($validation->isValid()) {
                    $this->emitUpdate('validation.passed', [
                        'attempt' => $attempt,
                        'warnings' => $validation->getWarnings(),
                    ]);

                    $this->logSuccess(['attempts' => $attempt + 1]);

                    $result = new ComponentResult($code, $validation, [
                        'attempts' => $attempt + 1,
                        'description' => $description,
                    ]);

                    $this->emitUpdate('component.completed', [
                        'attempts' => $attempt + 1,
                        'code_length' => strlen($code),
                    ]);

                    return $result;
                }

                // Validation failed
                $lastErrors = $validation->getErrors();
                $this->emitUpdate('validation.failed', [
                    'attempt' => $attempt,
                    'errors' => $lastErrors,
                    'warnings' => $validation->getWarnings(),
                ]);

                $this->logDebug("Validation failed with {$validation->getErrorCount()} errors", [
                    'errors' => $lastErrors,
                ]);

                // Check if we can retry
                if ($attempt >= $this->maxValidationRetries) {
                    break;
                }

                // Prepare for retry
                $attempt++;
                $this->emitUpdate('retry.attempt', [
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxValidationRetries + 1,
                ]);

                if ($this->onRetry !== null) {
                    ($this->onRetry)($attempt, $lastErrors);
                }

                // Reframe the description with validation feedback
                $this->emitUpdate('retry.reframing', [
                    'attempt' => $attempt,
                    'errors' => $lastErrors,
                ]);

                $description = $this->createRetryPrompt($code, $lastErrors, $description);
            } catch (\Throwable $e) {
                $this->logError($e->getMessage(), ['attempt' => $attempt]);
                $this->emitUpdate('code.error', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxValidationRetries) {
                    throw $e;
                }

                $attempt++;
            }
        }

        // Max retries exceeded
        $this->logError('Max validation retries exceeded', ['attempts' => $attempt]);
        $this->emitUpdate('validation.max_retries', [
            'attempts' => $attempt,
            'last_errors' => $lastErrors,
        ]);

        throw new MaxRetriesException($this->maxValidationRetries, $lastErrors);
    }

    /**
     * Run the agent with a task (standard AgentInterface method).
     */
    public function run(string $task): AgentResult
    {
        try {
            $result = $this->generateComponent($task);

            return AgentResult::success(
                answer: $result->getCode(),
                messages: [],
                iterations: (int) $result->getMetadata()['attempts'],
                metadata: $result->toArray(),
            );
        } catch (MaxRetriesException $e) {
            return AgentResult::failure(
                error: $e->getMessage(),
                messages: [],
                iterations: $this->maxValidationRetries + 1,
            );
        } catch (\Throwable $e) {
            return AgentResult::failure(
                error: $e->getMessage(),
                messages: [],
                iterations: 1,
            );
        }
    }

    /**
     * Generate code from description using Claude.
     *
     * @param array<string, mixed> $context
     */
    private function generateCode(string $description, array $context = []): string
    {
        $prompt = $this->buildGenerationPrompt($description, $context);

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $rawCode = $this->extractTextContent($response->content);
        
        // Clean the code to remove markdown formatting if present
        return CodeFormatter::cleanPhpCode($rawCode);
    }

    /**
     * Build the code generation prompt.
     *
     * @param array<string, mixed> $context
     */
    private function buildGenerationPrompt(string $description, array $context = []): string
    {
        $prompt = "Generate PHP code based on the following description:\n\n{$description}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Use PHP 8.1+ features\n";
        $prompt .= "- Include strict type declarations\n";
        $prompt .= "- Follow PSR-12 coding standards\n";
        $prompt .= "- Add appropriate docblocks\n";
        $prompt .= "- Write clean, maintainable code\n\n";

        if (! empty($context)) {
            $prompt .= "Context:\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n\n";
        }

        $prompt .= "Return ONLY the PHP code, no explanations or markdown formatting.";

        return $prompt;
    }

    /**
     * Create retry prompt with validation feedback.
     *
     * @param array<string> $errors
     */
    private function createRetryPrompt(string $code, array $errors, string $originalDescription): string
    {
        $prompt = "The following code has validation errors and needs to be fixed.\n\n";
        $prompt .= "Original request: {$originalDescription}\n\n";
        $prompt .= "Generated code:\n```php\n{$code}\n```\n\n";
        $prompt .= "Validation errors:\n";

        foreach ($errors as $i => $error) {
            $prompt .= ($i + 1) . ". {$error}\n";
        }

        $prompt .= "\nPlease fix these errors and regenerate the complete code.\n";
        $prompt .= "Return ONLY the fixed PHP code, no explanations or markdown formatting.";

        return $prompt;
    }

    /**
     * Emit a progress update event.
     *
     * @param array<string, mixed> $data
     */
    private function emitUpdate(string $type, array $data = []): void
    {
        if ($this->onUpdate === null || ! $this->enableStreaming) {
            return;
        }

        try {
            ($this->onUpdate)($type, array_merge($data, [
                'timestamp' => microtime(true),
                'agent' => $this->name,
            ]));
        } catch (\Throwable $e) {
            // Don't let callback errors break the agent
            $this->logger->warning("Update callback error: {$e->getMessage()}");
        }
    }

    protected function getDefaultName(): string
    {
        return 'code_generation_agent';
    }
}
