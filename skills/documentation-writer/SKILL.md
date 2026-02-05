---
name: documentation-writer
description: "Generate comprehensive documentation for PHP projects including API docs, README files, and inline documentation. Use when asked to document code or create project documentation."
license: MIT
metadata:
  author: claude-php-agent
  version: "1.0.0"
  tags: [documentation, php, readme, api-docs]
---

# Documentation Writer

## Overview

Generate clear, comprehensive documentation for PHP projects. Supports README generation, API documentation, inline PHPDoc comments, and tutorial creation.

## Documentation Types

### 1. README.md
- Project description and purpose
- Installation instructions
- Quick start / getting started
- Configuration options
- Usage examples
- API reference (brief)
- Contributing guidelines
- License information

### 2. API Documentation
- Class and method descriptions
- Parameter documentation with types
- Return type documentation
- Exception documentation
- Code examples for each public method
- Inheritance and interface documentation

### 3. PHPDoc Comments
```php
/**
 * Brief one-line description.
 *
 * Longer description if needed. Can include multiple paragraphs
 * and additional context about the method's behavior.
 *
 * @param string $name The user's display name
 * @param int $age The user's age (must be positive)
 * @return User The created user instance
 * @throws InvalidArgumentException If age is negative
 * @throws DuplicateUserException If name already exists
 *
 * @example
 * ```php
 * $user = $service->createUser('Alice', 30);
 * echo $user->getName(); // "Alice"
 * ```
 */
```

### 4. Tutorial / Guide
- Step-by-step instructions
- Code examples at each step
- Expected output
- Common pitfalls and troubleshooting
- Links to related documentation

## Style Guidelines

1. **Be concise** - Say what needs to be said, nothing more
2. **Use examples** - Show, don't just tell
3. **Be accurate** - Documentation must match actual behavior
4. **Keep current** - Update docs when code changes
5. **Use consistent formatting** - Follow established conventions
