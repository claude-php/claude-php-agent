<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\FileSystemTool;
use PHPUnit\Framework\TestCase;

class FileSystemToolTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/filesystem_tool_test_' . uniqid();
        mkdir($this->testDir, 0o755, true);
        $this->testDir = realpath($this->testDir); // Get real path for symlink resolution
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testDir)) {
            $this->deleteDirectory($this->testDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCreateWithDefaults(): void
    {
        $tool = FileSystemTool::create();
        $this->assertEquals('filesystem', $tool->getName());
    }

    public function testWriteAndReadFile(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $filePath = $this->testDir . '/test.txt';
        $content = 'Hello, World!';

        // Write
        $result = $tool->execute([
            'operation' => 'write',
            'path' => $filePath,
            'content' => $content,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($filePath);

        // Read
        $result = $tool->execute([
            'operation' => 'read',
            'path' => $filePath,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals($content, $data['content']);
    }

    public function testReadOnlyMode(): void
    {
        $tool = FileSystemTool::create([
            'allowed_paths' => [$this->testDir],
            'read_only' => true,
        ]);

        $result = $tool->execute([
            'operation' => 'write',
            'path' => $this->testDir . '/test.txt',
            'content' => 'test',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('read-only', strtolower($result->getContent()));
    }

    public function testListDirectory(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        // Create some test files
        file_put_contents($this->testDir . '/file1.txt', 'content1');
        file_put_contents($this->testDir . '/file2.txt', 'content2');
        mkdir($this->testDir . '/subdir');

        $result = $tool->execute([
            'operation' => 'list',
            'path' => $this->testDir,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(3, $data['count']);
    }

    public function testFileExists(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        file_put_contents($this->testDir . '/exists.txt', 'content');

        $result = $tool->execute([
            'operation' => 'exists',
            'path' => $this->testDir . '/exists.txt',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['exists']);
    }

    public function testDeleteFile(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $filePath = $this->testDir . '/delete_me.txt';
        file_put_contents($filePath, 'content');

        $result = $tool->execute([
            'operation' => 'delete',
            'path' => $filePath,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertFileDoesNotExist($filePath);
    }

    public function testMkdir(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $newDir = $this->testDir . '/newdir';

        $result = $tool->execute([
            'operation' => 'mkdir',
            'path' => $newDir,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertDirectoryExists($newDir);
    }

    public function testFileInfo(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $filePath = $this->testDir . '/info.txt';
        file_put_contents($filePath, 'content');

        $result = $tool->execute([
            'operation' => 'info',
            'path' => $filePath,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals('file', $data['type']);
        $this->assertArrayHasKey('size', $data);
        $this->assertArrayHasKey('permissions', $data);
    }

    public function testPathRestriction(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $result = $tool->execute([
            'operation' => 'read',
            'path' => '/etc/passwd',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('access denied', strtolower($result->getContent()));
    }

    public function testFileSizeLimit(): void
    {
        $tool = FileSystemTool::create([
            'allowed_paths' => [$this->testDir],
            'max_file_size' => 100,
        ]);

        $filePath = $this->testDir . '/large.txt';
        $largeContent = str_repeat('x', 200);

        $result = $tool->execute([
            'operation' => 'write',
            'path' => $filePath,
            'content' => $largeContent,
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('too large', strtolower($result->getContent()));
    }

    public function testRecursiveList(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        // Create nested structure
        mkdir($this->testDir . '/dir1');
        mkdir($this->testDir . '/dir1/dir2');
        file_put_contents($this->testDir . '/dir1/file.txt', 'content');
        file_put_contents($this->testDir . '/dir1/dir2/file2.txt', 'content');

        $result = $tool->execute([
            'operation' => 'list',
            'path' => $this->testDir,
            'recursive' => true,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertGreaterThan(3, $data['count']);
    }

    public function testFileNotFound(): void
    {
        $tool = FileSystemTool::create(['allowed_paths' => [$this->testDir]]);

        $result = $tool->execute([
            'operation' => 'read',
            'path' => $this->testDir . '/nonexistent.txt',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('not found', strtolower($result->getContent()));
    }
}
