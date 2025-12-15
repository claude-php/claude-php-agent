<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\State;

use ClaudeAgents\State\StateConfig;
use PHPUnit\Framework\TestCase;

class StateConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = StateConfig::default();

        $this->assertEquals(1000, $config->maxConversationHistory);
        $this->assertEquals(1000, $config->maxActionHistory);
        $this->assertFalse($config->compressHistory);
        $this->assertTrue($config->atomicWrites);
        $this->assertEquals(5, $config->backupRetention);
        $this->assertEquals(1, $config->version);
    }

    public function testUnlimitedConfig(): void
    {
        $config = StateConfig::unlimited();

        $this->assertEquals(0, $config->maxConversationHistory);
        $this->assertEquals(0, $config->maxActionHistory);
    }

    public function testProductionConfig(): void
    {
        $config = StateConfig::production();

        $this->assertEquals(500, $config->maxConversationHistory);
        $this->assertEquals(500, $config->maxActionHistory);
        $this->assertTrue($config->compressHistory);
        $this->assertTrue($config->atomicWrites);
        $this->assertEquals(10, $config->backupRetention);
    }

    public function testCustomConfig(): void
    {
        $config = new StateConfig(
            maxConversationHistory: 100,
            maxActionHistory: 50,
            compressHistory: true,
            atomicWrites: false,
            backupRetention: 3,
            version: 2,
        );

        $this->assertEquals(100, $config->maxConversationHistory);
        $this->assertEquals(50, $config->maxActionHistory);
        $this->assertTrue($config->compressHistory);
        $this->assertFalse($config->atomicWrites);
        $this->assertEquals(3, $config->backupRetention);
        $this->assertEquals(2, $config->version);
    }

    public function testNegativeMaxConversationHistoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxConversationHistory must be non-negative');

        new StateConfig(maxConversationHistory: -1);
    }

    public function testNegativeMaxActionHistoryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxActionHistory must be non-negative');

        new StateConfig(maxActionHistory: -1);
    }

    public function testNegativeBackupRetentionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('backupRetention must be non-negative');

        new StateConfig(backupRetention: -1);
    }

    public function testZeroVersionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('version must be at least 1');

        new StateConfig(version: 0);
    }

    public function testReadonlyProperties(): void
    {
        $config = StateConfig::default();

        $this->assertIsInt($config->maxConversationHistory);
        $this->assertIsInt($config->maxActionHistory);
        $this->assertIsBool($config->compressHistory);
        $this->assertIsBool($config->atomicWrites);
        $this->assertIsInt($config->backupRetention);
        $this->assertIsInt($config->version);
    }
}
