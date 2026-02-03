<?php

/**
 * Services Tutorial 4: Custom Services
 * 
 * Run: php examples/tutorials/services-system/04-custom-service.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceInterface;
use ClaudeAgents\Services\ServiceManager;

echo "=== Services Tutorial 4: Custom Services ===\n\n";

// Custom Email Service
class EmailService implements ServiceInterface
{
    private bool $initialized = false;
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'smtp_host' => 'localhost',
            'from' => 'noreply@example.com',
        ], $config);
    }
    
    public function getName(): string
    {
        return 'email';
    }
    
    public function initialize(): void
    {
        echo "Initializing email service with host: {$this->config['smtp_host']}\n";
        $this->initialized = true;
    }
    
    public function teardown(): void
    {
        echo "Tearing down email service\n";
        $this->initialized = false;
    }
    
    public function isReady(): bool
    {
        return $this->initialized;
    }
    
    public function getSchema(): array
    {
        return [
            'name' => 'email',
            'type' => 'notification',
        ];
    }
    
    public function send(string $to, string $subject, string $body): bool
    {
        echo "Sending email to $to: $subject\n";
        return true;
    }
}

// Use custom service
$manager = ServiceManager::getInstance();

// Register custom service
$emailService = new EmailService([
    'smtp_host' => 'smtp.gmail.com',
    'from' => 'app@example.com',
]);

$manager->register('email', $emailService);
$manager->initialize('email');

// Use the service
$email = $manager->get('email');
$email->send('user@example.com', 'Welcome', 'Thanks for signing up!');

echo "\n✓ Example complete!\n";
