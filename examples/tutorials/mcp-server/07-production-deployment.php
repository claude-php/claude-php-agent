<?php

/**
 * MCP Tutorial 7: Production Deployment
 * 
 * This file shows production deployment configurations
 */

declare(strict_types=1);

echo "=== MCP Tutorial 7: Production Deployment ===\n\n";

echo "Supervisor Configuration (supervisor.conf):\n\n";

$supervisorConf = <<<'INI'
[program:mcp-server]
command=php /var/www/agent/bin/mcp-server
directory=/var/www/agent
autostart=true
autorestart=true
user=www-data
environment=ANTHROPIC_API_KEY="your-key"
stdout_logfile=/var/log/mcp-server.log
stderr_logfile=/var/log/mcp-server-error.log
INI;

echo $supervisorConf . "\n\n";

echo "Docker Compose (docker-compose.yml):\n\n";

$dockerCompose = <<<'YAML'
version: '3.8'
services:
  mcp-server:
    build: .
    environment:
      - ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
    restart: always
    volumes:
      - ./logs:/app/logs
YAML;

echo $dockerCompose . "\n\n";

echo "✓ See docs/tutorials/MCPServer_Tutorial.md for deployment guide\n";
