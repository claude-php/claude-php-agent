<?php

/**
 * Testing Strategies Tutorial 6: Test Coverage
 * 
 * This shows coverage commands and configuration
 */

declare(strict_types=1);

echo "=== Testing Strategies Tutorial 6: Test Coverage ===\n\n";

echo "Generate coverage report:\n\n";

echo "With Xdebug:\n";
echo "  XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/\n\n";

echo "With PCOV:\n";
echo "  php -d pcov.enabled=1 ./vendor/bin/phpunit --coverage-html coverage/\n\n";

echo "View coverage:\n";
echo "  open coverage/index.html\n\n";

echo "PHPUnit Configuration (phpunit.xml):\n\n";

$phpunitXml = <<<'XML'
<coverage>
    <include>
        <directory suffix=".php">src</directory>
    </include>
    <report>
        <html outputDirectory="coverage/html"/>
        <text outputFile="coverage/coverage.txt"/>
    </report>
</coverage>
XML;

echo $phpunitXml . "\n\n";

echo "✓ See docs/tutorials/TestingStrategies_Tutorial.md for details\n";
