<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation;

use ClaudeAgents\Validation\Exceptions\ClassLoadException;

/**
 * Dynamically loads PHP classes from code strings.
 *
 * Supports two loading strategies:
 * - Temp File: Writes code to a temporary file and includes it (safer, default)
 * - Eval: Uses eval() to execute code directly (requires explicit opt-in)
 */
class ClassLoader
{
    private string $loadStrategy;
    private bool $allowEval;
    private string $tempDir;
    private bool $cleanupTempFiles;

    /**
     * @var array<string> Paths to temporary files created
     */
    private array $tempFiles = [];

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - load_strategy: 'temp_file' or 'eval' (default: 'temp_file')
     *   - allow_eval: Allow eval strategy (default: false)
     *   - temp_dir: Directory for temp files (default: sys_get_temp_dir())
     *   - cleanup_temp_files: Auto-cleanup temp files (default: true)
     */
    public function __construct(array $options = [])
    {
        $this->loadStrategy = $options['load_strategy'] ?? 'temp_file';
        $this->allowEval = $options['allow_eval'] ?? false;
        $this->tempDir = $options['temp_dir'] ?? sys_get_temp_dir();
        $this->cleanupTempFiles = $options['cleanup_temp_files'] ?? true;

        // Validate strategy
        if ($this->loadStrategy === 'eval' && ! $this->allowEval) {
            throw new \InvalidArgumentException(
                'Eval strategy requires explicit opt-in via allow_eval option'
            );
        }

        // Register shutdown function for cleanup
        if ($this->cleanupTempFiles) {
            register_shutdown_function([$this, 'cleanupTempFiles']);
        }
    }

    /**
     * Load a class from code string.
     *
     * @param string $code PHP code containing the class
     * @param string $className Name of the class to load
     * @return string Fully qualified class name
     * @throws ClassLoadException If loading fails
     */
    public function loadClass(string $code, string $className): string
    {
        return match ($this->loadStrategy) {
            'temp_file' => $this->loadFromTempFile($code, $className),
            'eval' => $this->loadFromEval($code, $className),
            default => throw new ClassLoadException(
                "Unknown load strategy: {$this->loadStrategy}",
                $className,
                $this->loadStrategy
            ),
        };
    }

    /**
     * Load class from temporary file.
     *
     * @param string $code PHP code
     * @param string $className Class name
     * @return string Fully qualified class name
     * @throws ClassLoadException
     */
    public function loadFromTempFile(string $code, string $className): string
    {
        // Generate unique namespace to avoid collisions
        $uniqueSuffix = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        $uniqueNamespace = "DynamicValidation\\Temp{$uniqueSuffix}";

        // Extract original namespace if present
        $originalNamespace = $this->extractNamespace($code);

        // Create fully qualified class name
        $fqcn = $uniqueNamespace . '\\' . $className;

        // Modify code to use unique namespace
        $modifiedCode = $this->injectNamespace($code, $uniqueNamespace, $originalNamespace);

        // Create temp file
        $tempFile = tempnam($this->tempDir, 'php_class_');
        if ($tempFile === false) {
            throw new ClassLoadException(
                'Failed to create temporary file',
                $className,
                'temp_file'
            );
        }

        // Change extension to .php
        $phpTempFile = $tempFile . '.php';
        rename($tempFile, $phpTempFile);
        $tempFile = $phpTempFile;

        // Track temp file for cleanup
        $this->tempFiles[] = $tempFile;

        try {
            // Write code to temp file
            $written = file_put_contents($tempFile, $modifiedCode);
            if ($written === false) {
                throw new ClassLoadException(
                    'Failed to write to temporary file',
                    $className,
                    'temp_file',
                    $tempFile
                );
            }

            // Set restrictive permissions
            chmod($tempFile, 0600);

            // Include the file
            require_once $tempFile;

            // Verify class was loaded
            if (! class_exists($fqcn)) {
                throw new ClassLoadException(
                    "Class {$className} was not found after loading temp file",
                    $className,
                    'temp_file',
                    $tempFile
                );
            }

            return $fqcn;
        } catch (ClassLoadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ClassLoadException(
                "Failed to load class from temp file: {$e->getMessage()}",
                $className,
                'temp_file',
                $tempFile,
                0,
                $e
            );
        }
    }

    /**
     * Load class using eval().
     *
     * @param string $code PHP code
     * @param string $className Class name
     * @return string Fully qualified class name
     * @throws ClassLoadException
     */
    public function loadFromEval(string $code, string $className): string
    {
        if (! $this->allowEval) {
            throw new ClassLoadException(
                'Eval strategy is not allowed. Set allow_eval option to true.',
                $className,
                'eval'
            );
        }

        // Generate unique namespace to avoid collisions
        $uniqueSuffix = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        $uniqueNamespace = "DynamicValidation\\Eval{$uniqueSuffix}";

        // Extract original namespace if present
        $originalNamespace = $this->extractNamespace($code);

        // Create fully qualified class name
        $fqcn = $uniqueNamespace . '\\' . $className;

        // Modify code to use unique namespace
        $modifiedCode = $this->injectNamespace($code, $uniqueNamespace, $originalNamespace);

        // Remove PHP opening tag if present
        $evalCode = preg_replace('/^<\?php\s*/i', '', $modifiedCode);

        try {
            // Execute code with eval
            eval($evalCode);

            // Verify class was loaded
            if (! class_exists($fqcn)) {
                throw new ClassLoadException(
                    "Class {$className} was not found after eval",
                    $className,
                    'eval'
                );
            }

            return $fqcn;
        } catch (ClassLoadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ClassLoadException(
                "Failed to load class via eval: {$e->getMessage()}",
                $className,
                'eval',
                null,
                0,
                $e
            );
        }
    }

    /**
     * Extract namespace from code.
     */
    private function extractNamespace(string $code): ?string
    {
        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/i', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Inject unique namespace into code.
     */
    private function injectNamespace(string $code, string $newNamespace, ?string $originalNamespace): string
    {
        // Ensure code starts with <?php
        if (! str_starts_with(trim($code), '<?php')) {
            $code = "<?php\n\n" . $code;
        }

        // If there's already a namespace, replace it
        if ($originalNamespace !== null) {
            $code = preg_replace(
                '/namespace\s+' . preg_quote($originalNamespace, '/') . '\s*;/i',
                "namespace {$newNamespace};",
                $code,
                1
            );
        } else {
            // Insert namespace after <?php and declare statements
            $lines = explode("\n", $code);
            $insertIndex = 0;

            foreach ($lines as $i => $line) {
                $trimmed = trim($line);
                // Skip <?php, empty lines, and declare statements
                if ($trimmed === '<?php' || empty($trimmed) || str_starts_with($trimmed, 'declare(')) {
                    $insertIndex = $i + 1;
                } else {
                    break;
                }
            }

            array_splice($lines, $insertIndex, 0, ["\nnamespace {$newNamespace};\n"]);
            $code = implode("\n", $lines);
        }

        return $code;
    }

    /**
     * Get list of temporary files created.
     *
     * @return array<string>
     */
    public function getTempFiles(): array
    {
        return $this->tempFiles;
    }

    /**
     * Cleanup temporary files.
     */
    public function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    /**
     * Destructor - ensure cleanup.
     */
    public function __destruct()
    {
        if ($this->cleanupTempFiles) {
            $this->cleanupTempFiles();
        }
    }
}
