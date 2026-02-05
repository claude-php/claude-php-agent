# PHP Design Patterns Quick Reference

## Creational Patterns

### Factory Method
Use when: A class can't anticipate the class of objects it must create.
```php
interface Logger { public function log(string $msg): void; }
class FileLogger implements Logger { /* ... */ }
class ConsoleLogger implements Logger { /* ... */ }

class LoggerFactory {
    public static function create(string $type): Logger {
        return match($type) {
            'file' => new FileLogger(),
            'console' => new ConsoleLogger(),
        };
    }
}
```

### Builder
Use when: The construction process must allow different representations.

### Singleton
Use when: Exactly one instance of a class is needed.
Note: Consider dependency injection instead.

## Structural Patterns

### Adapter
Use when: You need to use an existing class with an incompatible interface.

### Decorator
Use when: You need to add responsibilities to objects dynamically.

### Strategy
Use when: You need to define a family of algorithms and make them interchangeable.

## Behavioral Patterns

### Observer
Use when: A change to one object requires changing others.

### Chain of Responsibility
Use when: More than one object may handle a request.

### Command
Use when: You need to parameterize objects with operations.
