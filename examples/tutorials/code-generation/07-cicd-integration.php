<?php

/**
 * Code Generation Tutorial 7: CI/CD Integration
 * 
 * This file shows patterns for CI/CD integration
 */

declare(strict_types=1);

echo "=== Code Generation Tutorial 7: CI/CD Integration ===\n\n";

echo "GitHub Actions Workflow (.github/workflows/generate.yml):\n\n";

$workflow = <<<'YAML'
name: Code Generation

on:
  workflow_dispatch:
    inputs:
      description:
        description: 'Component description'
        required: true

jobs:
  generate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install dependencies
        run: composer install
      - name: Generate Component
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
        run: php scripts/generate.php "${{ inputs.description }}"
      - name: Validate
        run: vendor/bin/phpunit tests/Generated/
YAML;

echo $workflow . "\n\n";

echo "✓ See docs/tutorials/CodeGeneration_Tutorial.md for complete setup\n";
