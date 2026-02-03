# StorageService Tutorial

Learn how to use the StorageService for persistent file storage in your applications.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [File Operations](#file-operations)
- [User Scoping](#user-scoping)
- [Common Patterns](#common-patterns)
- [Best Practices](#best-practices)

## Overview

The StorageService provides a unified interface for file storage with automatic directory management and user scoping.

**Features:**
- Flow/user-scoped file organization
- Atomic writes for data safety
- Path sanitization (prevents directory traversal)
- Automatic directory cleanup
- File listing and management

## Basic Usage

### Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Storage\StorageServiceFactory;

$manager = ServiceManager::getInstance();
$manager->registerFactory(new StorageServiceFactory());

$storage = $manager->get(ServiceType::STORAGE);
```

### Save and Get Files

```php
// Save a file
$storage->saveFile(
    'user-123',           // Flow/user ID
    'profile.json',       // File name
    json_encode([         // Data
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ])
);

// Get a file
$data = $storage->getFile('user-123', 'profile.json');
$profile = json_decode($data, true);

echo $profile['name']; // 'John Doe'
```

### List and Delete Files

```php
// List all files for a user
$files = $storage->listFiles('user-123');
// ['profile.json', 'settings.json', 'data/export.csv']

// Check if file exists
if ($storage->fileExists('user-123', 'profile.json')) {
    echo "Profile exists";
}

// Delete a file
$storage->deleteFile('user-123', 'profile.json');
```

## File Operations

### Working with Subdirectories

```php
// Save in subdirectory
$storage->saveFile('user-123', 'exports/report.csv', $csvData);
$storage->saveFile('user-123', 'uploads/avatar.png', $imageData);

// List shows directory structure
$files = $storage->listFiles('user-123');
// ['exports/report.csv', 'uploads/avatar.png']

// Get from subdirectory
$csv = $storage->getFile('user-123', 'exports/report.csv');
```

### File Metadata

```php
// Get file size
$size = $storage->getFileSize('user-123', 'profile.json');
echo "File size: {$size} bytes";

// Build full path (for debugging)
$path = $storage->buildPath('user-123', 'profile.json');
echo "Stored at: {$path}";

// Parse path
$parsed = $storage->parsePath($path);
// ['flowId' => 'user-123', 'fileName' => 'profile.json']
```

### Atomic Writes

Files are written atomically to prevent corruption:

```php
// This is safe even if process crashes mid-write
$storage->saveFile('user-123', 'important.json', $criticalData);

// Implementation:
// 1. Write to temp file: important.json.tmp.xxx
// 2. Atomic rename: important.json.tmp.xxx -> important.json
```

## User Scoping

Files are automatically scoped by user/flow ID:

```php
// User 1's files
$storage->saveFile('user-1', 'data.json', '{"value": 1}');
$storage->saveFile('user-1', 'config.json', '{"theme": "dark"}');

// User 2's files (completely isolated)
$storage->saveFile('user-2', 'data.json', '{"value": 2}');
$storage->saveFile('user-2', 'config.json', '{"theme": "light"}');

// Each user has their own data.json
$user1Data = $storage->getFile('user-1', 'data.json'); // {"value": 1}
$user2Data = $storage->getFile('user-2', 'data.json'); // {"value": 2}
```

### Directory Structure

```
storage/
├── user-1/
│   ├── data.json
│   └── config.json
├── user-2/
│   ├── data.json
│   └── config.json
└── shared/
    └── app-config.json
```

## Common Patterns

### Pattern 1: User Profiles

```php
class UserProfileStorage
{
    private StorageService $storage;
    
    public function saveProfile(string $userId, array $profile): void
    {
        $data = json_encode($profile, JSON_PRETTY_PRINT);
        $this->storage->saveFile($userId, 'profile.json', $data);
    }
    
    public function loadProfile(string $userId): ?array
    {
        try {
            $data = $this->storage->getFile($userId, 'profile.json');
            return json_decode($data, true);
        } catch (\RuntimeException $e) {
            return null; // Profile doesn't exist
        }
    }
    
    public function deleteProfile(string $userId): void
    {
        $this->storage->deleteFile($userId, 'profile.json');
    }
}

// Usage
$profiles = new UserProfileStorage($storage);

$profiles->saveProfile('user-123', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$profile = $profiles->loadProfile('user-123');
```

### Pattern 2: Agent State Persistence

```php
class AgentStateStorage
{
    private StorageService $storage;
    
    public function saveState(string $agentId, array $state): void
    {
        $data = json_encode([
            'state' => $state,
            'timestamp' => time(),
            'version' => '1.0',
        ], JSON_PRETTY_PRINT);
        
        $this->storage->saveFile("agents/{$agentId}", 'state.json', $data);
    }
    
    public function loadState(string $agentId): ?array
    {
        try {
            $data = $this->storage->getFile("agents/{$agentId}", 'state.json');
            $decoded = json_decode($data, true);
            return $decoded['state'] ?? null;
        } catch (\RuntimeException $e) {
            return null;
        }
    }
    
    public function listAgents(): array
    {
        $agents = [];
        $files = $this->storage->listFiles('agents');
        
        foreach ($files as $file) {
            if (basename($file) === 'state.json') {
                $agentId = dirname($file);
                $agents[] = $agentId;
            }
        }
        
        return $agents;
    }
}
```

### Pattern 3: Export/Import

```php
class DataExporter
{
    private StorageService $storage;
    
    public function exportUserData(string $userId): string
    {
        $exportData = [
            'user_id' => $userId,
            'exported_at' => date('Y-m-d H:i:s'),
            'files' => [],
        ];
        
        // List all user files
        $files = $this->storage->listFiles($userId);
        
        foreach ($files as $file) {
            $content = $this->storage->getFile($userId, $file);
            $exportData['files'][$file] = base64_encode($content);
        }
        
        // Save export
        $exportJson = json_encode($exportData, JSON_PRETTY_PRINT);
        $exportFile = "export-{$userId}-" . time() . '.json';
        $this->storage->saveFile('exports', $exportFile, $exportJson);
        
        return $exportFile;
    }
    
    public function importUserData(string $exportFile): void
    {
        $exportData = json_decode(
            $this->storage->getFile('exports', $exportFile),
            true
        );
        
        $userId = $exportData['user_id'];
        
        foreach ($exportData['files'] as $file => $content) {
            $decoded = base64_decode($content);
            $this->storage->saveFile($userId, $file, $decoded);
        }
    }
}
```

### Pattern 4: Versioned Storage

```php
class VersionedStorage
{
    private StorageService $storage;
    
    public function saveWithVersion(
        string $userId,
        string $fileName,
        string $data
    ): void {
        // Save current version
        $this->storage->saveFile($userId, $fileName, $data);
        
        // Create version backup
        $timestamp = date('Y-m-d_His');
        $versionFile = "versions/{$fileName}.{$timestamp}";
        $this->storage->saveFile($userId, $versionFile, $data);
    }
    
    public function listVersions(string $userId, string $fileName): array
    {
        $versions = [];
        $files = $this->storage->listFiles($userId);
        
        foreach ($files as $file) {
            if (str_starts_with($file, "versions/{$fileName}.")) {
                $versions[] = $file;
            }
        }
        
        return $versions;
    }
    
    public function restoreVersion(
        string $userId,
        string $fileName,
        string $versionFile
    ): void {
        $data = $this->storage->getFile($userId, $versionFile);
        $this->storage->saveFile($userId, $fileName, $data);
    }
}
```

### Pattern 5: Temporary Files

```php
class TempFileManager
{
    private StorageService $storage;
    
    public function createTempFile(string $userId, string $data): string
    {
        $tempFile = 'temp/' . uniqid('tmp_', true) . '.tmp';
        $this->storage->saveFile($userId, $tempFile, $data);
        return $tempFile;
    }
    
    public function cleanupTempFiles(string $userId): int
    {
        $files = $this->storage->listFiles($userId);
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (str_starts_with($file, 'temp/') && str_ends_with($file, '.tmp')) {
                $this->storage->deleteFile($userId, $file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}
```

## Best Practices

### 1. Use Descriptive Flow IDs

```php
// ❌ Bad - Generic IDs
$storage->saveFile('data', 'file.json', $data);

// ✅ Good - Descriptive IDs
$storage->saveFile('user-123', 'profile.json', $data);
$storage->saveFile('agent-react-456', 'state.json', $data);
$storage->saveFile('session-789', 'conversation.json', $data);
```

### 2. Organize with Subdirectories

```php
// ✅ Good - Organized structure
$storage->saveFile('user-123', 'profiles/personal.json', $data);
$storage->saveFile('user-123', 'profiles/business.json', $data);
$storage->saveFile('user-123', 'exports/2024-01-15.csv', $data);
$storage->saveFile('user-123', 'uploads/avatar.png', $data);
```

### 3. Handle Errors Gracefully

```php
try {
    $data = $storage->getFile('user-123', 'profile.json');
} catch (\RuntimeException $e) {
    // File doesn't exist - use defaults
    $data = json_encode(['name' => 'Guest']);
}
```

### 4. Clean Up After Yourself

```php
// Save temp file
$tempFile = 'temp/processing.tmp';
$storage->saveFile('user-123', $tempFile, $data);

try {
    // Process...
} finally {
    // Always clean up
    $storage->deleteFile('user-123', $tempFile);
}
```

### 5. Use JSON for Structured Data

```php
// ✅ Good - Structured data
$storage->saveFile('user-123', 'config.json', json_encode([
    'theme' => 'dark',
    'language' => 'en',
    'notifications' => true,
], JSON_PRETTY_PRINT));

// Easy to read and modify
$config = json_decode($storage->getFile('user-123', 'config.json'), true);
```

### 6. Validate Before Saving

```php
function saveUserProfile(string $userId, array $profile): void
{
    // Validate required fields
    if (!isset($profile['name']) || !isset($profile['email'])) {
        throw new \InvalidArgumentException('Missing required fields');
    }
    
    // Validate email format
    if (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Invalid email');
    }
    
    // Save validated data
    $storage->saveFile($userId, 'profile.json', json_encode($profile));
}
```

### 7. Check File Sizes

```php
function saveWithSizeLimit(
    string $userId,
    string $fileName,
    string $data,
    int $maxSize = 1048576 // 1MB
): void {
    if (strlen($data) > $maxSize) {
        throw new \RuntimeException("File too large: " . strlen($data) . " bytes");
    }
    
    $storage->saveFile($userId, $fileName, $data);
}
```

## Summary

You've learned:

✅ Basic file operations (save, get, delete)  
✅ File listing and metadata  
✅ User scoping for isolation  
✅ Common patterns (profiles, versioning, exports)  
✅ Best practices for production use  

Next: Check out [SessionService Tutorial](Services_Sessions.md) for user sessions!
