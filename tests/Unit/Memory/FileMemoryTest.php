<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\FileMemory;
use PHPUnit\Framework\TestCase;

class FileMemoryTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFile = sys_get_temp_dir() . '/test_memory_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }

        // Also clean up any leftover subdirectories
        $pattern = sys_get_temp_dir() . '/test_subdir_*';
        foreach (glob($pattern) as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($dir);
            }
        }

        parent::tearDown();
    }

    public function testSetAndGetWithAutoSave(): void
    {
        $memory = new FileMemory($this->testFile, autoSave: true);
        $memory->set('key', 'value');

        // Create new instance to verify persistence
        $memory2 = new FileMemory($this->testFile);

        $this->assertEquals('value', $memory2->get('key'));
    }

    public function testSetWithoutAutoSave(): void
    {
        $memory = new FileMemory($this->testFile, autoSave: false);
        $memory->set('key', 'value');

        // File should not exist yet
        $this->assertFalse(file_exists($this->testFile));

        $memory->save();

        // Now it should exist
        $this->assertTrue(file_exists($this->testFile));
    }

    public function testLoadExistingFile(): void
    {
        $memory1 = new FileMemory($this->testFile);
        $memory1->set('key', 'value');

        $memory2 = new FileMemory($this->testFile);

        $this->assertEquals('value', $memory2->get('key'));
    }

    public function testForgetWithAutoSave(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('key', 'value');
        $memory->forget('key');

        $memory2 = new FileMemory($this->testFile);

        $this->assertFalse($memory2->has('key'));
    }

    public function testClear(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('key1', 'value1');
        $memory->set('key2', 'value2');
        $memory->clear();

        $memory2 = new FileMemory($this->testFile);

        $this->assertEmpty($memory2->all());
    }

    public function testDelete(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('key', 'value');

        $this->assertTrue($memory->exists());

        $memory->delete();

        $this->assertFalse($memory->exists());
        $this->assertFalse(file_exists($this->testFile));
    }

    public function testDeleteNonExistentFile(): void
    {
        $memory = new FileMemory($this->testFile, autoSave: false);

        $result = $memory->delete();

        $this->assertTrue($result);
    }

    public function testGetFilePath(): void
    {
        $memory = new FileMemory($this->testFile);

        $this->assertEquals($this->testFile, $memory->getFilePath());
    }

    public function testExists(): void
    {
        $memory = new FileMemory($this->testFile, autoSave: false);

        $this->assertFalse($memory->exists());

        $memory->set('key', 'value');
        $memory->save();

        $this->assertTrue($memory->exists());
    }

    public function testGetLastModified(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('key', 'value');

        $lastModified = $memory->getLastModified();

        $this->assertIsInt($lastModified);
        $this->assertGreaterThan(0, $lastModified);
    }

    public function testGetLastModifiedNonExistent(): void
    {
        $memory = new FileMemory($this->testFile, autoSave: false);

        $lastModified = $memory->getLastModified();

        $this->assertNull($lastModified);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $subdir = sys_get_temp_dir() . '/test_subdir_' . uniqid();
        $filePath = $subdir . '/memory.json';

        $memory = new FileMemory($filePath);
        $memory->set('key', 'value');

        $this->assertTrue(is_dir($subdir));
        $this->assertTrue(file_exists($filePath));

        // Cleanup
        unlink($filePath);
        rmdir($subdir);
    }

    public function testPersistsComplexData(): void
    {
        $memory = new FileMemory($this->testFile);
        $complexData = [
            'array' => [1, 2, 3],
            'nested' => ['deep' => ['value' => 'test']],
            'number' => 42,
            'bool' => true,
        ];

        $memory->set('complex', $complexData);

        $memory2 = new FileMemory($this->testFile);

        $this->assertEquals($complexData, $memory2->get('complex'));
    }

    public function testGetWithDefault(): void
    {
        $memory = new FileMemory($this->testFile);

        $this->assertEquals('default', $memory->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('exists', 'value');

        $this->assertTrue($memory->has('exists'));
        $this->assertFalse($memory->has('does_not_exist'));
    }

    public function testAll(): void
    {
        $memory = new FileMemory($this->testFile);
        $memory->set('key1', 'value1');
        $memory->set('key2', 'value2');

        $all = $memory->all();

        $this->assertCount(2, $all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }
}
