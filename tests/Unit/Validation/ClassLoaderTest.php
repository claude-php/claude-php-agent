<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use ClaudeAgents\Validation\ClassLoader;
use ClaudeAgents\Validation\Exceptions\ClassLoadException;
use PHPUnit\Framework\TestCase;

class ClassLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/class_loader_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
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

    public function test_creates_loader_with_default_options(): void
    {
        $loader = new ClassLoader();

        $this->assertInstanceOf(ClassLoader::class, $loader);
    }

    public function test_throws_exception_when_eval_not_allowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Eval strategy requires explicit opt-in');

        new ClassLoader([
            'load_strategy' => 'eval',
            'allow_eval' => false,
        ]);
    }

    public function test_allows_eval_when_explicitly_enabled(): void
    {
        $loader = new ClassLoader([
            'load_strategy' => 'eval',
            'allow_eval' => true,
        ]);

        $this->assertInstanceOf(ClassLoader::class, $loader);
    }

    public function test_loads_simple_class_from_temp_file(): void
    {
        $code = <<<'PHP'
<?php

class SimpleTestClass
{
    public function getValue(): string
    {
        return 'test';
    }
}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $fqcn = $loader->loadClass($code, 'SimpleTestClass');

        $this->assertStringContainsString('SimpleTestClass', $fqcn);
        $this->assertTrue(class_exists($fqcn));

        $instance = new $fqcn();
        $this->assertSame('test', $instance->getValue());
    }

    public function test_loads_class_with_namespace_from_temp_file(): void
    {
        $code = <<<'PHP'
<?php

namespace MyApp\Test;

class NamespacedTestClass
{
    public function getValue(): string
    {
        return 'namespaced';
    }
}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $fqcn = $loader->loadClass($code, 'NamespacedTestClass');

        $this->assertTrue(class_exists($fqcn));

        $instance = new $fqcn();
        $this->assertSame('namespaced', $instance->getValue());
    }

    public function test_loads_class_with_constructor_from_temp_file(): void
    {
        $code = <<<'PHP'
<?php

class ConstructorTestClass
{
    private string $value;
    
    public function __construct(string $value = 'default')
    {
        $this->value = $value;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $fqcn = $loader->loadClass($code, 'ConstructorTestClass');

        $this->assertTrue(class_exists($fqcn));

        $instance = new $fqcn('test_value');
        $this->assertSame('test_value', $instance->getValue());
    }

    public function test_throws_exception_for_missing_class(): void
    {
        $code = <<<'PHP'
<?php

// No class definition
function test() {
    return 'test';
}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $this->expectException(ClassLoadException::class);
        $this->expectExceptionMessage('was not found after loading');

        $loader->loadClass($code, 'NonExistentClass');
    }

    public function test_loads_class_with_eval_strategy(): void
    {
        $code = <<<'PHP'
<?php

class EvalTestClass
{
    public function getValue(): string
    {
        return 'eval_test';
    }
}
PHP;

        $loader = new ClassLoader([
            'load_strategy' => 'eval',
            'allow_eval' => true,
        ]);

        $fqcn = $loader->loadClass($code, 'EvalTestClass');

        $this->assertTrue(class_exists($fqcn));

        $instance = new $fqcn();
        $this->assertSame('eval_test', $instance->getValue());
    }

    public function test_throws_exception_for_eval_when_not_allowed(): void
    {
        $code = <<<'PHP'
<?php

class TestClass {}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        // Trying to use eval via loadFromEval directly should fail
        $this->expectException(ClassLoadException::class);
        $this->expectExceptionMessage('Eval strategy is not allowed');

        $loader->loadFromEval($code, 'TestClass');
    }

    public function test_tracks_temp_files(): void
    {
        $code = <<<'PHP'
<?php

class TempFileTrackingTest {}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
            'cleanup_temp_files' => false, // Don't auto-cleanup for this test
        ]);

        $loader->loadClass($code, 'TempFileTrackingTest');

        $tempFiles = $loader->getTempFiles();

        $this->assertCount(1, $tempFiles);
        $this->assertFileExists($tempFiles[0]);
    }

    public function test_cleans_up_temp_files(): void
    {
        $code = <<<'PHP'
<?php

class CleanupTest {}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
            'cleanup_temp_files' => false,
        ]);

        $loader->loadClass($code, 'CleanupTest');

        $tempFiles = $loader->getTempFiles();
        $this->assertCount(1, $tempFiles);
        $this->assertFileExists($tempFiles[0]);

        $loader->cleanupTempFiles();

        $this->assertFileDoesNotExist($tempFiles[0]);
        $this->assertCount(0, $loader->getTempFiles());
    }

    public function test_handles_multiple_class_loads(): void
    {
        $code1 = <<<'PHP'
<?php

class FirstClass
{
    public function getValue(): string { return 'first'; }
}
PHP;

        $code2 = <<<'PHP'
<?php

class SecondClass
{
    public function getValue(): string { return 'second'; }
}
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $fqcn1 = $loader->loadClass($code1, 'FirstClass');
        $fqcn2 = $loader->loadClass($code2, 'SecondClass');

        $this->assertTrue(class_exists($fqcn1));
        $this->assertTrue(class_exists($fqcn2));

        $instance1 = new $fqcn1();
        $instance2 = new $fqcn2();

        $this->assertSame('first', $instance1->getValue());
        $this->assertSame('second', $instance2->getValue());
    }

    public function test_handles_syntax_errors_gracefully(): void
    {
        $code = <<<'PHP'
<?php

class BrokenClass {
    // Missing closing brace
PHP;

        $loader = new ClassLoader([
            'temp_dir' => $this->tempDir,
        ]);

        $this->expectException(ClassLoadException::class);

        $loader->loadClass($code, 'BrokenClass');
    }

    public function test_unknown_strategy_throws_exception(): void
    {
        $code = '<?php class TestClass {}';

        $loader = new ClassLoader([
            'load_strategy' => 'unknown_strategy',
        ]);

        $this->expectException(ClassLoadException::class);
        $this->expectExceptionMessage('Unknown load strategy');

        $loader->loadClass($code, 'TestClass');
    }
}
