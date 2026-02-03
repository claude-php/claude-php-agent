<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Validation;

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\ValidationResult;
use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class ValidationCoordinatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_adds_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('getName')->andReturn('test');
        $validator->shouldReceive('getPriority')->andReturn(10);

        $coordinator->addValidator($validator);

        $this->assertCount(1, $coordinator->getValidators());
    }

    public function test_adds_multiple_validators(): void
    {
        $coordinator = new ValidationCoordinator();
        
        $validators = [];
        for ($i = 0; $i < 3; $i++) {
            $validator = Mockery::mock(ValidatorInterface::class);
            $validator->shouldReceive('getName')->andReturn("test{$i}");
            $validator->shouldReceive('getPriority')->andReturn(10 + $i);
            $validators[] = $validator;
        }

        $coordinator->addValidators($validators);

        $this->assertCount(3, $coordinator->getValidators());
    }

    public function test_removes_validator(): void
    {
        $coordinator = new ValidationCoordinator();
        
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('getName')->andReturn('test');
        $validator->shouldReceive('getPriority')->andReturn(10);

        $coordinator->addValidator($validator);
        $this->assertCount(1, $coordinator->getValidators());

        $coordinator->removeValidator('test');
        $this->assertCount(0, $coordinator->getValidators());
    }

    public function test_runs_validators_in_priority_order(): void
    {
        $coordinator = new ValidationCoordinator();
        $executionOrder = [];
        
        // Add validators in wrong order
        $validator2 = Mockery::mock(ValidatorInterface::class);
        $validator2->shouldReceive('getName')->andReturn('validator2');
        $validator2->shouldReceive('getPriority')->andReturn(20);
        $validator2->shouldReceive('canHandle')->andReturn(true);
        $validator2->shouldReceive('validate')->andReturnUsing(function () use (&$executionOrder) {
            $executionOrder[] = 'validator2';
            return ValidationResult::success();
        });

        $validator1 = Mockery::mock(ValidatorInterface::class);
        $validator1->shouldReceive('getName')->andReturn('validator1');
        $validator1->shouldReceive('getPriority')->andReturn(10);
        $validator1->shouldReceive('canHandle')->andReturn(true);
        $validator1->shouldReceive('validate')->andReturnUsing(function () use (&$executionOrder) {
            $executionOrder[] = 'validator1';
            return ValidationResult::success();
        });

        $coordinator->addValidator($validator2);
        $coordinator->addValidator($validator1);

        $coordinator->validate('<?php echo "test";');

        // Should execute in priority order (10 before 20)
        $this->assertEquals(['validator1', 'validator2'], $executionOrder);
    }

    public function test_stops_on_first_failure_when_configured(): void
    {
        $coordinator = new ValidationCoordinator(['stop_on_first_failure' => true]);
        
        $validator1 = Mockery::mock(ValidatorInterface::class);
        $validator1->shouldReceive('getName')->andReturn('validator1');
        $validator1->shouldReceive('getPriority')->andReturn(10);
        $validator1->shouldReceive('canHandle')->andReturn(true);
        $validator1->shouldReceive('validate')->andReturn(
            ValidationResult::failure(['Error from validator1'])
        );

        $validator2 = Mockery::mock(ValidatorInterface::class);
        $validator2->shouldReceive('getName')->andReturn('validator2');
        $validator2->shouldReceive('getPriority')->andReturn(20);
        $validator2->shouldReceive('canHandle')->never();
        $validator2->shouldReceive('validate')->never();

        $coordinator->addValidator($validator1);
        $coordinator->addValidator($validator2);

        $result = $coordinator->validate('<?php');

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }

    public function test_continues_on_failure_when_configured(): void
    {
        $coordinator = new ValidationCoordinator(['stop_on_first_failure' => false]);
        
        $validator1 = Mockery::mock(ValidatorInterface::class);
        $validator1->shouldReceive('getName')->andReturn('validator1');
        $validator1->shouldReceive('getPriority')->andReturn(10);
        $validator1->shouldReceive('canHandle')->andReturn(true);
        $validator1->shouldReceive('validate')->andReturn(
            ValidationResult::failure(['Error 1'])
        );

        $validator2 = Mockery::mock(ValidatorInterface::class);
        $validator2->shouldReceive('getName')->andReturn('validator2');
        $validator2->shouldReceive('getPriority')->andReturn(20);
        $validator2->shouldReceive('canHandle')->andReturn(true);
        $validator2->shouldReceive('validate')->andReturn(
            ValidationResult::failure(['Error 2'])
        );

        $coordinator->addValidator($validator1);
        $coordinator->addValidator($validator2);

        $result = $coordinator->validate('<?php');

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getErrors());
    }

    public function test_skips_validator_if_cannot_handle(): void
    {
        $coordinator = new ValidationCoordinator();
        
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('getName')->andReturn('test');
        $validator->shouldReceive('getPriority')->andReturn(10);
        $validator->shouldReceive('canHandle')->andReturn(false);
        $validator->shouldReceive('validate')->never();

        $coordinator->addValidator($validator);

        $result = $coordinator->validate('<?php');

        $this->assertTrue($result->isValid());
    }

    public function test_returns_success_with_warning_when_no_validators(): void
    {
        $coordinator = new ValidationCoordinator();

        $result = $coordinator->validate('<?php');

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
    }

    public function test_caches_results_when_enabled(): void
    {
        $coordinator = new ValidationCoordinator(['cache_results' => true]);
        
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('getName')->andReturn('test');
        $validator->shouldReceive('getPriority')->andReturn(10);
        $validator->shouldReceive('canHandle')->andReturn(true);
        $validator->shouldReceive('validate')->once()->andReturn(ValidationResult::success());

        $coordinator->addValidator($validator);

        // First call - should execute validator
        $result1 = $coordinator->validate('<?php echo "test";');
        // Second call - should use cache
        $result2 = $coordinator->validate('<?php echo "test";');

        $this->assertTrue($result1->isValid());
        $this->assertTrue($result2->isValid());
    }

    public function test_clears_cache(): void
    {
        $coordinator = new ValidationCoordinator(['cache_results' => true]);
        
        $validator = Mockery::mock(ValidatorInterface::class);
        $validator->shouldReceive('getName')->andReturn('test');
        $validator->shouldReceive('getPriority')->andReturn(10);
        $validator->shouldReceive('canHandle')->andReturn(true);
        $validator->shouldReceive('validate')->twice()->andReturn(ValidationResult::success());

        $coordinator->addValidator($validator);

        $coordinator->validate('<?php');
        $coordinator->clearCache();
        $coordinator->validate('<?php'); // Should execute again after cache clear

        $this->assertTrue(true); // If we get here, cache was cleared
    }
}
