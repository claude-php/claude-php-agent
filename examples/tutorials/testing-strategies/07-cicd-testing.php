<?php

/**
 * Testing Strategies Tutorial 7: CI/CD Testing
 * 
 * This shows CI/CD testing configuration
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 7: CI/CD Testing ===\n\n";

echo "GitHub Actions Workflow (.github/workflows/tests.yml):\n\n";

$workflow = <<<'YAML'
name: Tests

on: [push, pull_request]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install dependencies
        run: composer install
      - name: Run unit tests
        run: ./vendor/bin/phpunit tests/Unit/
        
  integration-tests:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Run integration tests
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: ./vendor/bin/phpunit tests/Integration/
YAML;

echo $workflow . "\n\n";

echo "✓ See .github/workflows/ for complete CI/CD configuration\n";
