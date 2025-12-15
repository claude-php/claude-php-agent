<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Chains;

use ClaudeAgents\Chains\ChainOutput;
use PHPUnit\Framework\TestCase;

class ChainOutputTest extends TestCase
{
    public function testCreateWithData(): void
    {
        $output = ChainOutput::create(['result' => 'success']);

        $this->assertTrue($output->has('result'));
        $this->assertEquals('success', $output->get('result'));
    }

    public function testCreateWithMetadata(): void
    {
        $metadata = ['tokens' => 100, 'model' => 'claude-3-5-sonnet'];
        $output = ChainOutput::create(['result' => 'data'], $metadata);

        $this->assertEquals($metadata, $output->getMetadata());
        $this->assertEquals(100, $output->getMetadataValue('tokens'));
        $this->assertEquals('claude-3-5-sonnet', $output->getMetadataValue('model'));
    }

    public function testGetMetadataValueWithDefault(): void
    {
        $output = ChainOutput::create(['data' => 'value'], ['key' => 'value']);

        $this->assertEquals('value', $output->getMetadataValue('key'));
        $this->assertNull($output->getMetadataValue('missing'));
        $this->assertEquals('default', $output->getMetadataValue('missing', 'default'));
    }

    public function testGetDotNotation(): void
    {
        $output = ChainOutput::create([
            'result' => [
                'text' => 'Hello',
                'metadata' => [
                    'score' => 0.95,
                ],
            ],
        ]);

        $this->assertEquals('Hello', $output->getDot('result.text'));
        $this->assertEquals(0.95, $output->getDot('result.metadata.score'));
        $this->assertNull($output->getDot('result.missing'));
    }

    public function testToArray(): void
    {
        $data = ['result' => 'test'];
        $metadata = ['tokens' => 50];
        $output = ChainOutput::create($data, $metadata);

        $array = $output->toArray();

        $this->assertEquals($data, $array['data']);
        $this->assertEquals($metadata, $array['metadata']);
    }

    public function testGetAll(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $output = ChainOutput::create($data);

        $this->assertEquals($data, $output->all());
    }
}
