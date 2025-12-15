# Exception System

This directory contains all domain-specific exceptions for the Claude PHP Agent framework.

## Quick Reference

| Exception | Use Case | Key Context |
|-----------|----------|-------------|
| `AgentException` | Base exception (extend this) | `context` array |
| `MaxIterationsException` | Agent reached iteration limit | `max_iterations`, `current_iteration` |
| `ToolExecutionException` | Tool execution failed | `tool`, `reason`, `input` |
| `ConfigurationException` | Invalid configuration | `parameter`, `value` |
| `ValidationException` | Validation failed | `field`, `value`, `violations` |
| `ParseException` | Parsing failed | `input`, `parser_type` |
| `StateException` | State operation failed | `operation`, `state_file` |
| `ContextException` | Context window error | `current_tokens`, `max_tokens`, `operation` |
| `MemoryException` | Memory operation failed | `operation`, `memory_type` |
| `RetryException` | Retry attempts exhausted | `attempts`, `max_attempts`, `last_error` |

## Chain Exceptions

Chain-specific exceptions are in `src/Chains/Exceptions/`:
- `ChainExecutionException` - Chain execution failures
- `ChainValidationException` - Chain validation failures

## Usage

### Throwing Exceptions

```php
use ClaudeAgents\Exceptions\ConfigurationException;

throw new ConfigurationException(
    'Buffer size must be greater than 0',
    'buffer_size',
    $invalidValue
);
```

### Catching Exceptions

```php
use ClaudeAgents\Exceptions\AgentException;
use ClaudeAgents\Exceptions\MaxIterationsException;
use ClaudeAgents\Exceptions\ToolExecutionException;

try {
    $agent->run($task);
} catch (MaxIterationsException $e) {
    // Handle iteration limit
    $context = $e->getContext();
    $maxIters = $context['max_iterations'];
} catch (ToolExecutionException $e) {
    // Handle tool failure
    $context = $e->getContext();
    $toolName = $context['tool'];
} catch (AgentException $e) {
    // Handle any agent error
    $context = $e->getContext();
}
```

### Accessing Context

All exceptions extend `AgentException` and provide a `getContext()` method:

```php
try {
    // some operation
} catch (AgentException $e) {
    echo $e->getMessage();  // Human-readable message
    $context = $e->getContext();  // Structured data
    
    // Log with context
    $logger->error($e->getMessage(), $context);
}
```

## Creating New Exceptions

To add a new exception:

1. Extend `AgentException`
2. Accept relevant parameters in constructor
3. Build context array with meaningful keys
4. Call parent constructor with message, code (0), previous, and context

```php
<?php

declare(strict_types=1);

namespace ClaudeAgents\Exceptions;

class YourNewException extends AgentException
{
    public function __construct(
        string $message,
        string $someParam = '',
        ?\Throwable $previous = null,
    ) {
        $context = [];
        if ($someParam !== '') {
            $context['some_param'] = $someParam;
        }

        parent::__construct(
            $message,
            0,
            $previous,
            $context,
        );
    }
}
```

## See Also

- [EXCEPTION_SYSTEM_COMPLETE.md](../../EXCEPTION_SYSTEM_COMPLETE.md) - Full documentation
- [Chains/Exceptions/](../Chains/Exceptions/) - Chain-specific exceptions

