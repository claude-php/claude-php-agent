# VariableService Tutorial

Learn how to securely manage variables, secrets, and API keys with the VariableService.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Variable Types](#variable-types)
- [Encryption](#encryption)
- [User Scoping](#user-scoping)
- [Common Patterns](#common-patterns)
- [Security Best Practices](#security-best-practices)

## Overview

The VariableService provides secure storage for user-scoped variables and sensitive credentials with automatic encryption.

**Features:**
- User-scoped variables
- Automatic encryption for credentials (AES-256-GCM)
- Multiple variable types (Generic vs Credential)
- Persistent storage
- Simple key-value interface

## Basic Usage

### Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Variable\VariableServiceFactory;
use ClaudeAgents\Services\Variable\VariableType;

$manager = ServiceManager::getInstance();
$manager->registerFactory(new VariableServiceFactory());

$variables = $manager->get(ServiceType::VARIABLE);
```

### Store and Retrieve

```php
// Store a variable
$variables->setVariable(
    'user-123',           // User ID
    'theme',              // Variable key
    'dark',               // Value
    VariableType::GENERIC // Type
);

// Retrieve a variable
$theme = $variables->getVariable('user-123', 'theme');
echo $theme; // 'dark'
```

### List and Delete

```php
// List all variables for a user
$keys = $variables->listVariables('user-123');
// ['theme', 'language', 'api_key']

// Check if variable exists
if ($variables->hasVariable('user-123', 'theme')) {
    echo "Theme is set";
}

// Delete a variable
$variables->deleteVariable('user-123', 'theme');
```

## Variable Types

### Generic Variables

Use for non-sensitive configuration data:

```php
use ClaudeAgents\Services\Variable\VariableType;

// User preferences
$variables->setVariable('user-123', 'theme', 'dark', VariableType::GENERIC);
$variables->setVariable('user-123', 'language', 'en', VariableType::GENERIC);
$variables->setVariable('user-123', 'timezone', 'UTC', VariableType::GENERIC);

// Application settings
$variables->setVariable('app', 'version', '1.0.0', VariableType::GENERIC);
$variables->setVariable('app', 'debug', 'false', VariableType::GENERIC);
```

**Characteristics:**
- Stored as plain text
- Fast to read/write
- Suitable for non-sensitive data
- Can be visible in logs/debugging

### Credential Variables

Use for sensitive data like API keys and passwords:

```php
use ClaudeAgents\Services\Variable\VariableType;

// API keys
$variables->setVariable(
    'user-123',
    'anthropic_api_key',
    'sk-ant-api03-xxx',
    VariableType::CREDENTIAL
);

// Passwords
$variables->setVariable(
    'user-123',
    'database_password',
    'super-secret-password',
    VariableType::CREDENTIAL
);

// Tokens
$variables->setVariable(
    'user-123',
    'github_token',
    'ghp_xxxxxxxxxxxx',
    VariableType::CREDENTIAL
);
```

**Characteristics:**
- Automatically encrypted at rest (AES-256-GCM)
- Decrypted when retrieved
- Hidden from logs
- More secure storage

## Encryption

### Automatic Encryption

Credentials are automatically encrypted:

```php
// Store (automatically encrypted)
$variables->setVariable(
    'user-123',
    'api_key',
    'sk-1234567890',
    VariableType::CREDENTIAL
);

// Retrieve (automatically decrypted)
$apiKey = $variables->getVariable('user-123', 'api_key');
echo $apiKey; // 'sk-1234567890' (decrypted)
```

### Configure Encryption Key

Set a custom encryption key in your configuration:

```php
// config/services.php
return [
    'variable' => [
        'encryption_key' => base64_encode(random_bytes(32)),
    ],
];
```

**⚠️ Important:** Keep your encryption key secret and secure!

```php
// .env
CLAUDE_AGENT_VARIABLE_ENCRYPTION_KEY=your-base64-encoded-key

// Or generate programmatically
$key = base64_encode(random_bytes(32));
```

### Key Rotation

To rotate encryption keys, you'll need to decrypt and re-encrypt:

```php
function rotateEncryptionKey(
    string $userId,
    string $oldKey,
    string $newKey
): void {
    // Initialize with old key
    $oldVariables = new VariableService($oldSettings, $storage);
    $oldVariables->initialize();
    
    // Get all variables
    $allVars = $oldVariables->getAllVariables($userId);
    
    // Initialize with new key
    $newSettings->set('variable.encryption_key', $newKey);
    $newVariables = new VariableService($newSettings, $storage);
    $newVariables->initialize();
    
    // Re-encrypt each variable
    foreach ($allVars as $key => $value) {
        $newVariables->setVariable(
            $userId,
            $key,
            $value,
            VariableType::CREDENTIAL
        );
    }
}
```

## User Scoping

Variables are scoped per user, providing complete isolation:

```php
// User 1
$variables->setVariable('user-1', 'api_key', 'key-for-user-1', VariableType::CREDENTIAL);

// User 2
$variables->setVariable('user-2', 'api_key', 'key-for-user-2', VariableType::CREDENTIAL);

// Each user gets their own value
$key1 = $variables->getVariable('user-1', 'api_key'); // 'key-for-user-1'
$key2 = $variables->getVariable('user-2', 'api_key'); // 'key-for-user-2'
```

### System-Wide Variables

Use a special "system" user for app-wide variables:

```php
// System-wide API key
$variables->setVariable(
    'system',
    'anthropic_api_key',
    getenv('ANTHROPIC_API_KEY'),
    VariableType::CREDENTIAL
);

// Access from anywhere
function getSystemApiKey(): string {
    $variables = ServiceManager::getInstance()->get(ServiceType::VARIABLE);
    return $variables->getVariable('system', 'anthropic_api_key');
}
```

## Common Patterns

### Pattern 1: API Key Management

```php
class ApiKeyManager
{
    private VariableService $variables;
    
    public function __construct(VariableService $variables)
    {
        $this->variables = $variables;
    }
    
    public function setApiKey(string $userId, string $service, string $key): void
    {
        $this->variables->setVariable(
            $userId,
            "{$service}_api_key",
            $key,
            VariableType::CREDENTIAL
        );
    }
    
    public function getApiKey(string $userId, string $service): ?string
    {
        try {
            return $this->variables->getVariable($userId, "{$service}_api_key");
        } catch (\RuntimeException $e) {
            return null;
        }
    }
    
    public function hasApiKey(string $userId, string $service): bool
    {
        return $this->variables->hasVariable($userId, "{$service}_api_key");
    }
}

// Usage
$apiKeys = new ApiKeyManager($variables);

// Set API keys for different services
$apiKeys->setApiKey('user-123', 'anthropic', 'sk-ant-xxx');
$apiKeys->setApiKey('user-123', 'openai', 'sk-xxx');
$apiKeys->setApiKey('user-123', 'langfuse', 'pk-lf-xxx');

// Retrieve when needed
$anthropicKey = $apiKeys->getApiKey('user-123', 'anthropic');
```

### Pattern 2: User Preferences

```php
class UserPreferences
{
    private VariableService $variables;
    private string $userId;
    
    public function __construct(VariableService $variables, string $userId)
    {
        $this->variables = $variables;
        $this->userId = $userId;
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            return $this->variables->getVariable($this->userId, $key);
        } catch (\RuntimeException $e) {
            return $default;
        }
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->variables->setVariable(
            $this->userId,
            $key,
            $value,
            VariableType::GENERIC
        );
    }
    
    public function all(): array
    {
        return $this->variables->getAllVariables($this->userId);
    }
}

// Usage
$prefs = new UserPreferences($variables, 'user-123');

$prefs->set('theme', 'dark');
$prefs->set('language', 'en');
$prefs->set('notifications', true);

$theme = $prefs->get('theme'); // 'dark'
$all = $prefs->all(); // All preferences
```

### Pattern 3: Environment Variable Migration

```php
class EnvToVariablesMigrator
{
    private VariableService $variables;
    
    public function migrateEnvVariables(string $userId): void
    {
        $envMappings = [
            'ANTHROPIC_API_KEY' => 'anthropic_api_key',
            'OPENAI_API_KEY' => 'openai_api_key',
            'LANGSMITH_API_KEY' => 'langsmith_api_key',
        ];
        
        foreach ($envMappings as $envVar => $varKey) {
            $value = getenv($envVar);
            
            if ($value && !$this->variables->hasVariable($userId, $varKey)) {
                $this->variables->setVariable(
                    $userId,
                    $varKey,
                    $value,
                    VariableType::CREDENTIAL
                );
                
                echo "Migrated {$envVar} to variables\n";
            }
        }
    }
}
```

### Pattern 4: Configuration with Fallback

```php
function getConfig(string $userId, string $key, mixed $default = null): mixed
{
    $variables = ServiceManager::getInstance()->get(ServiceType::VARIABLE);
    
    // Try user-specific first
    try {
        return $variables->getVariable($userId, $key);
    } catch (\RuntimeException $e) {
        // Fall back to system default
        try {
            return $variables->getVariable('system', $key);
        } catch (\RuntimeException $e) {
            return $default;
        }
    }
}

// Usage
$apiKey = getConfig('user-123', 'api_key', 'default-key');
```

## Security Best Practices

### 1. Always Use CREDENTIAL Type for Secrets

```php
// ❌ Bad - API key stored as plain text
$variables->setVariable(
    'user-123',
    'api_key',
    'sk-secret',
    VariableType::GENERIC
);

// ✅ Good - API key encrypted
$variables->setVariable(
    'user-123',
    'api_key',
    'sk-secret',
    VariableType::CREDENTIAL
);
```

### 2. Secure Your Encryption Key

```php
// ❌ Bad - Hardcoded key
$settings->set('variable.encryption_key', 'my-secret-key');

// ✅ Good - From environment
$settings->set('variable.encryption_key', getenv('ENCRYPTION_KEY'));

// ✅ Better - Proper base64 encoding
$key = base64_encode(random_bytes(32));
$settings->set('variable.encryption_key', $key);
```

### 3. Don't Log Credentials

```php
// ❌ Bad
$apiKey = $variables->getVariable('user-123', 'api_key');
error_log("API Key: {$apiKey}"); // Exposed in logs!

// ✅ Good
$apiKey = $variables->getVariable('user-123', 'api_key');
error_log("API Key retrieved successfully");
```

### 4. Validate Before Storing

```php
function setApiKey(string $userId, string $key): void
{
    // Validate format
    if (!preg_match('/^sk-[a-zA-Z0-9]{32,}$/', $key)) {
        throw new \InvalidArgumentException('Invalid API key format');
    }
    
    // Store securely
    $variables->setVariable(
        $userId,
        'api_key',
        $key,
        VariableType::CREDENTIAL
    );
}
```

### 5. Use User Isolation

```php
// Each user has isolated variables
class SecureAgentConfig
{
    private VariableService $variables;
    private string $userId;
    
    public function __construct(string $userId)
    {
        $this->variables = ServiceManager::getInstance()
            ->get(ServiceType::VARIABLE);
        $this->userId = $userId;
    }
    
    public function getApiKey(): string
    {
        // Only access this user's variables
        return $this->variables->getVariable($this->userId, 'api_key');
    }
}
```

### 6. Rotate Keys Regularly

```php
class ApiKeyRotation
{
    public function rotateApiKey(string $userId, string $newKey): void
    {
        $variables = ServiceManager::getInstance()->get(ServiceType::VARIABLE);
        
        // Backup old key
        try {
            $oldKey = $variables->getVariable($userId, 'api_key');
            $variables->setVariable(
                $userId,
                'api_key_backup',
                $oldKey,
                VariableType::CREDENTIAL
            );
        } catch (\RuntimeException $e) {
            // No old key to backup
        }
        
        // Set new key
        $variables->setVariable(
            $userId,
            'api_key',
            $newKey,
            VariableType::CREDENTIAL
        );
    }
}
```

### 7. Handle Errors Gracefully

```php
function getApiKeySecurely(string $userId): ?string
{
    try {
        $variables = ServiceManager::getInstance()->get(ServiceType::VARIABLE);
        return $variables->getVariable($userId, 'api_key');
    } catch (\RuntimeException $e) {
        // Log error without exposing details
        error_log("Failed to retrieve API key for user {$userId}");
        return null;
    }
}
```

## Summary

You've learned:

✅ How to store and retrieve variables  
✅ Difference between Generic and Credential types  
✅ Automatic encryption for credentials  
✅ User scoping for isolation  
✅ Common patterns (API keys, preferences, migrations)  
✅ Security best practices  

Next: Check out [TracingService Tutorial](Services_Tracing.md) for observability!
