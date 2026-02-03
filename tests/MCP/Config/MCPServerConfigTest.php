<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\MCP\Config;

use ClaudeAgents\MCP\Config\MCPServerConfig;
use PHPUnit\Framework\TestCase;

class MCPServerConfigTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new MCPServerConfig();
        
        $this->assertEquals('claude-php-agent', $config->serverName);
        $this->assertEquals('1.0.0', $config->serverVersion);
        $this->assertEquals('stdio', $config->transport);
        $this->assertEquals(3600, $config->sessionTimeout);
        $this->assertTrue($config->enableVisualization);
    }

    public function testCustomConfiguration(): void
    {
        $config = new MCPServerConfig(
            serverName: 'custom-server',
            transport: 'sse',
            ssePort: 9000,
        );
        
        $this->assertEquals('custom-server', $config->serverName);
        $this->assertEquals('sse', $config->transport);
        $this->assertEquals(9000, $config->ssePort);
    }

    public function testFromArray(): void
    {
        $config = MCPServerConfig::fromArray([
            'server_name' => 'array-server',
            'transport' => 'sse',
            'sse_port' => 8888,
        ]);
        
        $this->assertEquals('array-server', $config->serverName);
        $this->assertEquals('sse', $config->transport);
        $this->assertEquals(8888, $config->ssePort);
    }

    public function testToArray(): void
    {
        $config = new MCPServerConfig(
            serverName: 'test-server',
        );
        
        $array = $config->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('test-server', $array['server_name']);
        $this->assertArrayHasKey('transport', $array);
    }

    public function testIsToolEnabled(): void
    {
        $config = new MCPServerConfig(
            toolsEnabled: ['tool1', 'tool2'],
        );
        
        $this->assertTrue($config->isToolEnabled('tool1'));
        $this->assertTrue($config->isToolEnabled('tool2'));
        $this->assertFalse($config->isToolEnabled('tool3'));
    }

    public function testIsToolEnabledAllEnabled(): void
    {
        $config = new MCPServerConfig(
            toolsEnabled: [],
        );
        
        // Empty toolsEnabled means all tools are enabled
        $this->assertTrue($config->isToolEnabled('any_tool'));
    }

    public function testValidateValidConfig(): void
    {
        $config = new MCPServerConfig(
            transport: 'stdio',
        );
        
        $this->expectNotToPerformAssertions();
        $config->validate();
    }

    public function testValidateInvalidTransport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid transport');
        
        $config = new MCPServerConfig(
            transport: 'invalid',
        );
        
        $config->validate();
    }

    public function testValidateInvalidSessionTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $config = new MCPServerConfig(
            sessionTimeout: -1,
        );
        
        $config->validate();
    }

    public function testValidateInvalidPort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $config = new MCPServerConfig(
            ssePort: 99999,
        );
        
        $config->validate();
    }
}
