<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\ValidationResult;
use ClaudeAgents\Validation\Exceptions\MaxRetriesException;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Mockery;

class CodeGenerationAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_agent_with_defaults(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client);

        $this->assertInstanceOf(CodeGenerationAgent::class, $agent);
        $this->assertInstanceOf(ValidationCoordinator::class, $agent->getValidationCoordinator());
    }

    public function test_accepts_custom_validation_coordinator(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $coordinator = new ValidationCoordinator();

        $agent = new CodeGenerationAgent($client, [
            'validation_coordinator' => $coordinator,
        ]);

        $this->assertSame($coordinator, $agent->getValidationCoordinator());
    }

    public function test_accepts_max_retries_option(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        
        $agent = new CodeGenerationAgent($client, [
            'max_validation_retries' => 5,
        ]);

        $this->assertInstanceOf(CodeGenerationAgent::class, $agent);
    }

    public function test_returns_default_name(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client);

        $this->assertEquals('code_generation_agent', $agent->getName());
    }

    public function test_accepts_custom_name(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client, [
            'name' => 'custom_generator',
        ]);

        $this->assertEquals('custom_generator', $agent->getName());
    }

    public function test_allows_setting_update_callback(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client);

        $callbackCalled = false;
        $callback = function () use (&$callbackCalled) {
            $callbackCalled = true;
        };

        $result = $agent->onUpdate($callback);

        $this->assertSame($agent, $result); // Fluent interface
        // Callback will be tested in integration tests
    }

    public function test_allows_setting_validation_callback(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client);

        $callback = fn ($result, $attempt) => null;
        $result = $agent->onValidation($callback);

        $this->assertSame($agent, $result);
    }

    public function test_allows_setting_retry_callback(): void
    {
        $client = Mockery::mock(ClaudePhp::class);
        $agent = new CodeGenerationAgent($client);

        $callback = fn ($attempt, $errors) => null;
        $result = $agent->onRetry($callback);

        $this->assertSame($agent, $result);
    }
}
