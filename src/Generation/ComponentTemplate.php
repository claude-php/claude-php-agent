<?php

declare(strict_types=1);

namespace ClaudeAgents\Generation;

/**
 * Template system for common PHP component patterns.
 *
 * Provides starter templates for classes, interfaces, traits, and services.
 */
class ComponentTemplate
{
    /**
     * Generate a class template.
     *
     * @param string $name Class name
     * @param string $namespace Namespace
     * @param array<string, mixed> $options Template options:
     *   - extends: Parent class name
     *   - implements: Array of interface names
     *   - properties: Array of property definitions
     *   - methods: Array of method definitions
     *   - use_strict_types: Add strict_types declaration (default: true)
     *   - add_docblock: Add class docblock (default: true)
     */
    public static function classTemplate(string $name, string $namespace, array $options = []): string
    {
        $useStrictTypes = $options['use_strict_types'] ?? true;
        $addDocblock = $options['add_docblock'] ?? true;
        $extends = $options['extends'] ?? null;
        $implements = $options['implements'] ?? [];
        $properties = $options['properties'] ?? [];
        $methods = $options['methods'] ?? [];

        $code = "<?php\n\n";

        if ($useStrictTypes) {
            $code .= "declare(strict_types=1);\n\n";
        }

        $code .= "namespace {$namespace};\n\n";

        if ($addDocblock) {
            $code .= "/**\n";
            $code .= " * {$name}\n";
            $code .= " */\n";
        }

        $code .= "class {$name}";

        if ($extends !== null) {
            $code .= " extends {$extends}";
        }

        if (! empty($implements)) {
            $code .= " implements " . implode(', ', $implements);
        }

        $code .= "\n{\n";

        // Add properties
        if (! empty($properties)) {
            foreach ($properties as $property) {
                $code .= self::generateProperty($property);
            }
            $code .= "\n";
        }

        // Add methods
        if (! empty($methods)) {
            foreach ($methods as $method) {
                $code .= self::generateMethod($method);
            }
        } else {
            // Add empty constructor by default
            $code .= "    public function __construct()\n";
            $code .= "    {\n";
            $code .= "        // Initialize\n";
            $code .= "    }\n";
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Generate an interface template.
     */
    public static function interfaceTemplate(string $name, string $namespace, array $options = []): string
    {
        $useStrictTypes = $options['use_strict_types'] ?? true;
        $addDocblock = $options['add_docblock'] ?? true;
        $extends = $options['extends'] ?? [];
        $methods = $options['methods'] ?? [];

        $code = "<?php\n\n";

        if ($useStrictTypes) {
            $code .= "declare(strict_types=1);\n\n";
        }

        $code .= "namespace {$namespace};\n\n";

        if ($addDocblock) {
            $code .= "/**\n";
            $code .= " * {$name}\n";
            $code .= " */\n";
        }

        $code .= "interface {$name}";

        if (! empty($extends)) {
            $code .= " extends " . implode(', ', $extends);
        }

        $code .= "\n{\n";

        // Add method signatures
        foreach ($methods as $method) {
            $code .= self::generateMethodSignature($method);
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Generate a trait template.
     */
    public static function traitTemplate(string $name, string $namespace, array $options = []): string
    {
        $useStrictTypes = $options['use_strict_types'] ?? true;
        $addDocblock = $options['add_docblock'] ?? true;
        $methods = $options['methods'] ?? [];

        $code = "<?php\n\n";

        if ($useStrictTypes) {
            $code .= "declare(strict_types=1);\n\n";
        }

        $code .= "namespace {$namespace};\n\n";

        if ($addDocblock) {
            $code .= "/**\n";
            $code .= " * {$name}\n";
            $code .= " */\n";
        }

        $code .= "trait {$name}\n{\n";

        // Add methods
        foreach ($methods as $method) {
            $code .= self::generateMethod($method);
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Generate a service class template.
     */
    public static function serviceTemplate(string $name, string $namespace, array $options = []): string
    {
        $dependencies = $options['dependencies'] ?? [];

        $properties = [];
        $constructorParams = [];

        foreach ($dependencies as $dep) {
            $properties[] = [
                'name' => $dep['name'],
                'type' => $dep['type'],
                'visibility' => 'private',
            ];
            $constructorParams[] = "{$dep['type']} \${$dep['name']}";
        }

        $constructor = "    public function __construct(\n";
        $constructor .= "        " . implode(",\n        ", $constructorParams) . "\n";
        $constructor .= "    ) {\n";
        foreach ($dependencies as $dep) {
            $constructor .= "        \$this->{$dep['name']} = \${$dep['name']};\n";
        }
        $constructor .= "    }\n\n";

        return self::classTemplate($name, $namespace, [
            'properties' => $properties,
            'add_docblock' => true,
            'custom_constructor' => $constructor,
        ]);
    }

    /**
     * Generate property code.
     *
     * @param array<string, mixed> $property
     */
    private static function generateProperty(array $property): string
    {
        $visibility = $property['visibility'] ?? 'private';
        $name = $property['name'];
        $type = $property['type'] ?? null;
        $default = $property['default'] ?? null;

        $code = "    {$visibility}";

        if ($type !== null) {
            $code .= " {$type}";
        }

        $code .= " \${$name}";

        if ($default !== null) {
            $defaultValue = is_string($default) ? "'{$default}'" : var_export($default, true);
            $code .= " = {$defaultValue}";
        }

        $code .= ";\n";

        return $code;
    }

    /**
     * Generate method code.
     *
     * @param array<string, mixed> $method
     */
    private static function generateMethod(array $method): string
    {
        $visibility = $method['visibility'] ?? 'public';
        $name = $method['name'];
        $params = $method['params'] ?? [];
        $returnType = $method['return_type'] ?? null;
        $body = $method['body'] ?? "        // TODO: Implement {$name}\n";

        $code = "    {$visibility} function {$name}(";

        $paramStrings = [];
        foreach ($params as $param) {
            $paramStr = '';
            if (isset($param['type'])) {
                $paramStr .= $param['type'] . ' ';
            }
            $paramStr .= '$' . $param['name'];
            if (isset($param['default'])) {
                $defaultValue = is_string($param['default']) ? "'{$param['default']}'" : var_export($param['default'], true);
                $paramStr .= " = {$defaultValue}";
            }
            $paramStrings[] = $paramStr;
        }

        $code .= implode(', ', $paramStrings);
        $code .= ")";

        if ($returnType !== null) {
            $code .= ": {$returnType}";
        }

        $code .= "\n    {\n";
        $code .= $body;
        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate method signature (for interfaces).
     *
     * @param array<string, mixed> $method
     */
    private static function generateMethodSignature(array $method): string
    {
        $name = $method['name'];
        $params = $method['params'] ?? [];
        $returnType = $method['return_type'] ?? null;

        $code = "    public function {$name}(";

        $paramStrings = [];
        foreach ($params as $param) {
            $paramStr = '';
            if (isset($param['type'])) {
                $paramStr .= $param['type'] . ' ';
            }
            $paramStr .= '$' . $param['name'];
            $paramStrings[] = $paramStr;
        }

        $code .= implode(', ', $paramStrings);
        $code .= ")";

        if ($returnType !== null) {
            $code .= ": {$returnType}";
        }

        $code .= ";\n\n";

        return $code;
    }
}
