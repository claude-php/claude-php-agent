<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration;

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudeAgents\Generation\ComponentResult;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for component validation with AI-generated code.
 *
 * Note: This test requires a valid ANTHROPIC_API_KEY environment variable.
 * Skip these tests if the key is not available.
 *
 * @group integration
 * @group requires-api-key
 */
class ComponentValidationIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private string $tempDir;

    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
        
        $this->tempDir = sys_get_temp_dir() . '/component_validation_integration_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function test_generates_and_validates_simple_component(): void
    {
        // Setup validators including component instantiation
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);
        
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a StringHelper class with a method to reverse a string and a method to capitalize the first letter';

        $result = $agent->generateComponent($description);

        // Verify generation succeeded
        $this->assertInstanceOf(ComponentResult::class, $result);
        $this->assertTrue($result->isValid(), 'Generated component should be valid');
        
        // Verify code was generated
        $code = $result->getCode();
        $this->assertStringContainsString('class StringHelper', $code);
        $this->assertStringContainsString('<?php', $code);
        
        // Verify validation metadata includes component instantiation
        $validation = $result->getValidation();
        $this->assertGreaterThanOrEqual(2, $validation->getMetadata()['validator_count']);
    }

    public function test_generates_component_with_constructor_validation(): void
    {
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);
        
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
            'constructor_args' => [], // No args - component should have optional args or no args
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a ConfigLoader class with an optional constructor that takes a file path parameter with a default value, and a load() method that returns an array';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('class ConfigLoader', $result->getCode());
    }

    public function test_validates_generated_component_with_dependencies(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a Logger class with no constructor dependencies that has methods info() and error() to log messages';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        
        // Verify the component can actually be instantiated
        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);
        
        $validationResult = $service->validate($result->getCode());
        
        $this->assertTrue($validationResult->isValid());
        $this->assertArrayHasKey('class_name', $validationResult->getMetadata());
        $this->assertArrayHasKey('fully_qualified_class_name', $validationResult->getMetadata());
    }

    public function test_handles_complex_component_generation(): void
    {
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);
        
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = <<<'DESC'
Create a CacheManager class with:
- A constructor that takes no required parameters
- Methods: set(key, value), get(key), has(key), delete(key), clear()
- Store data in an internal array property
- Return types should be appropriate for each method
DESC;

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        
        $code = $result->getCode();
        $this->assertStringContainsString('class CacheManager', $code);
        $this->assertStringContainsString('function set', $code);
        $this->assertStringContainsString('function get', $code);
        $this->assertStringContainsString('function has', $code);
        $this->assertStringContainsString('function delete', $code);
        $this->assertStringContainsString('function clear', $code);
    }

    public function test_tracks_validation_progress(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 2,
        ]);

        $validationEvents = [];
        $agent->onUpdate(function (string $type, array $data) use (&$validationEvents) {
            if (str_starts_with($type, 'validation.')) {
                $validationEvents[] = $type;
            }
        });

        $description = 'Create a simple Calculator class with add and multiply methods';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        $this->assertContains('validation.started', $validationEvents);
        $this->assertContains('validation.passed', $validationEvents);
    }

    public function test_retries_on_instantiation_failure(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['required_value'], // Provide args that might be needed
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $retries = [];
        $agent->onRetry(function (int $attempt, array $errors) use (&$retries) {
            $retries[] = [
                'attempt' => $attempt,
                'errors' => $errors,
            ];
        });

        // Request something that might need retries
        $description = 'Create a simple EmailSender class with a constructor that takes an optional apiKey parameter with default value empty string, and a send method';

        $result = $agent->generateComponent($description);

        // Should eventually succeed or exhaust retries
        $this->assertInstanceOf(ComponentResult::class, $result);
        
        // If it's valid, great. If not, we should have retry attempts
        if (! $result->isValid()) {
            $this->assertNotEmpty($retries, 'Should have retry attempts if validation failed');
        }
    }

    public function test_validates_namespaced_component_generation(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a class called UserRepository in the namespace App\Repositories with methods to find users. Include no constructor dependencies.';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        
        $code = $result->getCode();
        $this->assertStringContainsString('namespace', $code);
        $this->assertStringContainsString('class UserRepository', $code);
        
        // Verify namespace was extracted correctly
        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);
        $validation = $service->validate($code);
        
        $this->assertArrayHasKey('namespace', $validation->getMetadata());
        $this->assertNotNull($validation->getMetadata()['namespace']);
    }

    public function test_end_to_end_component_lifecycle(): void
    {
        // Step 1: Setup comprehensive validation
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
            'cache_results' => false,
        ]);
        
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        // Step 2: Generate component with AI
        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a UrlParser class with a constructor that takes no required parameters and a parse method that takes a URL string and returns an array with scheme, host, path components';

        $result = $agent->generateComponent($description);

        // Step 3: Verify generation and validation
        $this->assertInstanceOf(ComponentResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertTrue($result->wasValidated());

        // Step 4: Save to file
        $tempFile = $this->tempDir . '/UrlParser.php';
        $saved = $result->saveToFile($tempFile);
        
        $this->assertTrue($saved);
        $this->assertFileExists($tempFile);

        // Step 5: Re-validate saved file
        $savedCode = file_get_contents($tempFile);
        $revalidation = $coordinator->validate($savedCode);
        
        $this->assertTrue($revalidation->isValid());

        // Step 6: Extract metadata
        $metadata = $result->getValidation()->getMetadata();
        $this->assertArrayHasKey('validator_count', $metadata);
        $this->assertArrayHasKey('duration_ms', $metadata);
        
        // Should have run both validators
        $this->assertGreaterThanOrEqual(2, $metadata['validator_count']);

        // Step 7: Verify component result provides summary
        $summary = $result->getSummary();
        $this->assertStringContainsString('passed', strtolower($summary));
        $this->assertStringContainsString('bytes', $summary);
    }

    public function test_validates_component_with_interface_implementation(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a JsonSerializer class that has methods encode() and decode(). No constructor dependencies required.';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        
        // Verify instantiation metadata
        $validation = $result->getValidation();
        $metadata = $validation->getMetadata();
        
        // Should have instantiation metadata from ComponentInstantiationValidator
        $this->assertGreaterThan(0, $metadata['duration_ms']);
    }

    public function test_validates_component_with_static_methods(): void
    {
        $coordinator = new ValidationCoordinator();
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
            'max_validation_retries' => 3,
        ]);

        $description = 'Create a StringUtils class with static methods: slugify(string), truncate(string, int), and random(int). Include an empty constructor.';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('class StringUtils', $result->getCode());
    }

    public function test_handles_validation_metadata_correctly(): void
    {
        $coordinator = new ValidationCoordinator([
            'cache_results' => false,
        ]);
        
        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $agent = new CodeGenerationAgent($this->client, [
            'validation_coordinator' => $coordinator,
        ]);

        $description = 'Create a simple Counter class with increment, decrement, and getValue methods. No constructor parameters required.';

        $result = $agent->generateComponent($description);

        $this->assertTrue($result->isValid());
        
        $validation = $result->getValidation();
        $metadata = $validation->getMetadata();
        
        // Verify comprehensive metadata
        $this->assertArrayHasKey('validator_count', $metadata);
        $this->assertArrayHasKey('duration_ms', $metadata);
        $this->assertIsInt($metadata['validator_count']);
        $this->assertIsFloat($metadata['duration_ms']);
        $this->assertGreaterThan(0, $metadata['duration_ms']);
    }
}
