<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;

/**
 * FileSystem tool for safe file operations.
 */
class FileSystemTool
{
    /**
     * Create a filesystem tool.
     *
     * @param array{
     *     allowed_paths?: array<string>,
     *     read_only?: bool,
     *     max_file_size?: int,
     *     allowed_extensions?: array<string>
     * } $config Configuration options
     */
    public static function create(array $config = []): Tool
    {
        $allowedPaths = $config['allowed_paths'] ?? [];
        $readOnly = $config['read_only'] ?? false;
        $maxFileSize = $config['max_file_size'] ?? 1024 * 1024 * 10; // 10MB default
        $allowedExtensions = $config['allowed_extensions'] ?? [];

        // Normalize allowed paths to absolute paths
        $normalizedPaths = array_map(function ($path) {
            $realPath = realpath($path);
            if ($realPath === false) {
                // If path doesn't exist yet, get absolute path
                if ($path[0] !== '/') {
                    $path = getcwd() . '/' . $path;
                }

                return rtrim($path, '/');
            }

            return rtrim($realPath, '/');
        }, $allowedPaths);

        return Tool::create('filesystem')
            ->description(
                'Perform file operations (read, write, list, delete) within allowed directories. ' .
                ($readOnly ? 'READ-ONLY MODE - write operations disabled. ' : '') .
                'All paths must be within allowed directories.'
            )
            ->stringParam(
                'operation',
                'Operation to perform',
                true,
                ['read', 'write', 'list', 'exists', 'delete', 'info', 'mkdir']
            )
            ->stringParam('path', 'File or directory path (relative or absolute)')
            ->stringParam('content', 'Content to write (for write operation)', false)
            ->booleanParam('recursive', 'Recursive operation (for list/mkdir/delete)', false)
            ->handler(function (array $input) use (
                $normalizedPaths,
                $readOnly,
                $maxFileSize,
                $allowedExtensions
            ): ToolResult {
                $operation = $input['operation'];
                $path = $input['path'] ?? '';
                $content = $input['content'] ?? '';
                $recursive = $input['recursive'] ?? false;

                if (empty($path)) {
                    return ToolResult::error('Path parameter is required');
                }

                // Security: Check if path is within allowed directories
                if (! empty($normalizedPaths)) {
                    // Get absolute path
                    $absPath = $path[0] === '/' ? $path : getcwd() . '/' . $path;
                    $realPath = realpath($path) ?: $absPath;

                    $allowed = false;

                    foreach ($normalizedPaths as $allowedPath) {
                        if (str_starts_with($realPath, $allowedPath)) {
                            $allowed = true;

                            break;
                        }
                    }

                    if (! $allowed) {
                        return ToolResult::error(
                            "Access denied: path '{$path}' is outside allowed directories"
                        );
                    }
                }

                // Security: Check file extension if restricted
                if (! empty($allowedExtensions) && ! is_dir($path)) {
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (! in_array($extension, $allowedExtensions)) {
                        return ToolResult::error(
                            "File extension '.{$extension}' is not allowed"
                        );
                    }
                }

                try {
                    switch ($operation) {
                        case 'read':
                            if (! file_exists($path)) {
                                return ToolResult::error("File not found: {$path}");
                            }
                            if (! is_file($path)) {
                                return ToolResult::error("Path is not a file: {$path}");
                            }
                            if (filesize($path) > $maxFileSize) {
                                return ToolResult::error(
                                    'File too large: ' . filesize($path) . " bytes (max: {$maxFileSize})"
                                );
                            }
                            $fileContent = file_get_contents($path);

                            return ToolResult::success([
                                'content' => $fileContent,
                                'size' => strlen($fileContent),
                                'path' => $path,
                            ]);

                        case 'write':
                            if ($readOnly) {
                                return ToolResult::error('Write operations disabled (read-only mode)');
                            }
                            if (empty($content)) {
                                return ToolResult::error('Content parameter is required for write operation');
                            }
                            if (strlen($content) > $maxFileSize) {
                                return ToolResult::error(
                                    'Content too large: ' . strlen($content) . " bytes (max: {$maxFileSize})"
                                );
                            }

                            // Create directory if it doesn't exist
                            $dir = dirname($path);
                            if (! is_dir($dir)) {
                                mkdir($dir, 0o755, true);
                            }

                            $bytesWritten = file_put_contents($path, $content);

                            return ToolResult::success([
                                'bytes_written' => $bytesWritten,
                                'path' => $path,
                            ]);

                        case 'list':
                            if (! file_exists($path)) {
                                return ToolResult::error("Path not found: {$path}");
                            }
                            if (! is_dir($path)) {
                                return ToolResult::error("Path is not a directory: {$path}");
                            }

                            if ($recursive) {
                                $iterator = new \RecursiveIteratorIterator(
                                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                                    \RecursiveIteratorIterator::SELF_FIRST
                                );
                                $files = [];
                                foreach ($iterator as $file) {
                                    $files[] = [
                                        'path' => $file->getPathname(),
                                        'name' => $file->getFilename(),
                                        'type' => $file->isDir() ? 'directory' : 'file',
                                        'size' => $file->isFile() ? $file->getSize() : null,
                                    ];
                                }
                            } else {
                                $items = array_diff(scandir($path), ['.', '..']);
                                $files = [];
                                foreach ($items as $item) {
                                    $fullPath = $path . DIRECTORY_SEPARATOR . $item;
                                    $files[] = [
                                        'path' => $fullPath,
                                        'name' => $item,
                                        'type' => is_dir($fullPath) ? 'directory' : 'file',
                                        'size' => is_file($fullPath) ? filesize($fullPath) : null,
                                    ];
                                }
                            }

                            return ToolResult::success([
                                'path' => $path,
                                'count' => count($files),
                                'items' => $files,
                            ]);

                        case 'exists':
                            return ToolResult::success([
                                'exists' => file_exists($path),
                                'path' => $path,
                                'type' => file_exists($path) ? (is_dir($path) ? 'directory' : 'file') : null,
                            ]);

                        case 'delete':
                            if ($readOnly) {
                                return ToolResult::error('Delete operations disabled (read-only mode)');
                            }
                            if (! file_exists($path)) {
                                return ToolResult::error("Path not found: {$path}");
                            }

                            if (is_dir($path)) {
                                if ($recursive) {
                                    self::deleteDirectory($path);

                                    return ToolResult::success([
                                        'deleted' => true,
                                        'path' => $path,
                                        'type' => 'directory',
                                    ]);
                                }
                                if (rmdir($path)) {
                                    return ToolResult::success([
                                        'deleted' => true,
                                        'path' => $path,
                                        'type' => 'directory',
                                    ]);
                                }

                                return ToolResult::error(
                                    'Cannot delete directory (not empty). Use recursive=true to delete non-empty directories.'
                                );

                            }
                            unlink($path);

                            return ToolResult::success([
                                'deleted' => true,
                                'path' => $path,
                                'type' => 'file',
                            ]);

                        case 'info':
                            if (! file_exists($path)) {
                                return ToolResult::error("Path not found: {$path}");
                            }

                            $info = [
                                'path' => $path,
                                'type' => is_dir($path) ? 'directory' : 'file',
                                'size' => is_file($path) ? filesize($path) : null,
                                'permissions' => substr(sprintf('%o', fileperms($path)), -4),
                                'modified' => date('Y-m-d H:i:s', filemtime($path)),
                                'created' => date('Y-m-d H:i:s', filectime($path)),
                            ];

                            if (is_file($path)) {
                                $info['extension'] = pathinfo($path, PATHINFO_EXTENSION);
                                $info['mime_type'] = mime_content_type($path);
                            }

                            return ToolResult::success($info);

                        case 'mkdir':
                            if ($readOnly) {
                                return ToolResult::error('Mkdir operations disabled (read-only mode)');
                            }
                            if (file_exists($path)) {
                                return ToolResult::error("Path already exists: {$path}");
                            }

                            mkdir($path, 0o755, $recursive);

                            return ToolResult::success([
                                'created' => true,
                                'path' => $path,
                            ]);

                        default:
                            return ToolResult::error("Unknown operation: {$operation}");
                    }
                } catch (\Throwable $e) {
                    return ToolResult::error("Filesystem error: {$e->getMessage()}");
                }
            });
    }

    /**
     * Recursively delete a directory.
     */
    private static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
