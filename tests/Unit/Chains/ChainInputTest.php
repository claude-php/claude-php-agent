<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Chains;

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\Exceptions\ChainValidationException;
use PHPUnit\Framework\TestCase;

class ChainInputTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $input = ChainInput::create(['key' => 'value', 'number' => 42]);

        $this->assertTrue($input->has('key'));
        $this->assertTrue($input->has('number'));
        $this->assertFalse($input->has('missing'));
    }

    public function testGetValue(): void
    {
        $input = ChainInput::create(['name' => 'test', 'count' => 5]);

        $this->assertEquals('test', $input->get('name'));
        $this->assertEquals(5, $input->get('count'));
        $this->assertNull($input->get('missing'));
        $this->assertEquals('default', $input->get('missing', 'default'));
    }

    public function testGetAll(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $input = ChainInput::create($data);

        $this->assertEquals($data, $input->all());
    }

    public function testGetDotNotation(): void
    {
        $input = ChainInput::create([
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ]);

        $this->assertEquals('John', $input->getDot('user.name'));
        $this->assertEquals('New York', $input->getDot('user.address.city'));
        $this->assertNull($input->getDot('user.missing'));
        $this->assertEquals('default', $input->getDot('user.missing', 'default'));
    }

    public function testValidateRequiredFields(): void
    {
        $input = ChainInput::create(['name' => 'test', 'age' => 25]);

        $schema = [
            'required' => ['name', 'age'],
        ];

        $this->assertTrue($input->validate($schema));
    }

    public function testValidateMissingRequiredField(): void
    {
        $input = ChainInput::create(['name' => 'test']);

        $schema = [
            'required' => ['name', 'age'],
        ];

        $this->expectException(ChainValidationException::class);
        $this->expectExceptionMessage('Missing required input field: age');

        $input->validate($schema);
    }

    public function testValidateEmptySchema(): void
    {
        $input = ChainInput::create(['any' => 'value']);

        $this->assertTrue($input->validate([]));
    }
}
