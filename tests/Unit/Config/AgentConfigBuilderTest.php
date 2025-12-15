<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Config;

use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Config\AgentConfigBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AgentConfigBuilderTest extends TestCase
{
    public function test_create_default_config(): void
    {
        $config = AgentConfigBuilder::create()->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_model(): void
    {
        $config = AgentConfigBuilder::create()
            ->withModel('claude-opus-4')
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_max_tokens(): void
    {
        $config = AgentConfigBuilder::create()
            ->withMaxTokens(4096)
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_max_iterations(): void
    {
        $config = AgentConfigBuilder::create()
            ->withMaxIterations(20)
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_system_prompt(): void
    {
        $config = AgentConfigBuilder::create()
            ->withSystemPrompt('You are helpful')
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_thinking(): void
    {
        $config = AgentConfigBuilder::create()
            ->withThinking(20000)
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_without_thinking(): void
    {
        $config = AgentConfigBuilder::create()
            ->withThinking()
            ->withoutThinking()
            ->build();

        $this->assertInstanceOf(AgentConfig::class, $config);
    }

    public function test_with_custom_option(): void
    {
        $array = AgentConfigBuilder::create()
            ->withOption('custom', 'value')
            ->toArray();

        $this->assertSame('value', $array['custom']);
    }

    public function test_with_multiple_options(): void
    {
        $array = AgentConfigBuilder::create()
            ->withOptions(['opt1' => 'val1', 'opt2' => 'val2'])
            ->toArray();

        $this->assertSame('val1', $array['opt1']);
        $this->assertSame('val2', $array['opt2']);
    }

    public function test_fluent_api_chaining(): void
    {
        $array = AgentConfigBuilder::create()
            ->withModel('claude-opus-4')
            ->withMaxTokens(4096)
            ->withMaxIterations(10)
            ->withSystemPrompt('Test')
            ->withThinking(15000)
            ->toArray();

        $this->assertSame('claude-opus-4', $array['model']);
        $this->assertSame(4096, $array['max_tokens']);
        $this->assertSame(10, $array['max_iterations']);
        $this->assertSame('Test', $array['system']);
    }

    public function test_to_array_includes_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $array = AgentConfigBuilder::create()
            ->withLogger($logger)
            ->toArray();

        $this->assertSame($logger, $array['logger']);
    }
}
