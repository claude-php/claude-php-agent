<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Services;

use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Storage\LocalStorageService;
use ClaudeAgents\Services\Variable\VariableService;
use ClaudeAgents\Services\Variable\VariableType;
use PHPUnit\Framework\TestCase;

class VariableServiceTest extends TestCase
{
    private VariableService $service;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/claude-agent-test-' . uniqid();

        $settings = new SettingsService(null, [
            'storage.directory' => $this->testDir,
            'variable.encryption_key' => base64_encode(random_bytes(32)),
        ]);
        $settings->initialize();

        $storage = new LocalStorageService($settings);
        $storage->initialize();

        $this->service = new VariableService($settings, $storage);
        $this->service->initialize();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    public function testGetName(): void
    {
        $this->assertSame('variable', $this->service->getName());
    }

    public function testSetAndGetGenericVariable(): void
    {
        $this->service->setVariable('user-123', 'theme', 'dark', VariableType::GENERIC);

        $value = $this->service->getVariable('user-123', 'theme');

        $this->assertSame('dark', $value);
    }

    public function testSetAndGetCredential(): void
    {
        $apiKey = 'sk-1234567890';

        $this->service->setVariable('user-123', 'api_key', $apiKey, VariableType::CREDENTIAL);

        $retrieved = $this->service->getVariable('user-123', 'api_key');

        $this->assertSame($apiKey, $retrieved);
    }

    public function testListVariables(): void
    {
        $this->service->setVariable('user-123', 'var1', 'value1', VariableType::GENERIC);
        $this->service->setVariable('user-123', 'var2', 'value2', VariableType::GENERIC);
        $this->service->setVariable('user-123', 'var3', 'value3', VariableType::CREDENTIAL);

        $variables = $this->service->listVariables('user-123');

        $this->assertCount(3, $variables);
        $this->assertContains('var1', $variables);
        $this->assertContains('var2', $variables);
        $this->assertContains('var3', $variables);
    }

    public function testDeleteVariable(): void
    {
        $this->service->setVariable('user-123', 'test', 'value', VariableType::GENERIC);
        $this->assertTrue($this->service->hasVariable('user-123', 'test'));

        $this->service->deleteVariable('user-123', 'test');

        $this->assertFalse($this->service->hasVariable('user-123', 'test'));
    }

    public function testHasVariable(): void
    {
        $this->assertFalse($this->service->hasVariable('user-123', 'test'));

        $this->service->setVariable('user-123', 'test', 'value', VariableType::GENERIC);

        $this->assertTrue($this->service->hasVariable('user-123', 'test'));
    }

    public function testGetAllVariables(): void
    {
        $this->service->setVariable('user-123', 'var1', 'value1', VariableType::GENERIC);
        $this->service->setVariable('user-123', 'var2', 'value2', VariableType::CREDENTIAL);

        $all = $this->service->getAllVariables('user-123');

        $this->assertArrayHasKey('var1', $all);
        $this->assertArrayHasKey('var2', $all);
        $this->assertSame('value1', $all['var1']);
        $this->assertSame('value2', $all['var2']);
    }

    public function testGetNonexistentVariable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Variable not found');

        $this->service->getVariable('user-123', 'nonexistent');
    }

    public function testUserIsolation(): void
    {
        $this->service->setVariable('user-1', 'key', 'value1', VariableType::GENERIC);
        $this->service->setVariable('user-2', 'key', 'value2', VariableType::GENERIC);

        $value1 = $this->service->getVariable('user-1', 'key');
        $value2 = $this->service->getVariable('user-2', 'key');

        $this->assertSame('value1', $value1);
        $this->assertSame('value2', $value2);
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
