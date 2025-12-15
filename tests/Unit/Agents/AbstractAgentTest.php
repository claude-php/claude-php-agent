<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\AbstractAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AbstractAgentTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function test_constructor_sets_default_values(): void
    {
        $agent = new ConcreteTestAgent($this->client);

        $this->assertSame('test_agent', $agent->getName());
    }

    public function test_constructor_accepts_custom_name(): void
    {
        $agent = new ConcreteTestAgent($this->client, ['name' => 'custom_agent']);

        $this->assertSame('custom_agent', $agent->getName());
    }

    public function test_constructor_accepts_custom_model(): void
    {
        $agent = new ConcreteTestAgent($this->client, ['model' => 'claude-opus-4']);

        $this->assertSame('claude-opus-4', $agent->getModel());
    }

    public function test_constructor_accepts_custom_max_tokens(): void
    {
        $agent = new ConcreteTestAgent($this->client, ['max_tokens' => 4096]);

        $this->assertSame(4096, $agent->getMaxTokens());
    }

    public function test_constructor_accepts_custom_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $agent = new ConcreteTestAgent($this->client, ['logger' => $logger]);

        $this->assertSame($logger, $agent->getLogger());
    }

    public function test_initialize_is_called_with_options(): void
    {
        $agent = new ConcreteTestAgent($this->client, ['custom_option' => 'value']);

        $this->assertSame('value', $agent->getCustomOption());
    }

    public function test_extract_text_content_from_array(): void
    {
        $agent = new ConcreteTestAgent($this->client);

        $content = [
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'text', 'text' => 'World'],
        ];

        $result = $agent->publicExtractTextContent($content);

        $this->assertSame("Hello\nWorld", $result);
    }

    public function test_extract_text_content_from_response_object(): void
    {
        $agent = new ConcreteTestAgent($this->client);

        $response = (object) [
            'content' => [
                ['type' => 'text', 'text' => 'Test'],
            ],
        ];

        $result = $agent->publicExtractTextContent($response);

        $this->assertSame('Test', $result);
    }

    public function test_format_token_usage(): void
    {
        $agent = new ConcreteTestAgent($this->client);

        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $result = $agent->publicFormatTokenUsage($response);

        $this->assertSame([
            'input' => 100,
            'output' => 50,
            'total' => 150,
        ], $result);
    }

    public function test_format_simple_token_usage(): void
    {
        $agent = new ConcreteTestAgent($this->client);

        $response = (object) [
            'usage' => (object) [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
        ];

        $result = $agent->publicFormatSimpleTokenUsage($response);

        $this->assertSame([
            'input' => 100,
            'output' => 50,
        ], $result);
    }

    public function test_log_start_is_called(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Starting test_agent'),
                $this->arrayHasKey('task')
            );

        $agent = new ConcreteTestAgent($this->client, ['logger' => $logger]);
        $agent->publicLogStart('Test task');
    }

    public function test_log_success_is_called(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('completed successfully'),
                []
            );

        $agent = new ConcreteTestAgent($this->client, ['logger' => $logger]);
        $agent->publicLogSuccess();
    }

    public function test_log_error_is_called(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('failed: Error message'),
                []
            );

        $agent = new ConcreteTestAgent($this->client, ['logger' => $logger]);
        $agent->publicLogError('Error message');
    }

    public function test_log_debug_is_called(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->stringContains('[test_agent] Debug message'),
                []
            );

        $agent = new ConcreteTestAgent($this->client, ['logger' => $logger]);
        $agent->publicLogDebug('Debug message');
    }
}

/**
 * Concrete implementation of AbstractAgent for testing.
 */
class ConcreteTestAgent extends AbstractAgent
{
    protected const DEFAULT_NAME = 'test_agent';

    private ?string $customOption = null;

    protected function initialize(array $options): void
    {
        $this->customOption = $options['custom_option'] ?? null;
    }

    public function run(string $task): AgentResult
    {
        return AgentResult::success(
            answer: 'Test result',
            messages: [],
            iterations: 1
        );
    }

    // Expose protected methods for testing
    public function publicExtractTextContent(array|object $content): string
    {
        return $this->extractTextContent($content);
    }

    public function publicFormatTokenUsage(object|array $response): array
    {
        return $this->formatTokenUsage($response);
    }

    public function publicFormatSimpleTokenUsage(object|array $response): array
    {
        return $this->formatSimpleTokenUsage($response);
    }

    public function publicLogStart(string $task, array $context = []): void
    {
        $this->logStart($task, $context);
    }

    public function publicLogSuccess(array $context = []): void
    {
        $this->logSuccess($context);
    }

    public function publicLogError(string $message, array $context = []): void
    {
        $this->logError($message, $context);
    }

    public function publicLogDebug(string $message, array $context = []): void
    {
        $this->logDebug($message, $context);
    }

    // Expose protected properties for testing
    public function getModel(): string
    {
        return $this->model;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getCustomOption(): ?string
    {
        return $this->customOption;
    }
}
