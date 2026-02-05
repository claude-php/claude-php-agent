---
name: api-testing
description: "Create and execute API test suites for REST APIs. Use when asked to test endpoints, validate API responses, or create API test plans."
license: MIT
metadata:
  author: claude-php-agent
  version: "1.0.0"
  tags: [api, testing, rest, http]
dependencies:
  - phpunit/phpunit
  - guzzlehttp/guzzle
---

# API Testing

## Overview

Create comprehensive API test suites for REST APIs. Covers endpoint testing, response validation, authentication flows, error handling, and performance benchmarks.

## Test Structure

### 1. Setup
```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com',
    'timeout' => 30,
    'headers' => ['Accept' => 'application/json'],
]);
```

### 2. Request Testing
- Test all HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Validate request headers
- Test query parameters
- Test request body formats (JSON, form-data, multipart)

### 3. Response Validation
- Status code verification
- Response body schema validation
- Header validation (Content-Type, Cache-Control, etc.)
- Response time assertions

### 4. Authentication Testing
- Valid credentials
- Invalid credentials
- Expired tokens
- Missing authentication
- Role-based access control

### 5. Error Handling
- 400 Bad Request (invalid input)
- 401 Unauthorized
- 403 Forbidden
- 404 Not Found
- 422 Unprocessable Entity
- 429 Too Many Requests
- 500 Internal Server Error

### 6. Edge Cases
- Empty request bodies
- Extremely large payloads
- Special characters in parameters
- Concurrent requests
- Timeout scenarios

## Output Format

Generate PHPUnit test classes with:
- Descriptive test method names
- Data providers for parameterized tests
- setUp/tearDown for test fixtures
- Proper assertions for each scenario
