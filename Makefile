.PHONY: help install update test test-unit test-integration test-coverage analyse format format-check check check-full clean

# Default target
help:
	@echo "Claude PHP Agent Framework - Development Commands"
	@echo ""
	@echo "Available targets:"
	@echo "  install         - Install composer dependencies"
	@echo "  update          - Update composer dependencies"
	@echo "  test            - Run all tests"
	@echo "  test-unit       - Run unit tests only"
	@echo "  test-integration - Run integration tests only"
	@echo "  test-coverage   - Run tests with coverage report"
	@echo "  analyse         - Run PHPStan static analysis"
	@echo "  format          - Fix code style issues"
	@echo "  format-check    - Check code style without fixing"
	@echo "  check           - Run all quality checks"
	@echo "  check-full      - Run all quality checks with coverage"
	@echo "  clean           - Clean cache and build artifacts"

# Install dependencies
install:
	composer install

# Update dependencies
update:
	composer update

# Run all tests
test:
	composer test

# Run unit tests
test-unit:
	composer test:unit

# Run integration tests
test-integration:
	composer test:integration

# Run tests with coverage
test-coverage:
	composer test:coverage

# Run static analysis
analyse:
	composer analyse

# Fix code style
format:
	composer format

# Check code style
format-check:
	composer format:check

# Run all checks
check:
	composer check

# Run all checks with coverage
check-full:
	composer check:full

# Clean cache and artifacts
clean:
	rm -rf .phpunit.cache
	rm -rf .php-cs-fixer.cache
	rm -rf build/
	rm -rf coverage/
	rm -f phpstan.neon.local
	@echo "Cleaned cache and build artifacts"

