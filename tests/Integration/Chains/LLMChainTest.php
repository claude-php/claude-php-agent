<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Chains;

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class LLMChainTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
        if (empty($apiKey)) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
    }

    public function testSimpleLLMCall(): void
    {
        $chain = LLMChain::create($this->client)
            ->withModel('claude-sonnet-4-5')
            ->withMaxTokens(256);

        $result = $chain->invoke(['prompt' => 'What is 2+2? Answer with just the number.']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertIsString($result['result']);
        $this->assertStringContainsString('4', $result['result']);
    }

    public function testWithPromptTemplate(): void
    {
        $template = PromptTemplate::create('Count from 1 to {count}. Output only the numbers separated by commas.');

        $chain = LLMChain::create($this->client)
            ->withPromptTemplate($template)
            ->withMaxTokens(100);

        $result = $chain->invoke(['count' => '3']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertStringContainsString('1', $result['result']);
        $this->assertStringContainsString('2', $result['result']);
        $this->assertStringContainsString('3', $result['result']);
    }

    public function testWithMetadata(): void
    {
        $chain = LLMChain::create($this->client)
            ->withModel('claude-sonnet-4-5')
            ->withMaxTokens(50);

        $input = ChainInput::create(['prompt' => 'Say hello']);
        $output = $chain->run($input);

        $metadata = $output->getMetadata();
        $this->assertArrayHasKey('model', $metadata);
        $this->assertEquals('claude-sonnet-4-5', $metadata['model']);
        $this->assertArrayHasKey('input_tokens', $metadata);
        $this->assertArrayHasKey('output_tokens', $metadata);
        $this->assertGreaterThan(0, $metadata['input_tokens']);
        $this->assertGreaterThan(0, $metadata['output_tokens']);
    }

    public function testWithTemperature(): void
    {
        $chain = LLMChain::create($this->client)
            ->withModel('claude-sonnet-4-5')
            ->withMaxTokens(50)
            ->withTemperature(0.5);

        $result = $chain->invoke(['prompt' => 'Say hi']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
    }

    public function testCallbacks(): void
    {
        $beforeCalled = false;
        $afterCalled = false;

        $chain = LLMChain::create($this->client)
            ->withMaxTokens(50)
            ->onBefore(function ($input) use (&$beforeCalled) {
                $beforeCalled = true;
            })
            ->onAfter(function ($input, $output) use (&$afterCalled) {
                $afterCalled = true;
            });

        $chain->invoke(['prompt' => 'Say hello']);

        $this->assertTrue($beforeCalled);
        $this->assertTrue($afterCalled);
    }

    public function testInvalidTemplate(): void
    {
        $template = PromptTemplate::create('Hello {name}');

        $chain = LLMChain::create($this->client)
            ->withPromptTemplate($template)
            ->withMaxTokens(50);

        $this->expectException(ChainExecutionException::class);

        // Missing required template variable 'name'
        $chain->invoke(['prompt' => 'test']);
    }

    public function testJsonParserWithValidJson(): void
    {
        $template = PromptTemplate::create(
            'Return a JSON object with keys "name" and "age" for a person named John who is 30. Return ONLY the JSON object, no other text.'
        );

        $chain = LLMChain::create($this->client)
            ->withPromptTemplate($template)
            ->withJsonParser()
            ->withMaxTokens(100);

        $result = $chain->invoke([]);

        $this->assertIsArray($result);
        // The JSON parser should extract the JSON object
        $this->assertTrue(
            isset($result['name']) || isset($result[0]['name']) || isset($result['result']),
            'Expected parsed JSON result'
        );
    }

    public function testMultipleInvocations(): void
    {
        $chain = LLMChain::create($this->client)
            ->withModel('claude-sonnet-4-5')
            ->withMaxTokens(50);

        $result1 = $chain->invoke(['prompt' => 'Say hello']);
        $result2 = $chain->invoke(['prompt' => 'Say goodbye']);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('result', $result1);
        $this->assertArrayHasKey('result', $result2);
    }
}
