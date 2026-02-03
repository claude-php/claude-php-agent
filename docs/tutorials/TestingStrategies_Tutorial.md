# Testing Strategies Tutorial: Comprehensive Testing for AI Agents

## Introduction

This tutorial will guide you through testing AI agents comprehensively, covering unit tests, feature tests, integration tests with real APIs, mocking strategies, and measuring test coverage.

By the end of this tutorial, you'll be able to:

- Write unit tests for individual agent components
- Create feature tests for complete workflows
- Run integration tests with the Anthropic API
- Mock AI responses for fast, deterministic tests
- Test validation pipelines
- Measure and improve test coverage
- Set up CI/CD testing pipelines

## Prerequisites

- PHP 8.1 or higher
- Composer
- PHPUnit 10+
- Claude API key (for integration tests)
- Basic understanding of testing concepts
- Completed previous tutorials (recommended)

## Table of Contents

1. [Understanding Test Types](#understanding-test-types)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: Unit Testing](#tutorial-1-unit-testing)
4. [Tutorial 2: Feature Testing](#tutorial-2-feature-testing)
5. [Tutorial 3: Integration Testing](#tutorial-3-integration-testing)
6. [Tutorial 4: Mocking AI Responses](#tutorial-4-mocking-ai-responses)
7. [Tutorial 5: Validation Testing](#tutorial-5-validation-testing)
8. [Tutorial 6: Test Coverage](#tutorial-6-test-coverage)
9. [Tutorial 7: CI/CD Testing](#tutorial-7-cicd-testing)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Understanding Test Types

The testing pyramid for AI agents:

```
        ┌─────────────┐
        │ Integration │  ← Slow, real API
        │   Tests     │     (11 tests)
        ├─────────────┤
        │   Feature   │  ← Medium, workflows
        │   Tests     │     (12 tests)
        ├─────────────┤
        │    Unit     │  ← Fast, isolated
        │   Tests     │     (63 tests)
        └─────────────┘
```

### Test Type Comparison

| Type | Speed | Cost | Coverage | When to Use |
|------|-------|------|----------|-------------|
| Unit | Fast (ms) | Free | Narrow | Test components |
| Feature | Medium (ms) | Free | Wide | Test workflows |
| Integration | Slow (seconds) | $$$ | Complete | Test real API |

## Setup and Installation

### Install PHPUnit

```bash
composer require --dev phpunit/phpunit:^10.0
composer require --dev mockery/mockery:^1.6
```

### Configure PHPUnit

Create `phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

## Tutorial 1: Unit Testing

Test individual components in isolation.

### Step 1: Test a Tool

```php
<?php

namespace Tests\Unit\Tools;

use ClaudeAgents\Tools\Tool;
use PHPUnit\Framework\TestCase;

class CalculatorToolTest extends TestCase
{
    private Tool $calculator;
    
    protected function setUp(): void
    {
        $this->calculator = Tool::create('calculate')
            ->description('Perform calculations')
            ->parameter('expression', 'string', 'Math expression')
            ->required('expression')
            ->handler(function (array $input): string {
                return (string) eval("return {$input['expression']};");
            });
    }
    
    public function test_tool_has_correct_name(): void
    {
        $this->assertSame('calculate', $this->calculator->getName());
    }
    
    public function test_tool_executes_simple_calculation(): void
    {
        $result = $this->calculator->execute(['expression' => '2 + 2']);
        
        $this->assertSame('4', $result);
    }
    
    public function test_tool_handles_complex_expression(): void
    {
        $result = $this->calculator->execute(['expression' => '(10 + 5) * 2']);
        
        $this->assertSame('30', $result);
    }
}
```

### Step 2: Test ValidationResult

```php
<?php

namespace Tests\Unit\Validation;

use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

class ValidationResultTest extends TestCase
{
    public function test_creates_successful_result(): void
    {
        $result = ValidationResult::success();
        
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isFailed());
        $this->assertEmpty($result->getErrors());
    }
    
    public function test_creates_failed_result(): void
    {
        $errors = ['Syntax error', 'Type mismatch'];
        $result = ValidationResult::failure($errors);
        
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isFailed());
        $this->assertCount(2, $result->getErrors());
    }
    
    public function test_merges_results(): void
    {
        $result1 = ValidationResult::success(['Warning 1']);
        $result2 = ValidationResult::failure(['Error 1']);
        
        $merged = $result1->merge($result2);
        
        $this->assertFalse($merged->isValid());
        $this->assertCount(1, $merged->getErrors());
        $this->assertCount(1, $merged->getWarnings());
    }
}
```

### Step 3: Test Service Components

```php
<?php

namespace Tests\Unit\Services;

use ClaudeAgents\Services\Cache\CacheService;
use PHPUnit\Framework\TestCase;

class CacheServiceTest extends TestCase
{
    private CacheService $cache;
    
    protected function setUp(): void
    {
        $this->cache = new CacheService(['driver' => 'array']);
        $this->cache->initialize();
    }
    
    public function test_stores_and_retrieves_value(): void
    {
        $this->cache->set('test_key', 'test_value');
        
        $value = $this->cache->get('test_key');
        
        $this->assertSame('test_value', $value);
    }
    
    public function test_returns_default_for_missing_key(): void
    {
        $value = $this->cache->get('missing_key', 'default');
        
        $this->assertSame('default', $value);
    }
    
    public function test_deletes_value(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->has('key'));
        
        $this->cache->delete('key');
        
        $this->assertFalse($this->cache->has('key'));
    }
}
```

## Tutorial 2: Feature Testing

Test complete workflows.

### Step 1: Test Validation Workflow

```php
<?php

namespace Tests\Feature;

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group feature
 */
class ValidationWorkflowTest extends TestCase
{
    private ValidationCoordinator $coordinator;
    
    protected function setUp(): void
    {
        $this->coordinator = new ValidationCoordinator();
        $this->coordinator
            ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
            ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
    }
    
    public function test_validates_complete_component_lifecycle(): void
    {
        $code = <<<'PHP'
<?php

class TestComponent
{
    public function getValue(): string
    {
        return 'test';
    }
}
PHP;
        
        $result = $this->coordinator->validate($code);
        
        $this->assertTrue($result->isValid());
        $this->assertGreaterThanOrEqual(2, $result->getMetadata()['validator_count']);
    }
}
```

### Step 2: Test Service Integration

```php
public function test_services_integrate_correctly(): void
{
    $manager = ServiceManager::getInstance();
    
    // Register services
    $manager
        ->registerFactory(new CacheServiceFactory())
        ->registerFactory(new SettingsServiceFactory());
    
    // Get services
    $cache = $manager->get(ServiceType::CACHE);
    $settings = $manager->get(ServiceType::SETTINGS);
    
    // Test integration
    $settings->set('cache.enabled', true);
    $cache->set('test', 'value');
    
    $this->assertTrue($settings->get('cache.enabled'));
    $this->assertSame('value', $cache->get('test'));
}
```

## Tutorial 3: Integration Testing

Test with real Anthropic API.

### Step 1: Setup Integration Test

```php
<?php

namespace Tests\Integration;

use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 * @group requires-api-key
 */
class AgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    
    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        
        $this->client = new ClaudePhp(apiKey: $apiKey);
    }
    
    public function test_agent_executes_successfully(): void
    {
        $agent = Agent::create($this->client);
        
        $result = $agent->run('What is 2+2?');
        
        $this->assertNotEmpty($result->getAnswer());
        $this->assertStringContainsString('4', $result->getAnswer());
    }
}
```

### Step 2: Test Code Generation Integration

```php
/**
 * @group integration
 * @group requires-api-key
 */
public function test_generates_valid_component(): void
{
    $coordinator = new ValidationCoordinator();
    $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
    $coordinator->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
    
    $agent = new CodeGenerationAgent($this->client, [
        'validation_coordinator' => $coordinator,
    ]);
    
    $result = $agent->generateComponent('Create a simple Logger class');
    
    $this->assertTrue($result->isValid());
    $this->assertStringContainsString('class Logger', $result->getCode());
}
```

### Step 3: Test End-to-End Workflow

```php
public function test_complete_workflow(): void
{
    // Generate code
    $agent = new CodeGenerationAgent($this->client, [
        'validation_coordinator' => $this->coordinator,
    ]);
    
    $result = $agent->generateComponent('Create a Counter class');
    
    // Validate
    $this->assertTrue($result->isValid());
    
    // Save
    $tempFile = sys_get_temp_dir() . '/Counter.php';
    $saved = $result->saveToFile($tempFile);
    $this->assertTrue($saved);
    
    // Re-validate saved file
    $savedCode = file_get_contents($tempFile);
    $revalidation = $this->coordinator->validate($savedCode);
    $this->assertTrue($revalidation->isValid());
    
    // Cleanup
    unlink($tempFile);
}
```

## Tutorial 4: Mocking AI Responses

Test without calling the API.

### Step 1: Mock ClaudePhp Client

```php
<?php

namespace Tests\Unit;

use ClaudePhp\ClaudePhp;
use Mockery;
use PHPUnit\Framework\TestCase;

class MockedAgentTest extends TestCase
{
    public function test_agent_with_mocked_client(): void
    {
        // Mock the Claude client
        $mockClient = Mockery::mock(ClaudePhp::class);
        
        $mockClient->shouldReceive('messages->create')
            ->once()
            ->andReturn((object) [
                'content' => [
                    (object) ['text' => 'Mocked response: 4']
                ],
                'stop_reason' => 'end_turn',
                'usage' => (object) [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ]);
        
        // Use mocked client
        $agent = Agent::create($mockClient);
        $result = $agent->run('What is 2+2?');
        
        $this->assertStringContainsString('Mocked response', $result->getAnswer());
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
    }
}
```

### Step 2: Mock Tool Execution

```php
public function test_tool_with_mock(): void
{
    $mockTool = Mockery::mock(Tool::class);
    
    $mockTool->shouldReceive('getName')
        ->andReturn('calculator');
        
    $mockTool->shouldReceive('execute')
        ->with(['expression' => '2+2'])
        ->andReturn('4');
    
    $result = $mockTool->execute(['expression' => '2+2']);
    
    $this->assertSame('4', $result);
}
```

### Step 3: Test with Fixtures

```php
class FixtureBasedTest extends TestCase
{
    private function loadFixture(string $name): array
    {
        $file = __DIR__ . "/fixtures/{$name}.json";
        return json_decode(file_get_contents($file), true);
    }
    
    public function test_processes_fixture_response(): void
    {
        $fixture = $this->loadFixture('agent_response');
        
        // Mock client to return fixture
        $mockClient = Mockery::mock(ClaudePhp::class);
        $mockClient->shouldReceive('messages->create')
            ->andReturn((object) $fixture);
        
        $agent = Agent::create($mockClient);
        $result = $agent->run('test query');
        
        $this->assertNotEmpty($result->getAnswer());
    }
}
```

## Tutorial 5: Validation Testing

Test validation components.

### Step 1: Test ComponentValidationService

```php
<?php

namespace Tests\Unit\Validation;

use ClaudeAgents\Validation\ComponentValidationService;
use PHPUnit\Framework\TestCase;

class ComponentValidationServiceTest extends TestCase
{
    private ComponentValidationService $service;
    
    protected function setUp(): void
    {
        $this->service = new ComponentValidationService();
    }
    
    public function test_validates_simple_class(): void
    {
        $code = '<?php class Test {}';
        
        $result = $this->service->validate($code);
        
        $this->assertTrue($result->isValid());
        $this->assertSame('Test', $result->getMetadata()['class_name']);
    }
    
    public function test_detects_constructor_errors(): void
    {
        $code = <<<'PHP'
<?php
class FailingClass
{
    public function __construct()
    {
        throw new \Exception('Init failed');
    }
}
PHP;
        
        $result = $this->service->validate($code);
        
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Init failed', $result->getErrors()[0]);
    }
}
```

### Step 2: Test Validation Pipeline

```php
public function test_validation_pipeline(): void
{
    $coordinator = new ValidationCoordinator();
    $coordinator
        ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
        ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));
    
    $validCode = '<?php class ValidClass {}';
    $result = $coordinator->validate($validCode);
    
    $this->assertTrue($result->isValid());
    $this->assertSame(2, $result->getMetadata()['validator_count']);
}
```

## Tutorial 6: Test Coverage

Measure and improve coverage.

### Step 1: Generate Coverage Report

```bash
# With Xdebug
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/

# With PCOV
php -d pcov.enabled=1 ./vendor/bin/phpunit --coverage-html coverage/
```

### Step 2: Coverage Configuration

Update `phpunit.xml`:

```xml
<coverage>
    <include>
        <directory suffix=".php">src</directory>
    </include>
    <exclude>
        <directory>src/Generated</directory>
        <file>src/bootstrap.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage/html"/>
        <text outputFile="coverage/coverage.txt"/>
    </report>
</coverage>
```

### Step 3: Enforce Coverage Thresholds

```php
// In CI/CD pipeline
$coverage = file_get_contents('coverage/coverage.txt');

if (preg_match('/Lines:\s+(\d+\.\d+)%/', $coverage, $matches)) {
    $percentage = (float) $matches[1];
    
    if ($percentage < 80.0) {
        echo "Coverage too low: {$percentage}%\n";
        exit(1);
    }
    
    echo "Coverage: {$percentage}% ✓\n";
}
```

## Tutorial 7: CI/CD Testing

Automate testing in pipelines.

### Step 1: GitHub Actions

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: xdebug
          
      - name: Install dependencies
        run: composer install --prefer-dist
        
      - name: Run unit tests
        run: ./vendor/bin/phpunit tests/Unit/ --testdox
        
      - name: Run feature tests
        run: ./vendor/bin/phpunit tests/Feature/ --testdox
  
  integration-tests:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          
      - name: Install dependencies
        run: composer install
        
      - name: Run integration tests
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: ./vendor/bin/phpunit tests/Integration/ --testdox
```

### Step 2: Test Groups

```php
/**
 * @group unit
 * @group fast
 */
class FastUnitTest extends TestCase
{
    // Quick tests
}

/**
 * @group integration
 * @group requires-api-key
 * @group slow
 */
class SlowIntegrationTest extends TestCase
{
    // Slow tests with API
}

// Run specific groups
// ./vendor/bin/phpunit --group fast
// ./vendor/bin/phpunit --exclude-group slow
```

### Step 3: Parallel Testing

```bash
# Install paratest
composer require --dev brianium/paratest

# Run tests in parallel
./vendor/bin/paratest --processes=4 tests/Unit/

# With coverage
./vendor/bin/paratest -p 4 --coverage-html coverage/ tests/
```

## Common Patterns

### Pattern 1: Test Base Class

```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ClaudeAgents\Services\ServiceManager;

abstract class AgentTestCase extends TestCase
{
    protected ServiceManager $services;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup services for testing
        $this->services = ServiceManager::getInstance();
        $this->services->registerFactory(new CacheServiceFactory());
    }
    
    protected function tearDown(): void
    {
        // Cleanup
        $this->services->teardownAll();
        $this->services->clearMocks();
        
        parent::tearDown();
    }
    
    protected function mockService(ServiceType $type, $mock): void
    {
        $this->services->mock($type, $mock);
    }
}
```

### Pattern 2: Data Providers

```php
/**
 * @dataProvider validationCases
 */
public function test_validates_various_code_samples(
    string $code,
    bool $expectedValid,
    string $description
): void {
    $result = $this->service->validate($code);
    
    $this->assertSame(
        $expectedValid,
        $result->isValid(),
        $description
    );
}

public static function validationCases(): array
{
    return [
        'simple_class' => [
            '<?php class Simple {}',
            true,
            'Simple class should validate',
        ],
        'broken_syntax' => [
            '<?php class Broken {',
            false,
            'Broken syntax should fail',
        ],
        'failing_constructor' => [
            '<?php class Fail { public function __construct() { throw new \Exception(); } }',
            false,
            'Failing constructor should fail',
        ],
    ];
}
```

### Pattern 3: Custom Assertions

```php
trait ValidationAssertions
{
    protected function assertValidationPassed(ValidationResult $result, string $message = ''): void
    {
        $this->assertTrue($result->isValid(), $message);
        $this->assertEmpty($result->getErrors(), 'Should have no errors');
    }
    
    protected function assertValidationFailed(
        ValidationResult $result,
        string $expectedError = '',
        string $message = ''
    ): void {
        $this->assertFalse($result->isValid(), $message);
        
        if ($expectedError) {
            $errors = implode(' ', $result->getErrors());
            $this->assertStringContainsString($expectedError, $errors);
        }
    }
}

// Use in tests
class MyTest extends TestCase
{
    use ValidationAssertions;
    
    public function test_validation(): void
    {
        $result = $this->service->validate($code);
        $this->assertValidationPassed($result);
    }
}
```

## Troubleshooting

### Problem: Tests are too slow

**Solutions:**
```bash
# 1. Run only fast tests
./vendor/bin/phpunit --exclude-group slow

# 2. Run in parallel
./vendor/bin/paratest -p 4 tests/Unit/

# 3. Skip integration tests locally
./vendor/bin/phpunit --exclude-group integration
```

### Problem: Flaky integration tests

**Solutions:**
```php
// Add retries for flaky tests
public function test_flaky_api_call(): void
{
    $attempts = 0;
    $maxAttempts = 3;
    
    while ($attempts < $maxAttempts) {
        try {
            $result = $this->agent->run($query);
            $this->assertNotEmpty($result->getAnswer());
            return; // Success
        } catch (\Exception $e) {
            $attempts++;
            if ($attempts >= $maxAttempts) {
                throw $e;
            }
            sleep(1); // Wait before retry
        }
    }
}
```

### Problem: Mock expectations not met

**Solution:**
```php
// Use Mockery debugging
Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

// Add detailed expectations
$mock->shouldReceive('method')
    ->once()
    ->with(Mockery::on(function ($arg) {
        var_dump($arg); // Debug what's actually passed
        return true;
    }))
    ->andReturn('value');
```

## Next Steps

### Related Tutorials

- **[Component Validation Tutorial](./ComponentValidation_Tutorial.md)** - Validate test code
- **[Code Generation Tutorial](./CodeGeneration_Tutorial.md)** - Test generated code
- **[Production Patterns Tutorial](./ProductionPatterns_Tutorial.md)** - Production testing

### Further Reading

- [Best Practices](../BestPractices.md)
- [Component Validation Testing](../component-validation-testing.md)
- [PHPUnit Documentation](https://phpunit.de/)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/testing-strategies/`
- `tests/Unit/`, `tests/Feature/`, `tests/Integration/`

### What You've Learned

✓ Write unit tests for components
✓ Create feature tests for workflows
✓ Run integration tests with API
✓ Mock AI responses effectively
✓ Test validation pipelines
✓ Measure test coverage
✓ Set up CI/CD testing
✓ Implement testing best practices

**Congratulations!** You've completed the testing strategies tutorial. You now have the skills to test AI agents comprehensively!

---

*Tutorial Version: 1.0*
*Framework Version: v0.8.0+*
*Last Updated: February 2026*
