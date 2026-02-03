<?php

/**
 * Production Patterns Tutorial 4: Security
 * 
 * Run: php examples/tutorials/production-patterns/04-security.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "=== Production Patterns Tutorial 4: Security ===\n\n";

// Example 1: Secure API Key Management
echo "Example 1: API Key Management\n";
echo str_repeat('-', 60) . "\n";

// ❌ BAD - Never hardcode
// $apiKey = 'sk-ant-api03-...';

// ✓ GOOD - Environment variable
$apiKey = getenv('ANTHROPIC_API_KEY');

if ($apiKey) {
    echo "✓ API key loaded from environment\n";
    echo "  Length: " . strlen($apiKey) . " chars\n";
    echo "  Prefix: " . substr($apiKey, 0, 10) . "...\n";
} else {
    echo "⚠ API key not found (expected for demo)\n";
}

echo "\n";

// Example 2: Input Validation
echo "Example 2: Input Validation\n";
echo str_repeat('-', 60) . "\n";

function validateInput(string $query): bool
{
    // Length check
    if (strlen($query) > 10000) {
        echo "✗ Query too long\n";
        return false;
    }
    
    // Content validation
    $sanitized = strip_tags($query);
    if ($sanitized !== $query) {
        echo "✗ Query contains HTML tags\n";
        return false;
    }
    
    echo "✓ Query validated\n";
    return true;
}

$valid = validateInput("What is 2+2?");
$invalid = validateInput("<script>alert('xss')</script>");

echo "\n";

// Example 3: Rate Limiting
echo "Example 3: Rate Limiting\n";
echo str_repeat('-', 60) . "\n";

class RateLimiter
{
    private array $requests = [];
    private int $limit = 10;
    private int $window = 60; // seconds
    
    public function check(string $identifier): bool
    {
        $now = time();
        $key = $identifier;
        
        // Clean old requests
        $this->requests[$key] = array_filter(
            $this->requests[$key] ?? [],
            fn($time) => $time > $now - $this->window
        );
        
        // Check limit
        if (count($this->requests[$key] ?? []) >= $this->limit) {
            echo "✗ Rate limit exceeded for $identifier\n";
            return false;
        }
        
        // Record request
        $this->requests[$key][] = $now;
        echo "✓ Request allowed (" . count($this->requests[$key]) . "/{$this->limit})\n";
        return true;
    }
}

$limiter = new RateLimiter();
$limiter->check('user:123');
$limiter->check('user:123');

echo "\n✓ Example complete!\n";
