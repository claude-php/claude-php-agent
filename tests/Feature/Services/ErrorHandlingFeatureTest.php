<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature\Services;

use ClaudeAgents\Agent;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Feature tests for ErrorHandlingService integration with agents.
 */
class ErrorHandlingFeatureTest extends TestCase
{
    private ErrorHandlingService $errorService;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->apiKey = getenv('ANTHROPIC_API_KEY') ?: 'test-api-key';

        $this->errorService = new ErrorHandlingService(
            logger: new NullLogger(),
            maxRetries: 2,
            initialDelayMs: 100
        );
        $this->errorService->initialize();
    }

    public function testErrorHandlingInToolExecution(): void
    {
        $tool = Tool::create('failing_tool')
            ->description('A tool that fails')
            ->handler(function () {
                throw new \RuntimeException('Tool execution failed');
            });

        $result = $this->errorService->executeToolSafely(
            fn($input) => $tool->getHandler()($input),
            'failing_tool',
            []
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['is_error']);
        $this->assertStringContainsString('Error:', $result['content']);
    }

    public function testErrorHandlingWithSuccessfulTool(): void
    {
        $tool = Tool::create('successful_tool')
            ->description('A tool that succeeds')
            ->handler(function () {
                return 'Success!';
            });

        $result = $this->errorService->executeToolSafely(
            fn($input) => $tool->getHandler()($input),
            'successful_tool',
            []
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_error']);
        $this->assertSame('Success!', $result['content']);
    }

    public function testServiceManagerProvision(): void
    {
        $manager = ServiceManager::getInstance();
        
        $service = $manager->get(ServiceType::ERROR_HANDLING);

        $this->assertInstanceOf(ErrorHandlingService::class, $service);
        $this->assertTrue($service->isReady());
        $this->assertSame('error_handling', $service->getName());
    }

    public function testConfigurationLoading(): void
    {
        $manager = ServiceManager::getInstance();
        $service = $manager->get(ServiceType::ERROR_HANDLING);

        $patterns = $service->getErrorPatterns();

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('rate_limit', $patterns);
        $this->assertArrayHasKey('authentication', $patterns);
        $this->assertArrayHasKey('connection', $patterns);
        $this->assertArrayHasKey('timeout', $patterns);
    }

    public function testErrorRecoveryWorkflow(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $result = null;
        $lastError = null;

        while ($attempts < $maxAttempts && $result === null) {
            $attempts++;

            try {
                $client = new ClaudePhp(apiKey: $this->apiKey);
                
                $result = $this->errorService->executeWithRetry(
                    fn() => $client->messages()->create([
                        'model' => 'claude-sonnet-4',
                        'max_tokens' => 10,
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hi'],
                        ],
                    ]),
                    'Test recovery workflow'
                );
                
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
                $userMessage = $this->errorService->convertToUserFriendly($e);
                
                // Verify we got a user-friendly message
                $this->assertNotEmpty($userMessage);
            }
        }

        // Either we succeeded or got a handled error
        $this->assertTrue($result !== null || $lastError !== null);
    }

    public function testCustomPatternUsage(): void
    {
        $customService = new ErrorHandlingService(
            logger: new NullLogger(),
            maxRetries: 2,
            initialDelayMs: 100,
            customPatterns: [
                'custom_app_error' => [
                    'exception_class' => \LogicException::class,
                    'user_message' => 'Application logic error occurred',
                    'suggested_action' => 'Please contact support',
                ],
            ]
        );
        $customService->initialize();

        $error = new \LogicException('Logic error');
        $message = $customService->convertToUserFriendly($error);

        $this->assertSame('Application logic error occurred', $message);
    }

    public function testEndToEndErrorHandling(): void
    {
        // Test complete workflow: API call -> error -> conversion -> logging
        $client = new ClaudePhp(apiKey: 'invalid_key');

        $caughtError = null;
        $userMessage = null;
        $errorDetails = null;

        try {
            $this->errorService->executeWithRetry(
                fn() => $client->messages()->create([
                    'model' => 'claude-sonnet-4',
                    'max_tokens' => 100,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Test'],
                    ],
                ]),
                'End-to-end test'
            );
        } catch (\Throwable $e) {
            $caughtError = $e;
            $userMessage = $this->errorService->convertToUserFriendly($e);
            $errorDetails = $this->errorService->getErrorDetails($e);
        }

        // Verify all steps worked
        $this->assertNotNull($caughtError);
        $this->assertNotNull($userMessage);
        $this->assertNotNull($errorDetails);

        $this->assertIsString($userMessage);
        $this->assertIsArray($errorDetails);

        $this->assertArrayHasKey('exception_class', $errorDetails);
        $this->assertArrayHasKey('user_friendly_message', $errorDetails);
        $this->assertSame($userMessage, $errorDetails['user_friendly_message']);
    }
}
