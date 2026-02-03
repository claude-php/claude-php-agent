# SessionService Tutorial

Learn how to manage user sessions with automatic expiration and persistence.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Session Lifecycle](#session-lifecycle)
- [Session Management](#session-management)
- [Common Patterns](#common-patterns)
- [Best Practices](#best-practices)

## Overview

The SessionService provides robust session management with automatic expiration, persistence, and multi-user support.

**Features:**
- Session creation with unique IDs
- Automatic expiration handling
- Persistent storage
- User-scoped session lists
- Session extension support
- Cleanup utilities

## Basic Usage

### Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Session\SessionServiceFactory;

$manager = ServiceManager::getInstance();
$manager->registerFactory(new SessionServiceFactory());

$sessions = $manager->get(ServiceType::SESSION);
```

### Create and Use Sessions

```php
// Create a session
$sessionId = $sessions->createSession('user-123', [
    'name' => 'John Doe',
    'logged_in_at' => time(),
    'preferences' => ['theme' => 'dark'],
]);

// Get session data
$data = $sessions->getSession($sessionId);
echo $data['name']; // 'John Doe'

// Update session
$sessions->updateSession($sessionId, [
    'last_activity' => time(),
    'page_views' => 5,
]);

// Destroy session
$sessions->destroySession($sessionId);
```

## Session Lifecycle

### Session Creation

```php
// Create with initial data
$sessionId = $sessions->createSession('user-123', [
    'user_id' => 'user-123',
    'created_at' => time(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);
```

### Session Expiration

Sessions automatically expire based on configured lifetime:

```php
// Configure in settings
// config/services.php
return [
    'session' => [
        'lifetime' => 7200, // 2 hours in seconds
    ],
];

// Check if expired
$data = $sessions->getSession($sessionId);
if ($data === null) {
    echo "Session expired or doesn't exist";
}
```

### Extending Sessions

```php
// Extend session by 1 hour
$sessions->extendSession($sessionId, 3600);

// Keep-alive pattern
function keepSessionAlive(string $sessionId): void
{
    $sessions = ServiceManager::getInstance()->get(ServiceType::SESSION);
    
    if ($sessions->getSession($sessionId) !== null) {
        $sessions->extendSession($sessionId, 1800); // +30 minutes
    }
}
```

### Cleanup Expired Sessions

```php
// Manual cleanup
$cleaned = $sessions->cleanupExpiredSessions();
echo "Cleaned up {$cleaned} expired sessions";

// Scheduled cleanup (run periodically)
function scheduleSessionCleanup(): void
{
    // Run every hour
    while (true) {
        $sessions = ServiceManager::getInstance()->get(ServiceType::SESSION);
        $cleaned = $sessions->cleanupExpiredSessions();
        
        if ($cleaned > 0) {
            echo "Cleaned {$cleaned} sessions\n";
        }
        
        sleep(3600); // 1 hour
    }
}
```

## Session Management

### List User Sessions

```php
// Get all active sessions for a user
$activeSessions = $sessions->listSessions('user-123');

echo "Active sessions: " . count($activeSessions);

// Display session details
foreach ($activeSessions as $sessionId) {
    $data = $sessions->getSession($sessionId);
    echo "Session {$sessionId}: created at {$data['created_at']}\n";
}
```

### Single Sign-On (SSO)

```php
class SSOManager
{
    private SessionService $sessions;
    
    public function login(string $userId, array $userData): string
    {
        // Create new session
        return $this->sessions->createSession($userId, [
            'user_id' => $userId,
            'logged_in_at' => time(),
            'user_data' => $userData,
            'sso' => true,
        ]);
    }
    
    public function logout(string $sessionId): void
    {
        $this->sessions->destroySession($sessionId);
    }
    
    public function logoutAllSessions(string $userId): void
    {
        $allSessions = $this->sessions->listSessions($userId);
        
        foreach ($allSessions as $sessionId) {
            $this->sessions->destroySession($sessionId);
        }
    }
}
```

### Session Middleware

```php
class SessionMiddleware
{
    private SessionService $sessions;
    
    public function handle(string $sessionId, callable $next): mixed
    {
        // Validate session
        $data = $this->sessions->getSession($sessionId);
        
        if ($data === null) {
            throw new \RuntimeException('Invalid or expired session');
        }
        
        // Update last activity
        $this->sessions->updateSession($sessionId, [
            'last_activity' => time(),
        ]);
        
        // Execute handler
        try {
            return $next($data);
        } finally {
            // Optional: extend session on activity
            $this->sessions->extendSession($sessionId, 1800);
        }
    }
}

// Usage
$middleware = new SessionMiddleware($sessions);

$middleware->handle($sessionId, function($sessionData) {
    echo "Hello, {$sessionData['name']}!";
    // Process request...
});
```

## Common Patterns

### Pattern 1: Conversation Sessions

```php
class ConversationSession
{
    private SessionService $sessions;
    private string $sessionId;
    
    public function __construct(SessionService $sessions, string $userId)
    {
        $this->sessions = $sessions;
        $this->sessionId = $sessions->createSession($userId, [
            'messages' => [],
            'created_at' => time(),
        ]);
    }
    
    public function addMessage(string $role, string $content): void
    {
        $data = $this->sessions->getSession($this->sessionId);
        
        $data['messages'][] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        ];
        
        $this->sessions->updateSession($this->sessionId, $data);
    }
    
    public function getHistory(): array
    {
        $data = $this->sessions->getSession($this->sessionId);
        return $data['messages'] ?? [];
    }
    
    public function clear(): void
    {
        $this->sessions->destroySession($this->sessionId);
    }
}

// Usage
$conversation = new ConversationSession($sessions, 'user-123');

$conversation->addMessage('user', 'Hello!');
$conversation->addMessage('assistant', 'Hi! How can I help?');
$conversation->addMessage('user', 'What is the weather?');

$history = $conversation->getHistory();
```

### Pattern 2: Shopping Cart

```php
class ShoppingCart
{
    private SessionService $sessions;
    private string $sessionId;
    
    public function __construct(SessionService $sessions, string $userId)
    {
        $this->sessions = $sessions;
        
        // Try to find existing cart session
        $existing = $this->findCartSession($userId);
        
        if ($existing) {
            $this->sessionId = $existing;
        } else {
            $this->sessionId = $sessions->createSession($userId, [
                'items' => [],
                'total' => 0.0,
            ]);
        }
    }
    
    public function addItem(string $productId, int $quantity, float $price): void
    {
        $data = $this->sessions->getSession($this->sessionId);
        
        $data['items'][] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
        ];
        
        $data['total'] += $quantity * $price;
        
        $this->sessions->updateSession($this->sessionId, $data);
    }
    
    public function getTotal(): float
    {
        $data = $this->sessions->getSession($this->sessionId);
        return $data['total'] ?? 0.0;
    }
    
    public function clear(): void
    {
        $this->sessions->destroySession($this->sessionId);
    }
    
    private function findCartSession(string $userId): ?string
    {
        $userSessions = $this->sessions->listSessions($userId);
        
        foreach ($userSessions as $sessionId) {
            $data = $this->sessions->getSession($sessionId);
            if (isset($data['items'])) {
                return $sessionId;
            }
        }
        
        return null;
    }
}
```

### Pattern 3: Session Analytics

```php
class SessionAnalytics
{
    private SessionService $sessions;
    
    public function getUserActivity(string $userId): array
    {
        $allSessions = $this->sessions->listSessions($userId);
        $totalSessions = count($allSessions);
        $totalPageViews = 0;
        $avgSessionDuration = 0;
        
        foreach ($allSessions as $sessionId) {
            $data = $this->sessions->getSession($sessionId);
            $totalPageViews += $data['page_views'] ?? 0;
            
            $created = $data['created_at'] ?? time();
            $lastActivity = $data['last_activity'] ?? time();
            $avgSessionDuration += ($lastActivity - $created);
        }
        
        return [
            'total_sessions' => $totalSessions,
            'total_page_views' => $totalPageViews,
            'avg_session_duration' => $totalSessions > 0 
                ? $avgSessionDuration / $totalSessions 
                : 0,
        ];
    }
}
```

## Best Practices

### 1. Set Appropriate Lifetimes

```php
// Short sessions for sensitive operations
return [
    'session' => [
        'lifetime' => 900, // 15 minutes for banking
    ],
];

// Long sessions for convenience
return [
    'session' => [
        'lifetime' => 86400, // 24 hours for social media
    ],
];
```

### 2. Store Minimal Data

```php
// ❌ Bad - Storing too much
$sessions->createSession('user-123', [
    'full_user_object' => $user, // Entire user object
    'all_permissions' => $permissions, // All permissions
]);

// ✅ Good - Only essentials
$sessions->createSession('user-123', [
    'user_id' => $user->id,
    'name' => $user->name,
    'role' => $user->role,
]);
```

### 3. Validate Sessions

```php
function validateSession(string $sessionId): bool
{
    $sessions = ServiceManager::getInstance()->get(ServiceType::SESSION);
    
    $data = $sessions->getSession($sessionId);
    
    if ($data === null) {
        return false; // Expired or invalid
    }
    
    // Additional validation
    if (!isset($data['user_id'])) {
        return false;
    }
    
    return true;
}
```

### 4. Update Last Activity

```php
function updateActivity(string $sessionId): void
{
    $sessions = ServiceManager::getInstance()->get(ServiceType::SESSION);
    
    $sessions->updateSession($sessionId, [
        'last_activity' => time(),
    ]);
}
```

### 5. Implement Session Fixation Protection

```php
class SecureSessionManager
{
    private SessionService $sessions;
    
    public function regenerateSession(string $oldSessionId): string
    {
        // Get old session data
        $data = $this->sessions->getSession($oldSessionId);
        
        if ($data === null) {
            throw new \RuntimeException('Session not found');
        }
        
        $userId = $data['user_id'];
        
        // Create new session with same data
        $newSessionId = $this->sessions->createSession($userId, $data);
        
        // Destroy old session
        $this->sessions->destroySession($oldSessionId);
        
        return $newSessionId;
    }
}
```

## Summary

You've learned:

✅ Session creation and management  
✅ Expiration handling  
✅ Session extension  
✅ Multi-user support  
✅ Common patterns (conversations, shopping carts)  
✅ Security best practices  

Next: Check out [Best Practices](Services_BestPractices.md) for production tips!
