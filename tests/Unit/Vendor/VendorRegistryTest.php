<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\VendorConfig;
use ClaudeAgents\Vendor\VendorRegistry;
use PHPUnit\Framework\TestCase;

class VendorRegistryTest extends TestCase
{
    public function testRegisterAndRetrieveKey(): void
    {
        $registry = new VendorRegistry();
        $registry->registerKey('openai', 'sk-test-key');

        $this->assertEquals('sk-test-key', $registry->getKey('openai'));
    }

    public function testGetKeyThrowsForUnregisteredVendor(): void
    {
        $registry = new VendorRegistry();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("API key for vendor 'openai' is not registered");

        $registry->getKey('openai');
    }

    public function testIsAvailable(): void
    {
        $registry = new VendorRegistry();

        $this->assertFalse($registry->isAvailable('openai'));

        $registry->registerKey('openai', 'sk-test');
        $this->assertTrue($registry->isAvailable('openai'));
    }

    public function testIsAvailableReturnsFalseForEmptyKey(): void
    {
        $registry = new VendorRegistry();
        $registry->registerKey('openai', '');

        $this->assertFalse($registry->isAvailable('openai'));
    }

    public function testGetAvailableVendors(): void
    {
        $registry = new VendorRegistry();
        $registry->registerKey('openai', 'sk-openai');
        $registry->registerKey('google', 'key-google');

        $vendors = $registry->getAvailableVendors();

        $this->assertCount(2, $vendors);
        $this->assertContains('openai', $vendors);
        $this->assertContains('google', $vendors);
    }

    public function testGetAvailableVendorsEmpty(): void
    {
        $registry = new VendorRegistry();

        $this->assertEmpty($registry->getAvailableVendors());
    }

    public function testSetAndGetConfig(): void
    {
        $registry = new VendorRegistry();
        $config = new VendorConfig(
            vendor: 'openai',
            defaultChatModel: 'gpt-5.2-pro',
            timeout: 60.0,
        );

        $registry->setConfig('openai', $config);

        $retrieved = $registry->getConfig('openai');
        $this->assertNotNull($retrieved);
        $this->assertEquals('gpt-5.2-pro', $retrieved->defaultChatModel);
        $this->assertEquals(60.0, $retrieved->timeout);
    }

    public function testGetConfigReturnsNullWhenNotSet(): void
    {
        $registry = new VendorRegistry();

        $this->assertNull($registry->getConfig('openai'));
    }

    public function testHasExternalVendors(): void
    {
        $registry = new VendorRegistry();

        $this->assertFalse($registry->hasExternalVendors());

        $registry->registerKey('anthropic', 'sk-ant-test');
        $this->assertFalse($registry->hasExternalVendors());

        $registry->registerKey('openai', 'sk-openai');
        $this->assertTrue($registry->hasExternalVendors());
    }

    public function testGetEnvVarName(): void
    {
        $this->assertEquals('ANTHROPIC_API_KEY', VendorRegistry::getEnvVarName('anthropic'));
        $this->assertEquals('OPENAI_API_KEY', VendorRegistry::getEnvVarName('openai'));
        $this->assertEquals('GEMINI_API_KEY', VendorRegistry::getEnvVarName('google'));
    }

    public function testGetEnvVarNameUnknownVendor(): void
    {
        // Unknown vendors get UPPERCASED_API_KEY
        $this->assertEquals('MISTRAL_API_KEY', VendorRegistry::getEnvVarName('mistral'));
    }

    public function testRegisterMultipleVendors(): void
    {
        $registry = new VendorRegistry();
        $registry->registerKey('anthropic', 'sk-ant');
        $registry->registerKey('openai', 'sk-openai');
        $registry->registerKey('google', 'key-google');

        $this->assertTrue($registry->isAvailable('anthropic'));
        $this->assertTrue($registry->isAvailable('openai'));
        $this->assertTrue($registry->isAvailable('google'));
        $this->assertCount(3, $registry->getAvailableVendors());
    }

    public function testOverwriteKey(): void
    {
        $registry = new VendorRegistry();
        $registry->registerKey('openai', 'old-key');
        $registry->registerKey('openai', 'new-key');

        $this->assertEquals('new-key', $registry->getKey('openai'));
    }
}
