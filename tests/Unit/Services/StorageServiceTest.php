<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Storage\LocalStorageService;
use PHPUnit\Framework\TestCase;

class StorageServiceTest extends TestCase
{
    private LocalStorageService $service;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/claude-agent-test-' . uniqid();

        $settings = new SettingsService(null, [
            'storage.directory' => $this->testDir,
        ]);
        $settings->initialize();

        $this->service = new LocalStorageService($settings);
        $this->service->initialize();
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (file_exists($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    public function testGetName(): void
    {
        $this->assertSame('storage', $this->service->getName());
    }

    public function testSaveAndGetFile(): void
    {
        $data = 'test content';

        $this->service->saveFile('user-123', 'test.txt', $data);
        $retrieved = $this->service->getFile('user-123', 'test.txt');

        $this->assertSame($data, $retrieved);
    }

    public function testFileExists(): void
    {
        $this->assertFalse($this->service->fileExists('user-123', 'test.txt'));

        $this->service->saveFile('user-123', 'test.txt', 'data');

        $this->assertTrue($this->service->fileExists('user-123', 'test.txt'));
    }

    public function testListFiles(): void
    {
        $this->service->saveFile('user-123', 'file1.txt', 'data1');
        $this->service->saveFile('user-123', 'file2.txt', 'data2');
        $this->service->saveFile('user-123', 'subdir/file3.txt', 'data3');

        $files = $this->service->listFiles('user-123');

        $this->assertCount(3, $files);
        $this->assertContains('file1.txt', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertContains('subdir/file3.txt', $files);
    }

    public function testDeleteFile(): void
    {
        $this->service->saveFile('user-123', 'test.txt', 'data');
        $this->assertTrue($this->service->fileExists('user-123', 'test.txt'));

        $this->service->deleteFile('user-123', 'test.txt');

        $this->assertFalse($this->service->fileExists('user-123', 'test.txt'));
    }

    public function testGetFileSize(): void
    {
        $data = 'test content';
        $this->service->saveFile('user-123', 'test.txt', $data);

        $size = $this->service->getFileSize('user-123', 'test.txt');

        $this->assertSame(strlen($data), $size);
    }

    public function testBuildPath(): void
    {
        $path = $this->service->buildPath('user-123', 'test.txt');

        $this->assertStringContainsString('user-123', $path);
        $this->assertStringContainsString('test.txt', $path);
    }

    public function testParsePath(): void
    {
        $path = $this->service->buildPath('user-123', 'subdir/test.txt');
        $parsed = $this->service->parsePath($path);

        $this->assertSame('user-123', $parsed['flowId']);
        $this->assertSame('subdir/test.txt', $parsed['fileName']);
    }

    public function testGetNonexistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->service->getFile('user-123', 'nonexistent.txt');
    }

    private function removeDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
