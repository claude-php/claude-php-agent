<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration;

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Generation\ComponentResult;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the complete code generation flow.
 *
 * Note: This test requires a valid ANTHROPIC_API_KEY environment variable.
 * Skip these tests if the key is not available.
 *
 * @group integration
 * @group requires-api-key
 */
class CodeGenerationFlowTest extends TestCase
{
    private ?ClaudePhp $client = null;

    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
    }

    public function test_generates_simple_class(): void
    {
        $validator = new ValidationCoordinator();
        $validator->addValidator(new PHPSyntaxValidator());

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $validator,
            'max_validation_retries' => 2,
        ]);

        $description = 'Create a simple Calculator class with add and subtract methods';

        $result = $agent->generateComponent($description);

        $this->assertInstanceOf(ComponentResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('class Calculator', $result->getCode());
        $this->assertStringContainsString('function add', $result->getCode());
        $this->assertStringContainsString('function subtract', $result->getCode());
    }

    public function test_tracks_progress_with_callbacks(): void
    {
        $validator = new ValidationCoordinator();
        $validator->addValidator(new PHPSyntaxValidator());

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $validator,
            'max_validation_retries' => 1,
        ]);

        $events = [];
        $agent->onUpdate(function (string $type, array $data) use (&$events) {
            $events[] = $type;
        });

        $description = 'Create a simple User class with name and email properties';

        $result = $agent->generateComponent($description);

        $this->assertContains('code.generating', $events);
        $this->assertContains('code.generated', $events);
        $this->assertContains('validation.started', $events);
        $this->assertTrue($result->isValid());
    }

    public function test_retries_on_validation_failure(): void
    {
        $validator = new ValidationCoordinator();
        $validator->addValidator(new PHPSyntaxValidator());

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $validator,
            'max_validation_retries' => 2,
        ]);

        $retryCount = 0;
        $agent->onRetry(function (int $attempt, array $errors) use (&$retryCount) {
            $retryCount++;
        });

        // Request something that might fail validation initially
        $description = 'Create a complex class with syntax that needs fixing';

        try {
            $result = $agent->generateComponent($description);
            // If it succeeds, that's good
            $this->assertTrue($result->isValid() || $retryCount > 0);
        } catch (MaxRetriesException $e) {
            // If it fails after retries, at least verify retries were attempted
            $this->assertGreaterThan(0, $retryCount);
        }
    }

    public function test_saves_generated_code_to_file(): void
    {
        $validator = new ValidationCoordinator();
        $validator->addValidator(new PHPSyntaxValidator());

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $validator,
        ]);

        $description = 'Create a Logger class with info and error methods';

        $result = $agent->generateComponent($description);

        $tempFile = sys_get_temp_dir() . '/test_logger_' . uniqid() . '.php';

        try {
            $saved = $result->saveToFile($tempFile);

            $this->assertTrue($saved);
            $this->assertFileExists($tempFile);

            $content = file_get_contents($tempFile);
            $this->assertEquals($result->getCode(), $content);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_generates_interface(): void
    {
        $validator = new ValidationCoordinator();
        $validator->addValidator(new PHPSyntaxValidator());

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $validator,
        ]);

        $description = 'Create a LoggerInterface with methods for debug, info, warning, and error';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('interface', $result->getCode());
        $this->assertStringContainsString('LoggerInterface', $result->getCode());
    }
}
