<?php
/**
 * Simple .env file loader
 * 
 * This is a minimal .env parser for examples.
 * In production, use vlucas/phpdotenv package.
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return false;
    }
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        
        // Parse KEY=value
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set in environment
            if (!empty($name)) {
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
    
    return true;
}

// Auto-load .env from parent directory if it exists
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);

