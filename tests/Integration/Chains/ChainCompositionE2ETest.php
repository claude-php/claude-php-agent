<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Chains;

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Chains\ParallelChain;
use ClaudeAgents\Chains\RouterChain;
use ClaudeAgents\Chains\SequentialChain;
use ClaudeAgents\Chains\TransformChain;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;

class ChainCompositionE2ETest extends TestCase
{
    private ClaudePhp $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Claude client
        $this->mockClient = Mockery::mock(ClaudePhp::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock Message response.
     */
    private function createMockMessage(string $text, int $inputTokens = 10, int $outputTokens = 5): Message
    {
        $usage = new Usage(
            input_tokens: $inputTokens,
            output_tokens: $outputTokens
        );

        return new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
            model: 'claude-3-5-sonnet-20241022',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );
    }

    /**
     * Setup mock for messages()->create() call.
     */
    private function mockMessagesCreate(string $responseText, int $inputTokens = 10, int $outputTokens = 5): void
    {
        $mockMessages = Mockery::mock(Messages::class);
        $mockMessages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockMessage($responseText, $inputTokens, $outputTokens));

        $this->mockClient->shouldReceive('messages')
            ->once()
            ->andReturn($mockMessages);
    }

    public function testLLMChainWithPromptTemplate(): void
    {
        $this->mockMessagesCreate('This is a test response');

        $template = PromptTemplate::create('Say hello to {name}');
        $chain = LLMChain::create($this->mockClient)
            ->withPromptTemplate($template)
            ->withModel('claude-3-5-sonnet-20241022');

        $result = $chain->invoke(['name' => 'World']);

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('This is a test response', $result['result']);
    }

    public function testLLMChainWithJsonParser(): void
    {
        $this->mockMessagesCreate('{"sentiment": "positive", "score": 0.95}');

        $chain = LLMChain::create($this->mockClient)
            ->withPromptTemplate(PromptTemplate::create('Analyze: {text}'))
            ->withJsonParser();

        $result = $chain->invoke(['text' => 'I love this product!']);

        $this->assertEquals('positive', $result['sentiment']);
        $this->assertEquals(0.95, $result['score']);
    }

    public function testComplexSequentialPipeline(): void
    {
        // Step 1: Transform input
        $normalize = TransformChain::create(fn (array $input): array => [
            'normalized' => strtolower($input['text'] ?? ''),
        ]);

        // Step 2: Mock LLM analysis
        $this->mockMessagesCreate('Analysis: positive');

        $analyze = LLMChain::create($this->mockClient)
            ->withPromptTemplate(PromptTemplate::create('Analyze sentiment: {normalized}'));

        // Step 3: Format result
        $format = TransformChain::create(fn (array $input): array => [
            'formatted' => 'Result: ' . ($input['result'] ?? 'unknown'),
        ]);

        $pipeline = SequentialChain::create()
            ->addChain('normalize', $normalize)
            ->addChain('analyze', $analyze)
            ->addChain('format', $format)
            ->mapOutput('normalize', 'normalized', 'analyze', 'normalized')
            ->mapOutput('analyze', 'result', 'format', 'result');

        $result = $pipeline->invoke(['text' => 'I LOVE THIS!']);

        $this->assertArrayHasKey('format', $result);
        $this->assertStringContainsString('positive', $result['format']['formatted']);
    }

    public function testParallelAnalysisWithDifferentStrategies(): void
    {
        // Create multiple analysis chains
        $sentiment = TransformChain::create(fn (array $input): array => [
            'sentiment' => 'positive',
        ]);

        $topics = TransformChain::create(fn (array $input): array => [
            'topics' => ['product', 'review'],
        ]);

        // Test merge strategy
        $parallel = ParallelChain::create()
            ->addChain('sentiment', $sentiment)
            ->addChain('topics', $topics)
            ->withAggregation('merge');

        $result = $parallel->invoke(['text' => 'Great product!']);

        $this->assertArrayHasKey('sentiment_sentiment', $result);
        $this->assertArrayHasKey('topics_topics', $result);
    }

    public function testRouterChainWithLLMChains(): void
    {
        // Code analysis chain
        $this->mockMessagesCreate('Code review: looks good');

        $codeChain = LLMChain::create($this->mockClient)
            ->withPromptTemplate(PromptTemplate::create('Review code: {content}'));

        // Text analysis chain
        $textChain = TransformChain::create(fn (array $input): array => [
            'analysis' => 'Text processed',
        ]);

        $router = RouterChain::create()
            ->addRoute(
                fn (array $input): bool => str_contains($input['content'] ?? '', '<?php'),
                $codeChain
            )
            ->addRoute(
                fn (array $input): bool => isset($input['type']) && $input['type'] === 'text',
                $textChain
            )
            ->setDefault($textChain);

        // Route to code chain
        $result1 = $router->invoke(['content' => '<?php echo "test";']);
        $this->assertStringContainsString('Code review', $result1['result'] ?? '');

        // Route to text chain
        $result2 = $router->invoke(['type' => 'text', 'content' => 'Some text']);
        $this->assertEquals('Text processed', $result2['analysis']);
    }

    public function testChainAsTool(): void
    {
        $this->mockMessagesCreate('{"summary": "test summary"}');

        $chain = LLMChain::create($this->mockClient)
            ->withPromptTemplate(PromptTemplate::create('Summarize: {text}'))
            ->withJsonParser();

        $tool = Tool::fromChain($chain, 'summarize', 'Summarizes text');

        $this->assertEquals('summarize', $tool->getName());
        $this->assertEquals('Summarizes text', $tool->getDescription());

        // Execute the tool
        $result = $tool->execute(['text' => 'Long text here']);

        $this->assertTrue($result->isSuccess());
        $output = json_decode($result->getContent(), true);
        $this->assertEquals('test summary', $output['summary']);
    }

    public function testNestedChainComposition(): void
    {
        // Inner parallel chain
        $inner1 = TransformChain::create(fn (array $input): array => ['a' => 1]);
        $inner2 = TransformChain::create(fn (array $input): array => ['b' => 2]);

        $innerParallel = ParallelChain::create()
            ->addChain('inner1', $inner1)
            ->addChain('inner2', $inner2)
            ->withAggregation('merge');

        // Outer sequential chain
        $preprocess = TransformChain::create(fn (array $input): array => [
            'processed' => true,
        ]);

        $postprocess = TransformChain::create(fn (array $input): array => [
            'final' => 'done',
        ]);

        $outer = SequentialChain::create()
            ->addChain('preprocess', $preprocess)
            ->addChain('parallel', $innerParallel)
            ->addChain('postprocess', $postprocess);

        $result = $outer->invoke(['data' => 'test']);

        $this->assertArrayHasKey('preprocess', $result);
        $this->assertArrayHasKey('parallel', $result);
        $this->assertArrayHasKey('postprocess', $result);
        $this->assertTrue($result['preprocess']['processed']);
        $this->assertEquals('done', $result['postprocess']['final']);
    }

    public function testChainCallbacks(): void
    {
        $beforeCalled = false;
        $afterCalled = false;
        $errorCalled = false;

        $chain = TransformChain::create(fn (array $input): array => ['result' => 'success'])
            ->onBefore(function (ChainInput $input) use (&$beforeCalled) {
                $beforeCalled = true;
            })
            ->onAfter(function (ChainInput $input, $output) use (&$afterCalled) {
                $afterCalled = true;
            })
            ->onError(function (ChainInput $input, \Throwable $error) use (&$errorCalled) {
                $errorCalled = true;
            });

        $chain->invoke(['test' => 'value']);

        $this->assertTrue($beforeCalled);
        $this->assertTrue($afterCalled);
        $this->assertFalse($errorCalled);
    }

    public function testChainErrorCallback(): void
    {
        $errorCalled = false;

        $chain = TransformChain::create(
            fn (array $input): array => throw new \RuntimeException('Test error')
        )
            ->onError(function (ChainInput $input, \Throwable $error) use (&$errorCalled) {
                $errorCalled = true;
            });

        try {
            $chain->invoke(['test' => 'value']);
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertTrue($errorCalled);
    }

    public function testMetadataTracking(): void
    {
        $this->mockMessagesCreate('Response', 100, 50);

        $chain = LLMChain::create($this->mockClient)
            ->withPromptTemplate(PromptTemplate::create('Test: {input}'));

        $input = ChainInput::create(['input' => 'test']);
        $output = $chain->run($input);

        $metadata = $output->getMetadata();
        $this->assertEquals(100, $metadata['input_tokens']);
        $this->assertEquals(50, $metadata['output_tokens']);
        $this->assertArrayHasKey('model', $metadata);
    }
}
