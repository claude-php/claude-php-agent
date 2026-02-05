<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Skills\Exceptions\SkillInstallException;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;

/**
 * Installs and uninstalls Agent Skills.
 *
 * Copies skill directories to/from the configured skills path,
 * validates the skill format, and registers/unregisters from the registry.
 */
class SkillInstaller
{
    public function __construct(
        private string $targetPath,
    ) {
    }

    /**
     * Install a skill from a source directory.
     *
     * @param string $sourcePath Path to the skill directory to install
     * @param SkillRegistry|null $registry Optional registry to register the skill
     * @return SkillInterface The installed skill
     */
    public function install(string $sourcePath, ?SkillRegistry $registry = null): SkillInterface
    {
        // Validate source
        $skillFile = $sourcePath . '/SKILL.md';
        if (!file_exists($skillFile)) {
            throw SkillNotFoundException::noSkillFile($sourcePath);
        }

        $validator = new SkillValidator();
        $content = file_get_contents($skillFile);
        if ($content === false) {
            throw SkillInstallException::installFailed(basename($sourcePath), 'Cannot read SKILL.md');
        }

        $result = $validator->validate($content);
        if (!$result['valid']) {
            throw SkillInstallException::installFailed(
                basename($sourcePath),
                'Validation failed: ' . implode('; ', $result['errors'])
            );
        }

        // Parse to get the skill name
        $parsed = FrontmatterParser::parse($content);
        $name = $parsed['frontmatter']['name'] ?? basename($sourcePath);

        // Check if already installed
        $destPath = $this->targetPath . '/' . $name;
        if (is_dir($destPath)) {
            throw SkillInstallException::alreadyInstalled($name);
        }

        // Create target directory
        if (!is_dir($this->targetPath)) {
            if (!mkdir($this->targetPath, 0755, true)) {
                throw SkillInstallException::installFailed($name, 'Cannot create target directory');
            }
        }

        // Copy skill directory
        $this->copyDirectory($sourcePath, $destPath);

        // Load and register
        $loader = new SkillLoader($this->targetPath);
        $skill = $loader->loadFromPath($destPath);

        if ($registry !== null) {
            $registry->register($skill);
        }

        return $skill;
    }

    /**
     * Uninstall a skill by name.
     */
    public function uninstall(string $name, ?SkillRegistry $registry = null): void
    {
        $skillPath = $this->targetPath . '/' . $name;
        if (!is_dir($skillPath)) {
            throw SkillNotFoundException::withName($name);
        }

        // Remove directory
        if (!$this->removeDirectory($skillPath)) {
            throw SkillInstallException::removeFailed($name, 'Failed to remove skill directory');
        }

        // Unregister
        if ($registry !== null && $registry->has($name)) {
            $registry->unregister($name);
        }
    }

    /**
     * Check if a skill is installed.
     */
    public function isInstalled(string $name): bool
    {
        return is_dir($this->targetPath . '/' . $name)
            && file_exists($this->targetPath . '/' . $name . '/SKILL.md');
    }

    /**
     * List installed skill names.
     *
     * @return string[]
     */
    public function listInstalled(): array
    {
        if (!is_dir($this->targetPath)) {
            return [];
        }

        $installed = [];
        $items = scandir($this->targetPath);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($this->targetPath . '/' . $item)
                && file_exists($this->targetPath . '/' . $item . '/SKILL.md')) {
                $installed[] = $item;
            }
        }

        return $installed;
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $source, string $dest): void
    {
        if (!mkdir($dest, 0755, true)) {
            throw SkillInstallException::installFailed(
                basename($source),
                "Cannot create directory: {$dest}"
            );
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $destPath = $dest . '/' . $item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = scandir($path);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($path);
    }
}
