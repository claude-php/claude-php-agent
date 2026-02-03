<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature;

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudeAgents\Generation\ComponentResult;
use ClaudeAgents\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Feature test for component validation workflow.
 *
 * @group feature
 */
class ComponentValidationWorkflowTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/component_validation_feature_' . uniqid();
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

    public function test_validates_complete_component_lifecycle(): void
    {
        // Create a component
        $code = <<<'PHP'
<?php

namespace App\Services;

class EmailValidator
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allow_empty' => false,
            'require_domain' => true,
        ], $config);
    }
    
    public function validate(string $email): bool
    {
        if (!$this->config['allow_empty'] && empty($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
PHP;

        // Validate with ComponentValidationService
        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => [['allow_empty' => false]],
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertSame('EmailValidator', $result->getMetadata()['class_name']);
        $this->assertSame('App\Services', $result->getMetadata()['namespace']);
    }

    public function test_integration_with_validation_coordinator(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Utils;

class MathHelper
{
    public function fibonacci(int $n): int
    {
        if ($n <= 1) {
            return $n;
        }
        return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
    }
}
PHP;

        // Create coordinator with multiple validators
        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);

        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $result = $coordinator->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertGreaterThanOrEqual(2, $result->getMetadata()['validator_count']);
    }

    public function test_detects_constructor_validation_errors(): void
    {
        $code = <<<'PHP'
<?php

class DatabaseConnection
{
    public function __construct(array $config)
    {
        if (empty($config['host'])) {
            throw new \InvalidArgumentException('Host is required');
        }
        
        if (empty($config['database'])) {
            throw new \InvalidArgumentException('Database name is required');
        }
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => [['host' => '']], // Invalid config
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Host is required', $result->getErrors()[0]);
    }

    public function test_handles_complex_dependency_validation(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Services;

class CacheManager
{
    private string $driver;
    private int $ttl;
    
    public function __construct(string $driver, int $ttl = 3600)
    {
        $allowedDrivers = ['redis', 'memcached', 'file'];
        
        if (!in_array($driver, $allowedDrivers)) {
            throw new \InvalidArgumentException(
                "Invalid driver. Must be one of: " . implode(', ', $allowedDrivers)
            );
        }
        
        if ($ttl < 0) {
            throw new \InvalidArgumentException('TTL must be non-negative');
        }
        
        $this->driver = $driver;
        $this->ttl = $ttl;
    }
}
PHP;

        // Test with valid args
        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['redis', 3600],
        ]);

        $result = $service->validate($code);
        $this->assertTrue($result->isValid());

        // Test with invalid driver
        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['invalid_driver', 3600],
        ]);

        $result = $service->validate($code);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Invalid driver', $result->getErrors()[0]);
    }

    public function test_validation_result_can_be_used_in_component_result(): void
    {
        $code = <<<'PHP'
<?php

class SimpleComponent
{
    public function getValue(): string
    {
        return 'test';
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $validationResult = $service->validate($code);

        // Create ComponentResult from validation
        $componentResult = new ComponentResult($code, $validationResult);

        $this->assertTrue($componentResult->isValid());
        $this->assertSame($code, $componentResult->getCode());
        $this->assertTrue($componentResult->wasValidated());
    }

    public function test_validates_components_with_extension_checks(): void
    {
        $code = <<<'PHP'
<?php

class JsonHandler
{
    public function __construct()
    {
        if (!extension_loaded('json')) {
            throw new \RuntimeException('JSON extension is required');
        }
    }
    
    public function encode(array $data): string
    {
        return json_encode($data);
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        // Should pass because json extension is loaded
        $this->assertTrue($result->isValid());
    }

    public function test_provides_detailed_metadata_on_success(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Models;

class User
{
    public function __construct(
        private string $name,
        private string $email
    ) {
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['John Doe', 'john@example.com'],
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('validator', $metadata);
        $this->assertArrayHasKey('class_name', $metadata);
        $this->assertArrayHasKey('namespace', $metadata);
        $this->assertArrayHasKey('load_strategy', $metadata);
        $this->assertArrayHasKey('instantiation_time_ms', $metadata);
        $this->assertArrayHasKey('constructor_args_count', $metadata);
        
        $this->assertSame('component_validation', $metadata['validator']);
        $this->assertSame('User', $metadata['class_name']);
        $this->assertSame('App\Models', $metadata['namespace']);
        $this->assertSame(2, $metadata['constructor_args_count']);
    }

    public function test_provides_detailed_metadata_on_failure(): void
    {
        $code = <<<'PHP'
<?php

class FailingComponent
{
    public function __construct()
    {
        throw new \RuntimeException('Intentional failure for testing');
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertFalse($result->isValid());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('exception_type', $metadata);
        $this->assertArrayHasKey('validator', $metadata);
        $this->assertArrayHasKey('duration_ms', $metadata);
        
        $this->assertStringContainsString('Exception', $metadata['exception_type']);
    }

    public function test_validation_coordinator_stops_on_first_failure(): void
    {
        $invalidCode = <<<'PHP'
<?php

class BrokenSyntax {
    public function test()
    {
        return "missing semicolon"
    }
}
PHP;

        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => true,
        ]);

        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $result = $coordinator->validate($invalidCode);

        $this->assertFalse($result->isValid());
        // Should stop after syntax validator
        $this->assertLessThanOrEqual(1, $result->getMetadata()['validator_count']);
    }

    public function test_validation_coordinator_runs_all_validators_when_configured(): void
    {
        $validCode = <<<'PHP'
<?php

class ValidComponent
{
    public function test(): string
    {
        return "valid";
    }
}
PHP;

        $coordinator = new ValidationCoordinator([
            'stop_on_first_failure' => false,
        ]);

        $coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
        $coordinator->addValidator(new ComponentInstantiationValidator([
            'priority' => 50,
            'temp_dir' => $this->tempDir,
        ]));

        $result = $coordinator->validate($validCode);

        $this->assertTrue($result->isValid());
        $this->assertSame(2, $result->getMetadata()['validator_count']);
    }

    public function test_validates_trait_like_components(): void
    {
        // While we primarily validate classes, the service should handle traits gracefully
        $code = <<<'PHP'
<?php

namespace App\Traits;

class LoggableTrait
{
    private array $logs = [];
    
    public function log(string $message): void
    {
        $this->logs[] = [
            'message' => $message,
            'timestamp' => time(),
        ];
    }
    
    public function getLogs(): array
    {
        return $this->logs;
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertSame('LoggableTrait', $result->getMetadata()['class_name']);
    }

    public function test_handles_namespaced_components_correctly(): void
    {
        $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateway;

use App\Contracts\PaymentInterface;

class StripeGateway
{
    public function __construct(
        private string $apiKey,
        private bool $sandbox = false
    ) {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('API key is required');
        }
    }
}
PHP;

        $service = new ComponentValidationService([
            'temp_dir' => $this->tempDir,
            'constructor_args' => ['test_key_123', true],
        ]);

        $result = $service->validate($code);

        $this->assertTrue($result->isValid());
        $this->assertSame('StripeGateway', $result->getMetadata()['class_name']);
        $this->assertSame('App\Services\Payment\Gateway', $result->getMetadata()['namespace']);
    }
}
