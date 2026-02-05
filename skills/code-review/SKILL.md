---
name: code-review
description: "Review PHP code for quality, security, performance, and best practices. Use when asked to review, audit, or analyze code quality."
license: MIT
metadata:
  author: claude-php-agent
  version: "1.0.0"
  tags: [php, code-review, quality, security]
---

# PHP Code Review

## Overview

Perform comprehensive code reviews for PHP projects, checking for quality, security vulnerabilities, performance issues, and adherence to best practices.

## Review Checklist

### 1. Security Review
- Check for SQL injection vulnerabilities (use parameterized queries)
- Check for XSS vulnerabilities (escape output properly)
- Check for CSRF protection
- Validate all user input at system boundaries
- Check for insecure file operations
- Look for hardcoded credentials or secrets
- Verify proper authentication and authorization checks

### 2. Code Quality
- Verify proper type declarations (PHP 8.1+ features)
- Check for proper error handling and exception usage
- Ensure single responsibility principle
- Check for code duplication
- Verify naming conventions (PSR-12 compliance)
- Check cyclomatic complexity
- Look for dead code

### 3. Performance
- Check for N+1 query problems
- Verify proper use of caching
- Check for memory leaks in loops
- Look for unnecessary object instantiation
- Verify efficient string operations
- Check for proper database indexing usage

### 4. Testing
- Verify test coverage for critical paths
- Check for proper mocking and test isolation
- Ensure edge cases are tested
- Verify integration test coverage

## Output Format

Provide findings organized by severity:
1. **Critical** - Security vulnerabilities, data loss risks
2. **Major** - Bugs, significant performance issues
3. **Minor** - Code style, minor improvements
4. **Info** - Suggestions, best practices

For each finding, include:
- File and line reference
- Description of the issue
- Suggested fix with code example
