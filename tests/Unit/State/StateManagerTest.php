<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\State;

use ClaudeAgents\State\AgentState;
use ClaudeAgents\State\Goal;
use ClaudeAgents\State\StateConfig;
use ClaudeAgents\State\StateManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StateManagerTest extends TestCase
{
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFile = sys_get_temp_dir() . '/test_state_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }

        // Cleanup any leftover directories
        $pattern = sys_get_temp_dir() . '/test_state_dir_*';
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

    public function testLoadReturnsNullWhenFileDoesNotExist(): void
    {
        $manager = new StateManager($this->testFile);

        $state = $manager->load();

        $this->assertNull($state);
    }

    public function testSaveAndLoad(): void
    {
        $goal = new Goal('Test goal');
        $goal->setStatus('in_progress');
        $goal->setProgressPercentage(50);

        $state = new AgentState(sessionNumber: 1, goal: $goal);
        $state->addMessage(['role' => 'user', 'content' => 'Hello']);
        $state->setMetadataValue('key', 'value');

        $manager = new StateManager($this->testFile);
        $saved = $manager->save($state);

        $this->assertTrue($saved);
        $this->assertTrue($manager->exists());

        $loadedState = $manager->load();

        $this->assertNotNull($loadedState);
        $this->assertEquals(1, $loadedState->getSessionNumber());
        $this->assertEquals('Test goal', $loadedState->getGoal()->getDescription());
        $this->assertEquals('in_progress', $loadedState->getGoal()->getStatus());
        $this->assertCount(1, $loadedState->getConversationHistory());
        $this->assertEquals('value', $loadedState->getMetadataValue('key'));
    }

    public function testSaveCreatesDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/test_state_dir_' . uniqid();
        $filePath = $dir . '/state.json';

        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($filePath);
        $result = $manager->save($state);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($dir));
        $this->assertTrue(file_exists($filePath));

        // Cleanup
        unlink($filePath);
        rmdir($dir);
    }

    public function testDelete(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state);

        $this->assertTrue($manager->exists());

        $deleted = $manager->delete();

        $this->assertTrue($deleted);
        $this->assertFalse($manager->exists());
        $this->assertFalse(file_exists($this->testFile));
    }

    public function testDeleteNonExistentFile(): void
    {
        $manager = new StateManager($this->testFile);

        $result = $manager->delete();

        $this->assertTrue($result);
    }

    public function testExists(): void
    {
        $manager = new StateManager($this->testFile);

        $this->assertFalse($manager->exists());

        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);
        $manager->save($state);

        $this->assertTrue($manager->exists());
    }

    public function testGetStateFile(): void
    {
        $manager = new StateManager($this->testFile);

        $this->assertEquals($this->testFile, $manager->getStateFile());
    }

    public function testLoadWithInvalidJson(): void
    {
        file_put_contents($this->testFile, 'invalid json{');

        $manager = new StateManager($this->testFile);
        $state = $manager->load();

        $this->assertNull($state);
    }

    public function testLoadWithEmptyFile(): void
    {
        file_put_contents($this->testFile, '');

        $manager = new StateManager($this->testFile);
        $state = $manager->load();

        $this->assertNull($state);
    }

    public function testWithLogger(): void
    {
        $logger = new NullLogger();
        $manager = new StateManager($this->testFile, ['logger' => $logger]);

        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $result = $manager->save($state);

        $this->assertTrue($result);
    }

    public function testRoundTripSerialization(): void
    {
        $goal = new Goal('Complex goal');
        $goal->setStatus('in_progress');
        $goal->setProgressPercentage(75);
        $goal->completeSubgoal('Step 1');
        $goal->completeSubgoal('Step 2');
        $goal->setMetadataValue('priority', 'high');

        $state = new AgentState(sessionNumber: 5, goal: $goal);
        $state->addMessage(['role' => 'user', 'content' => 'Message 1']);
        $state->addMessage(['role' => 'assistant', 'content' => 'Response 1']);
        $state->recordAction(['tool' => 'calculator', 'result' => 42]);
        $state->setMetadataValue('context', 'test');

        $manager = new StateManager($this->testFile);
        $manager->save($state);

        $loaded = $manager->load();

        $this->assertNotNull($loaded);
        $this->assertEquals($state->getSessionNumber(), $loaded->getSessionNumber());
        $this->assertEquals($state->getGoal()->getDescription(), $loaded->getGoal()->getDescription());
        $this->assertEquals($state->getGoal()->getStatus(), $loaded->getGoal()->getStatus());
        $this->assertEquals($state->getGoal()->getProgressPercentage(), $loaded->getGoal()->getProgressPercentage());
        $this->assertEquals($state->getGoal()->getCompletedSubgoals(), $loaded->getGoal()->getCompletedSubgoals());
        $this->assertCount(2, $loaded->getConversationHistory());
        $this->assertCount(1, $loaded->getActionHistory());
    }

    public function testAtomicWrites(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $config = new StateConfig(atomicWrites: true);
        $manager = new StateManager($this->testFile, ['config' => $config]);

        $result = $manager->save($state);

        $this->assertTrue($result);
        $this->assertTrue(file_exists($this->testFile));

        // Verify no temp files left behind
        $tempFiles = glob($this->testFile . '.tmp.*');
        $this->assertEmpty($tempFiles);
    }

    public function testBackupCreation(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);

        // First save
        $manager->save($state, false);

        // Second save with backup
        $state->incrementSession();
        $manager->save($state, true);

        $backups = $manager->listBackups();
        $this->assertNotEmpty($backups);
        $this->assertStringContainsString('.backup.', $backups[0]);
    }

    public function testListBackups(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state, false);

        // Create multiple backups
        for ($i = 0; $i < 3; $i++) {
            sleep(1); // Ensure different timestamps
            $manager->createBackup();
        }

        $backups = $manager->listBackups();
        $this->assertCount(3, $backups);
    }

    public function testGetLatestBackup(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state, false);

        $manager->createBackup();
        sleep(1);
        $manager->createBackup();

        $latest = $manager->getLatestBackup();
        $this->assertNotNull($latest);
        $this->assertStringContainsString('.backup.', $latest);
    }

    public function testRestore(): void
    {
        $goal = new Goal('Original goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state);

        $backupFile = $manager->createBackup() ? $manager->getLatestBackup() : null;
        $this->assertNotNull($backupFile);

        // Modify state
        $goal2 = new Goal('Modified goal');
        $state2 = new AgentState(sessionNumber: 2, goal: $goal2);
        $manager->save($state2, false);

        // Restore from backup
        $restored = $manager->restore($backupFile);

        $this->assertNotNull($restored);
        $this->assertEquals(1, $restored->getSessionNumber());
        $this->assertEquals('Original goal', $restored->getGoal()->getDescription());
    }

    public function testRestoreLatest(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state);
        $manager->createBackup();

        // Modify state
        $state->incrementSession();
        $manager->save($state, false);

        // Restore latest
        $restored = $manager->restoreLatest();

        $this->assertNotNull($restored);
        $this->assertEquals(1, $restored->getSessionNumber());
    }

    public function testBackupRetention(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $config = new StateConfig(backupRetention: 2);
        $manager = new StateManager($this->testFile, ['config' => $config]);

        $manager->save($state, false);

        // Create 5 backups
        for ($i = 0; $i < 5; $i++) {
            sleep(1);
            $manager->createBackup();
        }

        // Should only keep 2 most recent
        $backups = $manager->listBackups();
        $this->assertCount(2, $backups);
    }

    public function testDeleteAllBackups(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state, false);

        // Create backups
        for ($i = 0; $i < 3; $i++) {
            $manager->createBackup();
            sleep(1);
        }

        $deleted = $manager->deleteAllBackups();
        $this->assertEquals(3, $deleted);
        $this->assertEmpty($manager->listBackups());
    }

    public function testGetConfig(): void
    {
        $config = StateConfig::production();
        $manager = new StateManager($this->testFile, ['config' => $config]);

        $this->assertSame($config, $manager->getConfig());
    }

    public function testSaveWithoutBackup(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);

        $manager = new StateManager($this->testFile);
        $manager->save($state, false);

        $this->assertTrue(file_exists($this->testFile));
        $this->assertEmpty($manager->listBackups());
    }

    public function testRestoreNonexistentBackup(): void
    {
        $manager = new StateManager($this->testFile);

        $restored = $manager->restore('/nonexistent/backup.json');

        $this->assertNull($restored);
    }

    public function testRestoreLatestWithNoBackups(): void
    {
        $manager = new StateManager($this->testFile);

        $restored = $manager->restoreLatest();

        $this->assertNull($restored);
    }

    public function testSavePreservesStateId(): void
    {
        $goal = new Goal('Test goal');
        $state = new AgentState(sessionNumber: 1, goal: $goal);
        $originalId = $state->getId();

        $manager = new StateManager($this->testFile);
        $manager->save($state);

        $loaded = $manager->load();
        $this->assertNotNull($loaded);
        $this->assertEquals($originalId, $loaded->getId());
    }
}
