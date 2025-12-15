# Contributing to Claude PHP Agent Framework

Thank you for considering contributing to the Claude PHP Agent Framework! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Enhancements](#suggesting-enhancements)

## Code of Conduct

This project adheres to a Code of Conduct that all contributors are expected to follow. Please read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) before contributing.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Create a new branch for your contribution
4. Make your changes
5. Push to your fork
6. Submit a pull request

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/claude-php-agent.git
cd claude-php-agent

# Install dependencies
composer install

# Copy environment file (if needed)
cp .env.example .env
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
composer test -- --testsuite=Unit
composer test -- --testsuite=Integration

# Run tests with coverage
composer test -- --coverage-html coverage
```

### Code Quality Tools

```bash
# Run static analysis
composer analyse

# Fix code style issues
composer format

# Run all checks (recommended before committing)
composer check
```

## How to Contribute

### Types of Contributions

We welcome various types of contributions:

- **Bug fixes** - Fix issues in existing code
- **New features** - Add new agent patterns, tools, or capabilities
- **Documentation** - Improve docs, add examples, write tutorials
- **Tests** - Add or improve test coverage
- **Performance** - Optimize existing functionality
- **Refactoring** - Improve code quality without changing behavior

### Branch Naming Convention

- `feature/your-feature-name` - For new features
- `bugfix/issue-description` - For bug fixes
- `docs/what-you-are-documenting` - For documentation
- `refactor/what-you-are-refactoring` - For refactoring

## Coding Standards

### PHP Standards

This project follows:
- **PSR-12** coding style standard
- **PSR-4** autoloading standard
- **PHPStan Level 6** static analysis

### Code Style

Run PHP-CS-Fixer before committing:

```bash
composer format
```

### Naming Conventions

- **Classes**: PascalCase (e.g., `ReactAgent`, `ToolRegistry`)
- **Methods**: camelCase (e.g., `executeAction`, `registerTool`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_ITERATIONS`)
- **Properties**: camelCase (e.g., `$maxRetries`, `$systemPrompt`)

### Documentation

- All public classes, methods, and properties must have PHPDoc comments
- Include `@param`, `@return`, and `@throws` tags where applicable
- Provide meaningful descriptions and examples

Example:

```php
/**
 * Execute the agent with the given input.
 *
 * @param string $input The user input or task description
 * @param array<string, mixed> $options Additional execution options
 * @return AgentResult The execution result containing the answer and metadata
 * @throws AgentException If execution fails after all retries
 */
public function run(string $input, array $options = []): AgentResult
{
    // Implementation
}
```

### Type Hints

- Use strict typing: `declare(strict_types=1);` in all files
- Type hint all parameters and return values
- Use union types and nullable types appropriately (PHP 8.1+)

## Testing Guidelines

### Writing Tests

- All new features must include tests
- Aim for high test coverage (>80%)
- Write both unit and integration tests where appropriate
- Use descriptive test method names

### Test Structure

```php
namespace ClaudeAgents\Tests\Unit;

use PHPUnit\Framework\TestCase;

class YourClassTest extends TestCase
{
    public function test_it_does_something_specific(): void
    {
        // Arrange
        $input = 'test';
        
        // Act
        $result = $this->subject->doSomething($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Test Organization

- Unit tests in `tests/Unit/` - Test individual classes in isolation
- Integration tests in `tests/Integration/` - Test component interactions
- Use mocks for external dependencies (API calls, etc.)

## Pull Request Process

### Before Submitting

1. **Update your branch** with the latest from `main`
2. **Run all quality checks**: `composer check`
3. **Write/update tests** for your changes
4. **Update documentation** if needed
5. **Add/update examples** if adding new features
6. **Update CHANGELOG.md** with your changes

### PR Description Template

```markdown
## Description
Brief description of what this PR does

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Refactoring
- [ ] Performance improvement

## Changes Made
- List specific changes
- Be clear and concise

## Testing
- How have you tested this?
- What test cases did you add?

## Breaking Changes
- List any breaking changes
- Provide migration guide if needed

## Related Issues
Closes #123
```

### Review Process

- At least one maintainer must approve the PR
- All CI checks must pass
- No merge conflicts with main branch
- Code coverage should not decrease

### After Approval

- Maintainers will merge your PR
- Your contribution will be included in the next release

## Reporting Bugs

### Before Reporting

1. Check existing issues to avoid duplicates
2. Try to reproduce with the latest version
3. Gather relevant information

### Bug Report Template

```markdown
**Description**
Clear description of the bug

**To Reproduce**
Steps to reproduce:
1. Create agent with...
2. Call method...
3. Error occurs...

**Expected Behavior**
What you expected to happen

**Actual Behavior**
What actually happened

**Environment**
- PHP version:
- Package version:
- OS:

**Code Sample**
```php
// Minimal code to reproduce
```

**Error Messages**
```
Paste any error messages or stack traces
```

**Additional Context**
Any other relevant information
```

## Suggesting Enhancements

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
Clear description of the problem

**Proposed Solution**
How you envision the feature working

**Alternatives Considered**
Other solutions you've considered

**Use Cases**
How would this be used? Include examples.

**Implementation Ideas**
Technical approach if you have thoughts
```

## Development Workflow

### Typical Workflow

```bash
# 1. Update your main branch
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feature/my-new-feature

# 3. Make changes and commit
git add .
git commit -m "Add: description of changes"

# 4. Run quality checks
composer check

# 5. Push to your fork
git push origin feature/my-new-feature

# 6. Create pull request on GitHub
```

### Commit Message Guidelines

Follow conventional commits format:

- `Add: new feature or capability`
- `Fix: bug fix`
- `Update: changes to existing functionality`
- `Remove: removal of feature or code`
- `Docs: documentation changes`
- `Test: test additions or changes`
- `Refactor: code refactoring`
- `Perf: performance improvements`
- `Style: formatting, missing semicolons, etc.`

Example:
```
Add: ReflectionLoop for self-improving agents

- Implement generate-reflect-refine cycle
- Add quality threshold configuration
- Include comprehensive tests
- Update documentation with examples
```

## Questions?

- **GitHub Issues**: For bugs and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: For security concerns (see [SECURITY.md](SECURITY.md))

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Recognition

Contributors will be recognized in:
- The CHANGELOG.md for their specific contributions
- The project's GitHub contributors page
- Release notes when applicable

Thank you for contributing to Claude PHP Agent Framework! 🚀

