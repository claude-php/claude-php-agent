<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services\ErrorHandling;

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\APIConnectionError;
use ClaudePhp\Exceptions\APITimeoutError;
use ClaudePhp\Exceptions\AuthenticationError;
use ClaudePhp\Exceptions\BadRequestError;
use ClaudePhp\Exceptions\InternalServerError;
use ClaudePhp\Exceptions\OverloadedError;
use ClaudePhp\Exceptions\PermissionDeniedError;
use ClaudePhp\Exceptions\RateLimitError;
use ClaudePhp\Exceptions\UnprocessableEntityError;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ErrorHandlingServiceTest extends TestCase
{
    private ErrorHandlingService $service;

    protected function setUp(): void
    {
        $this->service = new ErrorHandlingService(
            logger: new NullLogger(),
            maxRetries: 3,
            initialDelayMs: 100
        );
    }

    public function testGetName(): void
    {
        $this->assertSame('error_handling', $this->service->getName());
    }

    public function testInitialize(): void
    {
        $this->assertFalse($this->service->isReady());

        $this->service->initialize();

        $this->assertTrue($this->service->isReady());
    }

    public function testTeardown(): void
    {
        $this->service->initialize();
        $this->assertTrue($this->service->isReady());

        $this->service->teardown();

        $this->assertFalse($this->service->isReady());
    }

    public function testGetSchema(): void
    {
        $this->service->initialize();
        $schema = $this->service->getSchema();

        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('ready', $schema);
        $this->assertArrayHasKey('methods', $schema);
        $this->assertArrayHasKey('convertToUserFriendly', $schema['methods']);
        $this->assertArrayHasKey('getErrorDetails', $schema['methods']);
        $this->assertArrayHasKey('executeWithRetry', $schema['methods']);
        $this->assertArrayHasKey('executeToolSafely', $schema['methods']);
    }

    public function testConvertRateLimitError(): void
    {
        $error = $this->createMockRateLimitError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Rate limit exceeded', $message);
    }

    public function testConvertAuthenticationError(): void
    {
        $error = $this->createMockAuthenticationError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Authentication failed', $message);
    }

    public function testConvertPermissionError(): void
    {
        $error = $this->createMockPermissionDeniedError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Permission denied', $message);
    }

    public function testConvertTimeoutError(): void
    {
        $error = $this->createMockAPITimeoutError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('timed out', $message);
    }

    public function testConvertConnectionError(): void
    {
        $error = new APIConnectionError('Connection failed');
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Connection error', $message);
    }

    public function testConvertOverloadedError(): void
    {
        $error = $this->createMockOverloadedError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('overloaded', $message);
    }

    public function testConvertBadRequestError(): void
    {
        $error = $this->createMockBadRequestError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Invalid request', $message);
    }

    public function testConvertServerError(): void
    {
        $error = $this->createMockInternalServerError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('Server error', $message);
    }

    public function testConvertValidationError(): void
    {
        $error = $this->createMockUnprocessableEntityError();
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('validation', $message);
    }

    public function testConvertUnknownError(): void
    {
        $error = new \RuntimeException('Unknown error');
        $message = $this->service->convertToUserFriendly($error);

        $this->assertStringContainsString('unexpected error', $message);
    }

    public function testGetErrorDetailsWithStandardException(): void
    {
        $error = new \RuntimeException('Test error', 500);
        $details = $this->service->getErrorDetails($error);

        $this->assertArrayHasKey('exception_class', $details);
        $this->assertArrayHasKey('message', $details);
        $this->assertArrayHasKey('code', $details);
        $this->assertArrayHasKey('file', $details);
        $this->assertArrayHasKey('line', $details);
        $this->assertArrayHasKey('user_friendly_message', $details);

        $this->assertSame(\RuntimeException::class, $details['exception_class']);
        $this->assertSame('Test error', $details['message']);
        $this->assertSame(500, $details['code']);
    }

    public function testGetErrorDetailsWithAPIStatusError(): void
    {
        $error = $this->createMockRateLimitError();
        $details = $this->service->getErrorDetails($error);

        $this->assertArrayHasKey('status_code', $details);
        $this->assertArrayHasKey('request_id', $details);
        $this->assertArrayHasKey('suggested_action', $details);

        $this->assertSame(429, $details['status_code']);
    }

    public function testCustomPatterns(): void
    {
        $service = new ErrorHandlingService(
            logger: new NullLogger(),
            customPatterns: [
                'custom_error' => [
                    'exception_class' => \InvalidArgumentException::class,
                    'user_message' => 'Custom error message',
                ],
            ]
        );

        $error = new \InvalidArgumentException('Invalid argument');
        $message = $service->convertToUserFriendly($error);

        $this->assertSame('Custom error message', $message);
    }

    public function testCustomPatternOverridesDefault(): void
    {
        $service = new ErrorHandlingService(
            logger: new NullLogger(),
            customPatterns: [
                'rate_limit' => [
                    'exception_class' => RateLimitError::class,
                    'user_message' => 'Custom rate limit message',
                ],
            ]
        );

        $error = $this->createMockRateLimitError();
        $message = $service->convertToUserFriendly($error);

        $this->assertSame('Custom rate limit message', $message);
    }

    public function testAddErrorPattern(): void
    {
        $this->service->addErrorPattern('new_pattern', [
            'exception_class' => \LogicException::class,
            'user_message' => 'Logic error occurred',
        ]);

        $error = new \LogicException('Logic error');
        $message = $this->service->convertToUserFriendly($error);

        $this->assertSame('Logic error occurred', $message);
    }

    public function testGetErrorPatterns(): void
    {
        $patterns = $this->service->getErrorPatterns();

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('rate_limit', $patterns);
        $this->assertArrayHasKey('authentication', $patterns);
        $this->assertArrayHasKey('connection', $patterns);
    }

    public function testExecuteToolSafelySuccess(): void
    {
        $toolFn = fn($input) => 'success';
        $result = $this->service->executeToolSafely($toolFn, 'test_tool', ['key' => 'value']);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['is_error']);
        $this->assertSame('success', $result['content']);
    }

    public function testExecuteToolSafelyFailure(): void
    {
        $toolFn = function ($input) {
            throw new \RuntimeException('Tool failed');
        };

        $result = $this->service->executeToolSafely($toolFn, 'test_tool', ['key' => 'value']);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['is_error']);
        $this->assertStringContainsString('Error:', $result['content']);
    }

    public function testExecuteToolWithFallbackSuccess(): void
    {
        $toolFn = fn($input) => 'success';
        $result = $this->service->executeToolWithFallback($toolFn, 'test_tool', ['key' => 'value']);

        $this->assertSame('success', $result);
    }

    public function testExecuteToolWithFallbackFailure(): void
    {
        $toolFn = function ($input) {
            throw new \RuntimeException('Tool failed');
        };

        $result = $this->service->executeToolWithFallback(
            $toolFn,
            'test_tool',
            ['key' => 'value'],
            'fallback value'
        );

        $this->assertSame('fallback value', $result);
    }

    public function testCreateRateLimiter(): void
    {
        $limiter = ErrorHandlingService::createRateLimiter(100);

        $this->assertIsCallable($limiter);

        // Call twice and measure time
        $start = microtime(true);
        $limiter();
        $limiter();
        $elapsed = (microtime(true) - $start) * 1000;

        // Should take at least 100ms between calls
        $this->assertGreaterThanOrEqual(90, $elapsed); // Allow small margin
    }

    // Helper methods to create mock exceptions

    private function createMockRateLimitError(): RateLimitError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new RateLimitError(
            status_code: 429,
            message: 'Rate limit exceeded',
            request: $request,
            response: $response,
            body: ['error' => 'rate limit exceeded'],
            request_id: 'test-request-id'
        );
    }

    private function createMockAuthenticationError(): AuthenticationError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new AuthenticationError(
            status_code: 401,
            message: 'Invalid API key',
            request: $request,
            response: $response
        );
    }

    private function createMockPermissionDeniedError(): PermissionDeniedError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new PermissionDeniedError(
            status_code: 403,
            message: 'Permission denied',
            request: $request,
            response: $response
        );
    }

    private function createMockAPITimeoutError(): APITimeoutError
    {
        return new APITimeoutError('Request timed out');
    }

    private function createMockOverloadedError(): OverloadedError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new OverloadedError(
            status_code: 529,
            message: 'Service overloaded',
            request: $request,
            response: $response
        );
    }

    private function createMockBadRequestError(): BadRequestError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new BadRequestError(
            status_code: 400,
            message: 'Bad request',
            request: $request,
            response: $response
        );
    }

    private function createMockInternalServerError(): InternalServerError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new InternalServerError(
            status_code: 500,
            message: 'Internal server error',
            request: $request,
            response: $response
        );
    }

    private function createMockUnprocessableEntityError(): UnprocessableEntityError
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        return new UnprocessableEntityError(
            status_code: 422,
            message: 'Validation failed',
            request: $request,
            response: $response
        );
    }
}
