# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

We take the security of Claude PHP Agent Framework seriously. If you discover a security vulnerability, please follow these steps:

### Do NOT:

- Open a public GitHub issue
- Disclose the vulnerability publicly before it has been addressed

### Do:

1. **Email** security concerns to: dale@example.com
2. **Include** the following information:
   - Type of vulnerability
   - Full paths of source file(s) related to the vulnerability
   - Location of the affected source code (tag/branch/commit or direct URL)
   - Step-by-step instructions to reproduce the issue
   - Proof-of-concept or exploit code (if possible)
   - Impact of the issue, including how an attacker might exploit it

### What to Expect:

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours
- **Communication**: We will send you regular updates about our progress
- **Timeline**: We aim to address critical vulnerabilities within 7 days
- **Credit**: If you wish, we will publicly acknowledge your responsible disclosure

## Security Best Practices

When using Claude PHP Agent Framework:

### 1. API Key Management

```php
// ❌ DON'T: Hard-code API keys
$client = new ClaudePhp(['api_key' => 'sk-ant-1234...']);

// ✅ DO: Use environment variables
$client = new ClaudePhp(['api_key' => getenv('ANTHROPIC_API_KEY')]);
```

### 2. Tool Input Validation

```php
// ✅ Always validate and sanitize tool inputs
$tool = Tool::create('execute_command')
    ->handler(function (array $input): string {
        // Validate input
        if (!preg_match('/^[a-zA-Z0-9\s\-_]+$/', $input['command'])) {
            throw new InvalidArgumentException('Invalid command format');
        }

        // Escape for safe execution
        $command = escapeshellarg($input['command']);
        return shell_exec($command);
    });
```

### 3. Rate Limiting

```php
// Implement rate limiting for production use
$agent = Agent::create()
    ->maxIterations(10)  // Prevent infinite loops
    ->timeout(30.0)       // Set reasonable timeouts
    ->onError(function (Throwable $e, int $attempt) {
        // Log and handle errors appropriately
        error_log("Agent error: {$e->getMessage()}");
    });
```

### 4. Memory and State Management

```php
// ❌ DON'T: Store sensitive data in memory without encryption
$memory->set('password', 'secret123');

// ✅ DO: Avoid storing sensitive data, or encrypt it
$memory->set('user_id', hash('sha256', $userId));
```

### 5. Tool Permissions

- Only register tools that are absolutely necessary
- Implement proper access controls for tools that modify data
- Validate all user inputs before passing to tools
- Use least privilege principle for file system and network access

### 6. Logging and Monitoring

```php
// ✅ Use PSR-3 logger but be careful about logging sensitive data
$agent = Agent::create()
    ->withLogger($logger)
    ->onToolExecution(function (string $tool, array $input, string $result) {
        // Don't log sensitive information
        $sanitizedInput = array_diff_key($input, ['password' => null, 'api_key' => null]);
        error_log("Tool executed: $tool");
    });
```

### 7. Dependency Management

- Regularly update dependencies to get security patches
- Use `composer audit` to check for known vulnerabilities
- Pin dependency versions in production

```bash
# Check for vulnerabilities
composer audit

# Update dependencies
composer update
```

## Known Security Considerations

### 1. Tool Execution

Tools can execute arbitrary code. Always:

- Validate and sanitize inputs
- Use allowlists, not denylists
- Implement timeouts and resource limits

### 2. Prompt Injection

Be aware of prompt injection attacks:

- Validate user inputs before adding to prompts
- Use system prompts to set boundaries
- Implement output validation

### 3. Data Exposure

- Claude API interactions may be logged by Anthropic (see their privacy policy)
- Don't send sensitive data (passwords, tokens, PII) to the API unless necessary
- Implement data anonymization where possible

### 4. File System Access

If using file-based tools or memory:

- Restrict access to specific directories
- Validate all file paths
- Use absolute paths when possible
- Never allow user-controlled file paths without validation

## Vulnerability Disclosure Policy

We follow a coordinated disclosure process:

1. **Report received**: Acknowledged within 48 hours
2. **Validation**: We validate and reproduce the issue
3. **Fix development**: We develop and test a fix
4. **Security advisory**: We prepare a security advisory
5. **Release**: We release a patched version
6. **Disclosure**: We publicly disclose the vulnerability after the patch is released (typically 7-14 days)

## Security Updates

Subscribe to security updates:

- Watch this repository on GitHub
- Enable notifications for security advisories
- Follow release notes in CHANGELOG.md

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [Anthropic API Security](https://docs.anthropic.com/claude/reference/security)

## Hall of Fame

We recognize security researchers who responsibly disclose vulnerabilities:

<!-- Security researchers will be listed here -->

Thank you for helping keep Claude PHP Agent Framework and our users safe!
