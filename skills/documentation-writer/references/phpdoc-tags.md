# PHPDoc Tags Reference

## Common Tags

| Tag | Usage |
|-----|-------|
| `@param type $name` | Document a method parameter |
| `@return type` | Document the return type |
| `@throws ExceptionClass` | Document thrown exceptions |
| `@var type` | Document a property type |
| `@deprecated` | Mark as deprecated |
| `@since version` | When it was introduced |
| `@see Reference` | Link to related docs |
| `@example` | Code example |
| `@author Name` | Author attribution |
| `@license` | License information |
| `@link URL` | External link |
| `@internal` | For internal use only |
| `@api` | Part of public API |
| `@inheritdoc` | Inherit parent docs |

## Type Syntax

- Basic: `string`, `int`, `float`, `bool`, `array`, `object`, `null`
- Union: `string|int`
- Intersection: `Countable&Traversable`
- Nullable: `?string`
- Arrays: `string[]`, `array<string, int>`
- Generics: `Collection<User>`
- Callable: `callable(int): string`
