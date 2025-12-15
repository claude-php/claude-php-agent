<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IntentClassifierAgentTest extends TestCase
{
    private ClaudePhp $client;
    private IntentClassifierAgent $agent;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new IntentClassifierAgent($this->client, ['name' => 'test_classifier']);
    }

    public function test_creates_agent_with_default_options(): void
    {
        $agent = new IntentClassifierAgent($this->client);

        $this->assertSame('intent_classifier', $agent->getName());
    }

    public function test_creates_agent_with_custom_options(): void
    {
        $logger = new NullLogger();
        $agent = new IntentClassifierAgent($this->client, [
            'name' => 'custom_classifier',
            'logger' => $logger,
            'confidence_threshold' => 0.8,
            'fallback_intent' => 'fallback',
        ]);

        $this->assertSame('custom_classifier', $agent->getName());
    }

    public function test_get_name(): void
    {
        $this->assertSame('test_classifier', $this->agent->getName());
    }

    public function test_add_intent_without_examples(): void
    {
        $this->agent->addIntent('greeting');

        $intents = $this->agent->getIntents();
        $this->assertArrayHasKey('greeting', $intents);
        $this->assertEmpty($intents['greeting']['examples']);
    }

    public function test_add_intent_with_examples(): void
    {
        $examples = ['Hello', 'Hi there', 'Good morning'];
        $this->agent->addIntent('greeting', $examples);

        $intents = $this->agent->getIntents();
        $this->assertArrayHasKey('greeting', $intents);
        $this->assertSame($examples, $intents['greeting']['examples']);
    }

    public function test_add_intent_with_description(): void
    {
        $this->agent->addIntent('greeting', ['Hello'], 'User greeting intent');

        $intents = $this->agent->getIntents();
        $this->assertSame('User greeting intent', $intents['greeting']['description']);
    }

    public function test_add_entity_type(): void
    {
        $this->agent->addEntityType('product_name', 'Name of a product');

        $entityTypes = $this->agent->getEntityTypes();
        $this->assertArrayHasKey('product_name', $entityTypes);
        $this->assertSame('Name of a product', $entityTypes['product_name']);
    }

    public function test_add_entity_type_without_description(): void
    {
        $this->agent->addEntityType('order_id');

        $entityTypes = $this->agent->getEntityTypes();
        $this->assertArrayHasKey('order_id', $entityTypes);
        $this->assertNull($entityTypes['order_id']);
    }

    public function test_remove_intent(): void
    {
        $this->agent->addIntent('greeting', ['Hello']);
        $this->agent->addIntent('goodbye', ['Bye']);

        $this->assertCount(2, $this->agent->getIntents());

        $this->agent->removeIntent('greeting');

        $intents = $this->agent->getIntents();
        $this->assertCount(1, $intents);
        $this->assertArrayNotHasKey('greeting', $intents);
        $this->assertArrayHasKey('goodbye', $intents);
    }

    public function test_get_intents_returns_empty_by_default(): void
    {
        $agent = new IntentClassifierAgent($this->client);

        $this->assertIsArray($agent->getIntents());
        $this->assertEmpty($agent->getIntents());
    }

    public function test_get_entity_types_returns_empty_by_default(): void
    {
        $agent = new IntentClassifierAgent($this->client);

        $this->assertIsArray($agent->getEntityTypes());
        $this->assertEmpty($agent->getEntityTypes());
    }

    public function test_run_classifies_intent_successfully(): void
    {
        $this->agent->addIntent('greeting', ['Hello', 'Hi']);

        $this->mockLlmResponse([
            'intent' => 'greeting',
            'confidence' => 0.95,
            'entities' => [],
        ]);

        $result = $this->agent->run('Hello there!');

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertSame('greeting', $metadata['intent']);
        $this->assertSame(0.95, $metadata['confidence']);
    }

    public function test_run_extracts_entities(): void
    {
        $this->agent->addIntent('book_flight', ['Book a flight', 'I want to fly']);
        $this->agent->addEntityType('destination', 'Destination city');
        $this->agent->addEntityType('date', 'Travel date');

        $this->mockLlmResponse([
            'intent' => 'book_flight',
            'confidence' => 0.92,
            'entities' => [
                ['type' => 'destination', 'value' => 'Paris'],
                ['type' => 'date', 'value' => '2024-03-15'],
            ],
        ]);

        $result = $this->agent->run('I want to fly to Paris on March 15th');

        $metadata = $result->getMetadata();
        $this->assertSame('book_flight', $metadata['intent']);
        $this->assertCount(2, $metadata['entities']);
        $this->assertSame('Paris', $metadata['entities'][0]['value']);
        $this->assertSame('2024-03-15', $metadata['entities'][1]['value']);
    }

    public function test_run_applies_confidence_threshold(): void
    {
        $agent = new IntentClassifierAgent($this->client, [
            'confidence_threshold' => 0.8,
            'fallback_intent' => 'unclear',
        ]);
        $agent->addIntent('greeting', ['Hello']);

        $this->mockLlmResponse([
            'intent' => 'greeting',
            'confidence' => 0.6,  // Below threshold
            'entities' => [],
        ]);

        $result = $agent->run('Hmm...');

        $metadata = $result->getMetadata();
        $this->assertSame('unclear', $metadata['intent']);
    }

    public function test_run_uses_fallback_on_low_confidence(): void
    {
        $agent = new IntentClassifierAgent($this->client, [
            'confidence_threshold' => 0.7,
            'fallback_intent' => 'unknown',
        ]);

        $this->mockLlmResponse([
            'intent' => 'some_intent',
            'confidence' => 0.4,
            'entities' => [],
        ]);

        $result = $agent->run('asdfghjkl');

        $metadata = $result->getMetadata();
        $this->assertSame('unknown', $metadata['intent']);
        $this->assertArrayHasKey('original_intent', $metadata);
    }

    public function test_run_handles_json_parsing_error(): void
    {
        $this->mockLlmInvalidResponse('This is not JSON');

        $result = $this->agent->run('Test input');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertSame('unknown', $metadata['intent']);
        $this->assertSame(0.0, $metadata['confidence']);
    }

    public function test_run_handles_exception(): void
    {
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('API Error'));
        $this->client->method('messages')->willReturn($messages);

        $result = $this->agent->run('Test input');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function test_run_returns_json_in_answer(): void
    {
        $this->mockLlmResponse([
            'intent' => 'greeting',
            'confidence' => 0.95,
            'entities' => [],
        ]);

        $result = $this->agent->run('Hello');

        $answer = $result->getAnswer();
        $this->assertJson($answer);

        $decoded = json_decode($answer, true);
        $this->assertSame('greeting', $decoded['intent']);
    }

    public function test_run_with_multiple_intents(): void
    {
        $this->agent->addIntent('greeting', ['Hello', 'Hi']);
        $this->agent->addIntent('goodbye', ['Bye', 'Goodbye']);
        $this->agent->addIntent('help', ['Help me', 'I need help']);

        $this->mockLlmResponse([
            'intent' => 'help',
            'confidence' => 0.88,
            'entities' => [['type' => 'topic', 'value' => 'account']],
        ]);

        $result = $this->agent->run('I need help with my account');

        $metadata = $result->getMetadata();
        $this->assertSame('help', $metadata['intent']);
        $this->assertCount(1, $metadata['entities']);
    }

    public function test_run_strips_markdown_code_blocks(): void
    {
        // Some LLMs might wrap JSON in markdown
        $this->mockLlmInvalidResponse('```json
{
  "intent": "greeting",
  "confidence": 0.95,
  "entities": []
}
```');

        $result = $this->agent->run('Hello');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertSame('greeting', $metadata['intent']);
    }

    public function test_run_handles_missing_intent_field(): void
    {
        $this->mockLlmResponse([
            'confidence' => 0.8,
            'entities' => [],
            // Missing 'intent' field
        ]);

        $result = $this->agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertSame('unknown', $metadata['intent']);
    }

    public function test_run_handles_empty_entities_array(): void
    {
        $this->mockLlmResponse([
            'intent' => 'greeting',
            'confidence' => 0.9,
            'entities' => [],
        ]);

        $result = $this->agent->run('Hello');

        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata['entities']);
        $this->assertEmpty($metadata['entities']);
    }

    public function test_run_with_initial_intents_option(): void
    {
        $agent = new IntentClassifierAgent($this->client, [
            'intents' => [
                'greeting' => [
                    'examples' => ['Hello', 'Hi'],
                    'description' => 'User greeting',
                ],
            ],
        ]);

        $intents = $agent->getIntents();
        $this->assertCount(1, $intents);
        $this->assertArrayHasKey('greeting', $intents);
    }

    public function test_run_with_initial_entity_types_option(): void
    {
        $agent = new IntentClassifierAgent($this->client, [
            'entity_types' => [
                'product_name' => 'Name of product',
                'quantity' => 'Number of items',
            ],
        ]);

        $entityTypes = $agent->getEntityTypes();
        $this->assertCount(2, $entityTypes);
        $this->assertArrayHasKey('product_name', $entityTypes);
        $this->assertArrayHasKey('quantity', $entityTypes);
    }

    public function test_classification_result_includes_iterations(): void
    {
        $this->mockLlmResponse([
            'intent' => 'test',
            'confidence' => 0.9,
            'entities' => [],
        ]);

        $result = $this->agent->run('Test');

        $this->assertSame(1, $result->getIterations());
    }

    public function test_confidence_is_float(): void
    {
        $this->mockLlmResponse([
            'intent' => 'test',
            'confidence' => '0.85',  // String that should be converted
            'entities' => [],
        ]);

        $result = $this->agent->run('Test');

        $metadata = $result->getMetadata();
        $this->assertIsFloat($metadata['confidence']);
        $this->assertSame(0.85, $metadata['confidence']);
    }

    private function mockLlmResponse(array $classification): void
    {
        $json = json_encode($classification);

        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $json],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);
    }

    private function mockLlmInvalidResponse(string $text): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 50
        );

        $response = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => $text],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willReturn($response);
        $this->client->method('messages')->willReturn($messages);
    }
}
