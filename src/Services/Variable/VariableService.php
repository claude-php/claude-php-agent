<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Variable;

use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\Settings\SettingsService;
use ClaudeAgents\Services\Storage\StorageService;

/**
 * Variable service for managing user-scoped variables and secrets.
 *
 * Supports multiple backends and encryption for sensitive values.
 */
class VariableService implements ServiceInterface
{
    private bool $ready = false;

    /**
     * @var array<string, array<string, Variable>> In-memory cache of variables [userId => [key => Variable]]
     */
    private array $variables = [];

    private ?string $encryptionKey = null;

    /**
     * @param SettingsService $settings Settings service
     * @param StorageService $storage Storage service for persistence
     */
    public function __construct(
        private SettingsService $settings,
        private StorageService $storage
    ) {
    }

    public function getName(): string
    {
        return 'variable';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        // Get encryption key from settings
        $this->encryptionKey = $this->settings->get('variable.encryption_key');

        // If no key provided, generate one
        if ($this->encryptionKey === null || $this->encryptionKey === '') {
            $this->encryptionKey = $this->generateEncryptionKey();
        }

        $this->ready = true;
    }

    public function teardown(): void
    {
        $this->variables = [];
        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'getVariable' => [
                    'parameters' => ['userId' => 'string', 'key' => 'string'],
                    'return' => 'mixed',
                    'description' => 'Get a variable value',
                ],
                'setVariable' => [
                    'parameters' => ['userId' => 'string', 'key' => 'string', 'value' => 'mixed', 'type' => 'string'],
                    'return' => 'void',
                    'description' => 'Set a variable value',
                ],
                'deleteVariable' => [
                    'parameters' => ['userId' => 'string', 'key' => 'string'],
                    'return' => 'void',
                    'description' => 'Delete a variable',
                ],
                'listVariables' => [
                    'parameters' => ['userId' => 'string'],
                    'return' => 'array',
                    'description' => 'List all variables for a user',
                ],
            ],
        ];
    }

    /**
     * Get a variable value for a user.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @return mixed Variable value
     * @throws \RuntimeException If variable not found
     */
    public function getVariable(string $userId, string $key): mixed
    {
        $variable = $this->loadVariable($userId, $key);

        if ($variable === null) {
            throw new \RuntimeException("Variable not found: {$key}");
        }

        // Decrypt if credential type
        if ($variable->type === VariableType::CREDENTIAL) {
            return $this->decrypt($variable->value);
        }

        return $variable->value;
    }

    /**
     * Set a variable value for a user.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @param mixed $value Variable value
     * @param VariableType $type Variable type (credential or generic)
     * @return void
     */
    public function setVariable(
        string $userId,
        string $key,
        mixed $value,
        VariableType $type = VariableType::GENERIC
    ): void {
        // Encrypt if credential type
        $storedValue = $type === VariableType::CREDENTIAL
            ? $this->encrypt((string) $value)
            : $value;

        $variable = new Variable(
            key: $key,
            value: $storedValue,
            type: $type,
            updatedAt: time()
        );

        // Cache in memory
        if (! isset($this->variables[$userId])) {
            $this->variables[$userId] = [];
        }
        $this->variables[$userId][$key] = $variable;

        // Persist to storage
        $this->saveVariable($userId, $key, $variable);
    }

    /**
     * Delete a variable for a user.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @return void
     */
    public function deleteVariable(string $userId, string $key): void
    {
        // Remove from cache
        unset($this->variables[$userId][$key]);

        // Delete from storage
        try {
            $this->storage->deleteFile("variables/{$userId}", "{$key}.json");
        } catch (\RuntimeException $e) {
            // File might not exist, that's ok
        }
    }

    /**
     * List all variables for a user.
     *
     * @param string $userId User identifier
     * @return array<string> Array of variable keys
     */
    public function listVariables(string $userId): array
    {
        $this->loadAllVariables($userId);

        return array_keys($this->variables[$userId] ?? []);
    }

    /**
     * Check if a variable exists.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @return bool True if variable exists
     */
    public function hasVariable(string $userId, string $key): bool
    {
        return $this->loadVariable($userId, $key) !== null;
    }

    /**
     * Get all variables for a user with decrypted values.
     *
     * @param string $userId User identifier
     * @return array<string, mixed> Array of [key => value]
     */
    public function getAllVariables(string $userId): array
    {
        $this->loadAllVariables($userId);

        $result = [];
        foreach ($this->variables[$userId] ?? [] as $key => $variable) {
            $result[$key] = $variable->type === VariableType::CREDENTIAL
                ? $this->decrypt($variable->value)
                : $variable->value;
        }

        return $result;
    }

    /**
     * Load a variable from storage or cache.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @return Variable|null Variable or null if not found
     */
    private function loadVariable(string $userId, string $key): ?Variable
    {
        // Check cache first
        if (isset($this->variables[$userId][$key])) {
            return $this->variables[$userId][$key];
        }

        // Load from storage
        try {
            $data = $this->storage->getFile("variables/{$userId}", "{$key}.json");
            $decoded = json_decode($data, true);

            if (! is_array($decoded)) {
                return null;
            }

            $variable = Variable::fromArray($decoded);

            // Cache in memory
            if (! isset($this->variables[$userId])) {
                $this->variables[$userId] = [];
            }
            $this->variables[$userId][$key] = $variable;

            return $variable;
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Load all variables for a user from storage.
     *
     * @param string $userId User identifier
     * @return void
     */
    private function loadAllVariables(string $userId): void
    {
        if (isset($this->variables[$userId])) {
            return; // Already loaded
        }

        $this->variables[$userId] = [];

        try {
            $files = $this->storage->listFiles("variables/{$userId}");

            foreach ($files as $file) {
                if (! str_ends_with($file, '.json')) {
                    continue;
                }

                $key = basename($file, '.json');
                $this->loadVariable($userId, $key);
            }
        } catch (\RuntimeException $e) {
            // Directory might not exist, that's ok
        }
    }

    /**
     * Save a variable to storage.
     *
     * @param string $userId User identifier
     * @param string $key Variable key
     * @param Variable $variable Variable to save
     * @return void
     */
    private function saveVariable(string $userId, string $key, Variable $variable): void
    {
        $data = json_encode($variable->toArray(), JSON_PRETTY_PRINT);
        $this->storage->saveFile("variables/{$userId}", "{$key}.json", $data);
    }

    /**
     * Encrypt a value.
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    private function encrypt(string $value): string
    {
        if ($this->encryptionKey === null) {
            throw new \RuntimeException('Encryption key not configured');
        }

        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false) {
            throw new \RuntimeException('Failed to get IV length');
        }

        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $value,
            $cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Combine IV, tag, and encrypted data
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt a value.
     *
     * @param string $encrypted Encrypted value
     * @return string Decrypted value
     */
    private function decrypt(string $encrypted): string
    {
        if ($this->encryptionKey === null) {
            throw new \RuntimeException('Encryption key not configured');
        }

        $data = base64_decode($encrypted);
        if ($data === false) {
            throw new \RuntimeException('Failed to decode encrypted data');
        }

        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false) {
            throw new \RuntimeException('Failed to get IV length');
        }

        $tagLength = 16;

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $ciphertext = substr($data, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            $cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Generate a random encryption key.
     *
     * @return string Base64-encoded encryption key
     */
    private function generateEncryptionKey(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }
}
