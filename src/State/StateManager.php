<?php

declare(strict_types=1);

namespace ClaudeAgents\State;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Manages persistence of agent state across sessions.
 */
class StateManager
{
    private LoggerInterface $logger;
    private StateConfig $config;

    /**
     * @param string $stateFile Path to the state file
     * @param array<string, mixed> $options Configuration options:
     *   - logger: PSR-3 logger instance
     *   - config: StateConfig instance
     *   - atomic_writes: Enable atomic writes (default: true)
     */
    public function __construct(
        private readonly string $stateFile,
        array $options = [],
    ) {
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->config = $options['config'] ?? StateConfig::default();

        // Override atomic writes if specified in options
        if (isset($options['atomic_writes'])) {
            $this->config = new StateConfig(
                maxConversationHistory: $this->config->maxConversationHistory,
                maxActionHistory: $this->config->maxActionHistory,
                compressHistory: $this->config->compressHistory,
                atomicWrites: (bool) $options['atomic_writes'],
                backupRetention: $this->config->backupRetention,
                version: $this->config->version,
            );
        }
    }

    /**
     * Load state from file.
     *
     * @return AgentState|null Loaded state, or null if file doesn't exist
     */
    public function load(): ?AgentState
    {
        if (! file_exists($this->stateFile)) {
            $this->logger->debug("State file not found: {$this->stateFile}");

            return null;
        }

        try {
            $contents = file_get_contents($this->stateFile);
            if ($contents === false) {
                $this->logger->error("Failed to read state file: {$this->stateFile}");

                return null;
            }

            $data = json_decode($contents, true);
            if (! is_array($data)) {
                $this->logger->error("Invalid state file format: {$this->stateFile}");

                return null;
            }

            $this->logger->debug("Loaded state from: {$this->stateFile}", [
                'state_id' => $data['id'] ?? 'unknown',
                'version' => $data['version'] ?? 'unknown',
            ]);

            return AgentState::createFromArray($data);
        } catch (\Throwable $e) {
            $this->logger->error("Error loading state: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Save state to file with optional backup.
     */
    public function save(AgentState $state, bool $createBackup = true): bool
    {
        try {
            $data = $state->toArray();
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if ($json === false) {
                $this->logger->error('Failed to encode state to JSON');

                return false;
            }

            // Create directory if it doesn't exist
            $directory = dirname($this->stateFile);
            if (! file_exists($directory)) {
                if (! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
                    $this->logger->error("Failed to create directory: {$directory}");

                    return false;
                }
            }

            // Create backup if file exists and backup is enabled
            if ($createBackup && file_exists($this->stateFile) && $this->config->backupRetention > 0) {
                $this->createBackup();
            }

            // Use atomic write if enabled
            if ($this->config->atomicWrites) {
                $success = $this->atomicWrite($json);
            } else {
                $success = file_put_contents($this->stateFile, $json) !== false;
            }

            if (! $success) {
                $this->logger->error("Failed to write state file: {$this->stateFile}");

                return false;
            }

            $this->logger->debug("Saved state to: {$this->stateFile}", [
                'state_id' => $state->getStateId(),
                'size_bytes' => strlen($json),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error("Error saving state: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Perform atomic write using temp file + rename strategy.
     */
    private function atomicWrite(string $content): bool
    {
        $tempFile = $this->stateFile . '.tmp.' . uniqid();

        try {
            // Write to temp file
            if (file_put_contents($tempFile, $content) === false) {
                return false;
            }

            // Atomic rename
            if (! rename($tempFile, $this->stateFile)) {
                @unlink($tempFile);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            @unlink($tempFile);

            throw $e;
        }
    }

    /**
     * Delete the state file.
     */
    public function delete(): bool
    {
        if (! file_exists($this->stateFile)) {
            return true;
        }

        try {
            if (unlink($this->stateFile)) {
                $this->logger->debug("Deleted state file: {$this->stateFile}");

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Error deleting state: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if state file exists.
     */
    public function exists(): bool
    {
        return file_exists($this->stateFile);
    }

    /**
     * Get the state file path.
     */
    public function getStateFile(): string
    {
        return $this->stateFile;
    }

    /**
     * Create a backup of the current state file.
     */
    public function createBackup(): bool
    {
        if (! file_exists($this->stateFile)) {
            $this->logger->debug('No state file to backup');

            return false;
        }

        try {
            $timestamp = date('Y-m-d_His');
            $backupFile = $this->stateFile . '.backup.' . $timestamp;

            if (! copy($this->stateFile, $backupFile)) {
                $this->logger->error("Failed to create backup: {$backupFile}");

                return false;
            }

            $this->logger->debug("Created backup: {$backupFile}");

            // Clean up old backups
            $this->cleanupOldBackups();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error("Error creating backup: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Restore state from a backup file.
     */
    public function restore(string $backupFile): ?AgentState
    {
        if (! file_exists($backupFile)) {
            $this->logger->error("Backup file not found: {$backupFile}");

            return null;
        }

        try {
            $contents = file_get_contents($backupFile);
            if ($contents === false) {
                $this->logger->error("Failed to read backup file: {$backupFile}");

                return null;
            }

            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                $this->logger->error("Invalid backup file format: {$backupFile}");

                return null;
            }

            $state = AgentState::createFromArray($data);

            // Save the restored state
            if ($this->save($state, false)) {
                $this->logger->info("Restored state from backup: {$backupFile}");

                return $state;
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error("Error restoring from backup: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * List all available backup files.
     *
     * @return array<string>
     */
    public function listBackups(): array
    {
        $pattern = $this->stateFile . '.backup.*';
        $backups = glob($pattern);

        if ($backups === false) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($backups, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        return $backups;
    }

    /**
     * Get the most recent backup file.
     */
    public function getLatestBackup(): ?string
    {
        $backups = $this->listBackups();

        return $backups[0] ?? null;
    }

    /**
     * Restore from the most recent backup.
     */
    public function restoreLatest(): ?AgentState
    {
        $latestBackup = $this->getLatestBackup();

        if ($latestBackup === null) {
            $this->logger->warning('No backup files found');

            return null;
        }

        return $this->restore($latestBackup);
    }

    /**
     * Clean up old backup files based on retention policy.
     */
    private function cleanupOldBackups(): void
    {
        if ($this->config->backupRetention <= 0) {
            return;
        }

        $backups = $this->listBackups();

        // Keep only the configured number of backups
        $toDelete = array_slice($backups, $this->config->backupRetention);

        foreach ($toDelete as $backup) {
            if (@unlink($backup)) {
                $this->logger->debug("Deleted old backup: {$backup}");
            }
        }
    }

    /**
     * Delete all backup files.
     */
    public function deleteAllBackups(): int
    {
        $backups = $this->listBackups();
        $deleted = 0;

        foreach ($backups as $backup) {
            if (@unlink($backup)) {
                $deleted++;
                $this->logger->debug("Deleted backup: {$backup}");
            }
        }

        return $deleted;
    }

    /**
     * Get configuration.
     */
    public function getConfig(): StateConfig
    {
        return $this->config;
    }
}
