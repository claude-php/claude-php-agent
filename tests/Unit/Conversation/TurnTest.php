<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class TurnTest extends TestCase
{
    public function test_creates_turn_with_required_parameters(): void
    {
        $turn = new Turn('User input', 'Agent response');

        $this->assertSame('User input', $turn->getUserInput());
        $this->assertSame('Agent response', $turn->getAgentResponse());
    }

    public function test_generates_unique_id(): void
    {
        $turn1 = new Turn('Input 1', 'Response 1');
        $turn2 = new Turn('Input 2', 'Response 2');

        $this->assertNotEmpty($turn1->getId());
        $this->assertNotEmpty($turn2->getId());
        $this->assertNotSame($turn1->getId(), $turn2->getId());
        $this->assertStringStartsWith('turn_', $turn1->getId());
    }

    public function test_get_id(): void
    {
        $turn = new Turn('Input', 'Response');

        $id = $turn->getId();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    public function test_get_user_input(): void
    {
        $input = 'What is the weather today?';
        $turn = new Turn($input, 'Response');

        $this->assertSame($input, $turn->getUserInput());
    }

    public function test_get_agent_response(): void
    {
        $response = 'The weather is sunny today.';
        $turn = new Turn('Input', $response);

        $this->assertSame($response, $turn->getAgentResponse());
    }

    public function test_creates_turn_with_empty_metadata_by_default(): void
    {
        $turn = new Turn('Input', 'Response');

        $this->assertSame([], $turn->getMetadata());
    }

    public function test_creates_turn_with_custom_metadata(): void
    {
        $metadata = ['model' => 'claude-3', 'tokens' => 150];
        $turn = new Turn('Input', 'Response', $metadata);

        $this->assertSame($metadata, $turn->getMetadata());
    }

    public function test_get_metadata(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $turn = new Turn('Input', 'Response', $metadata);

        $retrievedMetadata = $turn->getMetadata();

        $this->assertSame($metadata, $retrievedMetadata);
    }

    public function test_get_timestamp(): void
    {
        $beforeCreation = microtime(true);
        $turn = new Turn('Input', 'Response');
        $afterCreation = microtime(true);

        $timestamp = $turn->getTimestamp();

        $this->assertGreaterThanOrEqual($beforeCreation, $timestamp);
        $this->assertLessThanOrEqual($afterCreation, $timestamp);
    }

    public function test_to_array_contains_all_fields(): void
    {
        $metadata = ['extra' => 'data'];
        $turn = new Turn('Input text', 'Response text', $metadata);

        $array = $turn->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('user_input', $array);
        $this->assertArrayHasKey('agent_response', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_to_array_has_correct_values(): void
    {
        $userInput = 'Test input';
        $agentResponse = 'Test response';
        $metadata = ['key' => 'value'];

        $turn = new Turn($userInput, $agentResponse, $metadata);
        $array = $turn->toArray();

        $this->assertSame($turn->getId(), $array['id']);
        $this->assertSame($userInput, $array['user_input']);
        $this->assertSame($agentResponse, $array['agent_response']);
        $this->assertSame($metadata, $array['metadata']);
        $this->assertSame($turn->getTimestamp(), $array['timestamp']);
    }

    public function test_turns_have_sequential_timestamps(): void
    {
        $turn1 = new Turn('First', 'Response 1');
        usleep(1000); // Small delay
        $turn2 = new Turn('Second', 'Response 2');

        $this->assertLessThan($turn2->getTimestamp(), $turn1->getTimestamp());
    }

    public function test_metadata_can_store_complex_data(): void
    {
        $metadata = [
            'model_info' => ['name' => 'claude-3', 'version' => '1.0'],
            'tokens' => ['input' => 100, 'output' => 50],
            'flags' => [true, false, true],
        ];

        $turn = new Turn('Input', 'Response', $metadata);

        $this->assertSame($metadata, $turn->getMetadata());
    }

    public function test_handles_empty_strings(): void
    {
        $turn = new Turn('', '');

        $this->assertSame('', $turn->getUserInput());
        $this->assertSame('', $turn->getAgentResponse());
    }

    public function test_handles_multiline_text(): void
    {
        $multilineInput = "Line 1\nLine 2\nLine 3";
        $multilineResponse = "Response line 1\nResponse line 2";

        $turn = new Turn($multilineInput, $multilineResponse);

        $this->assertSame($multilineInput, $turn->getUserInput());
        $this->assertSame($multilineResponse, $turn->getAgentResponse());
    }

    public function test_handles_special_characters(): void
    {
        $specialInput = 'Test with émojis 🎉 and spëcial çhars!';
        $specialResponse = 'Response with symbols: @#$%^&*()';

        $turn = new Turn($specialInput, $specialResponse);

        $this->assertSame($specialInput, $turn->getUserInput());
        $this->assertSame($specialResponse, $turn->getAgentResponse());
    }
}
