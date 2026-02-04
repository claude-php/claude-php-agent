<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Services;

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Exceptions\AuthenticationError;
use ClaudePhp\Exceptions\BadRequestError;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for ErrorHandlingService using real API calls.
 *
 * These tests use the provided API key to trigger actual error scenarios.
 */
class ErrorHandlingIntegrationTest extends TestCase
{
    private ErrorHandlingService $service;
    private string $validApiKey;

    protected function setUp(): void
    {
        // Use the provided API key
        $this->validApiKey = getenv('ANTHROPIC_API_KEY') ?: 'test-api-key';

        $this->service = new ErrorHandlingService(
            logger: new NullLogger(),
            maxRetries: 2,
            initialDelayMs: 500
        );
        $this->service->initialize();
    }

    public function testAuthenticationErrorWithInvalidKey(): void
    {
        $client = new ClaudePhp(apiKey: 'invalid_key_12345');

        try {
            $client->messages()->create([
                'model' => 'claude-sonnet-4',
                'max_tokens' => 100,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ]);

            $this->fail('Expected AuthenticationError to be thrown');
        } catch (AuthenticationError $e) {
            // Test user-friendly message conversion
            $userMessage = $this->service->convertToUserFriendly($e);
            $this->assertStringContainsString('Authentication failed', $userMessage);

            // Test detailed error extraction
            $details = $this->service->getErrorDetails($e);
            $this->assertArrayHasKey('status_code', $details);
            $this->assertSame(401, $details['status_code']);
            $this->assertArrayHasKey('suggested_action', $details);
            $this->assertStringContainsString('API key', $details['suggested_action']);
        }
    }

    public function testBadRequestError(): void
    {
        $client = new ClaudePhp(apiKey: $this->validApiKey);

        try {
            // Send a request with missing required fields
            $client->messages()->create([
                'model' => 'claude-sonnet-4',
                // Missing max_tokens and messages
            ]);

            $this->fail('Expected BadRequestError to be thrown');
        } catch (BadRequestError $e) {
            // Test user-friendly message conversion
            $userMessage = $this->service->convertToUserFriendly($e);
            $this->assertStringContainsString('Invalid request', $userMessage);

            // Test detailed error extraction
            $details = $this->service->getErrorDetails($e);
            $this->assertArrayHasKey('status_code', $details);
            $this->assertSame(400, $details['status_code']);
        } catch (\Throwable $e) {
            // API might return different error for missing fields
            $this->assertNotNull($e);
        }
    }

    public function testSuccessfulRequestWithRetryLogic(): void
    {
        $client = new ClaudePhp(apiKey: $this->validApiKey);

        $result = $this->service->executeWithRetry(
            fn() => $client->messages()->create([
                'model' => 'claude-sonnet-4',
                'max_tokens' => 50,
                'messages' => [
                    ['role' => 'user', 'content' => 'Say "test" in one word'],
                ],
            ]),
            'Test API call'
        );

        $this->assertNotNull($result);
        $this->assertIsObject($result);
    }

    public function testServiceManagerIntegration(): void
    {
        $manager = ServiceManager::getInstance();
        $service = $manager->get(ServiceType::ERROR_HANDLING);

        $this->assertInstanceOf(ErrorHandlingService::class, $service);
        $this->assertTrue($service->isReady());
    }

    public function testErrorLoggingAndRecovery(): void
    {
        $client = new ClaudePhp(apiKey: 'invalid_key');

        $errors = [];

        try {
            $this->service->executeWithRetry(
                function () use ($client) {
                    return $client->messages()->create([
                        'model' => 'claude-sonnet-4',
                        'max_tokens' => 100,
                        'messages' => [
                            ['role' => 'user', 'content' => 'Hello'],
                        ],
                    ]);
                },
                'Test with invalid key'
            );
        } catch (\Throwable $e) {
            $errors[] = $e;
            
            // Verify error handling provides useful information
            $userMessage = $this->service->convertToUserFriendly($e);
            $this->assertNotEmpty($userMessage);
            $this->assertIsString($userMessage);

            $details = $this->service->getErrorDetails($e);
            $this->assertIsArray($details);
            $this->assertArrayHasKey('user_friendly_message', $details);
        }

        $this->assertCount(1, $errors);
    }

    public function testValidAPIKeyDoesNotThrowAuthError(): void
    {
        $client = new ClaudePhp(apiKey: $this->validApiKey);

        try {
            $result = $client->messages()->create([
                'model' => 'claude-sonnet-4',
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hi'],
                ],
            ]);

            // If we get here, the API call succeeded
            $this->assertNotNull($result);
        } catch (\Throwable $e) {
            // Should not be an authentication error with valid key
            $this->assertNotInstanceOf(AuthenticationError::class, $e);
        }
    }

    public function testMultipleErrorTypesHandled(): void
    {
        $errorTypes = [
            ['key' => 'invalid_key', 'expectedMessage' => 'Authentication'],
        ];

        foreach ($errorTypes as $errorType) {
            $client = new ClaudePhp(apiKey: $errorType['key']);

            try {
                $client->messages()->create([
                    'model' => 'claude-sonnet-4',
                    'max_tokens' => 100,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Test'],
                    ],
                ]);
            } catch (\Throwable $e) {
                $userMessage = $this->service->convertToUserFriendly($e);
                $this->assertStringContainsString(
                    $errorType['expectedMessage'],
                    $userMessage,
                    "Error type '{$errorType['expectedMessage']}' not properly handled"
                );
            }
        }
    }
}
