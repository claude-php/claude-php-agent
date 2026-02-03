<?php

/**
 * Production Patterns Tutorial 7: Deployment
 * 
 * This file shows deployment configurations
 */

declare(strict_types=1);

echo "=== Production Patterns Tutorial 7: Deployment ===\n\n";

echo "Docker Compose (docker-compose.yml):\n\n";

$dockerCompose = <<<'YAML'
version: '3.8'
services:
  agent-api:
    build: .
    ports:
      - "8080:8080"
    environment:
      - ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
      - REDIS_HOST=redis
    depends_on:
      - redis
    restart: always
    
  redis:
    image: redis:7-alpine
    volumes:
      - redis-data:/data

volumes:
  redis-data:
YAML;

echo $dockerCompose . "\n\n";

echo "Kubernetes Deployment:\n\n";

$k8s = <<<'YAML'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: claude-agent
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: agent
        image: your-registry/claude-agent:latest
        resources:
          requests:
            memory: "512Mi"
            cpu: "250m"
YAML;

echo $k8s . "\n\n";

echo "✓ See docs/tutorials/ProductionPatterns_Tutorial.md for complete deployment guide\n";
