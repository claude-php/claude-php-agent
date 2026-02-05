<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Skills\Exceptions\SkillValidationException;

/**
 * Validates Agent Skills against the agentskills.io specification.
 *
 * Checks required fields, field constraints, directory structure,
 * and content guidelines.
 */
class SkillValidator
{
    private const MAX_NAME_LENGTH = 64;
    private const MAX_DESCRIPTION_LENGTH = 200;
    private const MAX_INSTRUCTIONS_LINES = 500;
    private const NAME_PATTERN = '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/';

    /**
     * Validate a skill's SKILL.md content.
     *
     * @param string $content Raw SKILL.md content
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validate(string $content): array
    {
        $errors = [];
        $warnings = [];

        // Validate frontmatter exists and is parseable
        try {
            $parsed = FrontmatterParser::parse($content);
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Failed to parse frontmatter: ' . $e->getMessage()],
                'warnings' => [],
            ];
        }

        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        // Required fields
        if (empty($frontmatter['name'])) {
            $errors[] = "Required field 'name' is missing";
        } else {
            $name = $frontmatter['name'];
            if (strlen($name) > self::MAX_NAME_LENGTH) {
                $errors[] = "Name must be at most " . self::MAX_NAME_LENGTH . " characters (got " . strlen($name) . ")";
            }
            if (is_string($name) && !preg_match(self::NAME_PATTERN, $name) && !preg_match('/^[a-z0-9]$/', $name)) {
                $warnings[] = "Name should be kebab-case (lowercase with hyphens): '{$name}'";
            }
        }

        if (empty($frontmatter['description'])) {
            $errors[] = "Required field 'description' is missing";
        } else {
            $description = $frontmatter['description'];
            if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
                $warnings[] = "Description exceeds recommended " . self::MAX_DESCRIPTION_LENGTH . " characters (got " . strlen($description) . ")";
            }
        }

        // Validate body
        if (empty(trim($body))) {
            $warnings[] = "SKILL.md body (instructions) is empty";
        } else {
            $lineCount = count(explode("\n", $body));
            if ($lineCount > self::MAX_INSTRUCTIONS_LINES) {
                $warnings[] = "Instructions exceed recommended " . self::MAX_INSTRUCTIONS_LINES . " lines (got {$lineCount}). Consider using references/ for detailed content.";
            }
        }

        // Validate optional fields
        if (isset($frontmatter['version']) && !is_string($frontmatter['version'])) {
            $warnings[] = "Version should be a string (e.g., '1.0.0')";
        }

        if (isset($frontmatter['metadata']) && !is_array($frontmatter['metadata'])) {
            $errors[] = "Metadata must be a mapping (key-value pairs)";
        }

        if (isset($frontmatter['dependencies']) && !is_array($frontmatter['dependencies'])) {
            $errors[] = "Dependencies must be an array";
        }

        if (isset($frontmatter['compatibility']) && !is_array($frontmatter['compatibility'])) {
            $errors[] = "Compatibility must be a mapping";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate a skill directory structure.
     *
     * @param string $path Path to skill directory
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validateDirectory(string $path): array
    {
        $errors = [];
        $warnings = [];

        if (!is_dir($path)) {
            return [
                'valid' => false,
                'errors' => ["Path is not a directory: '{$path}'"],
                'warnings' => [],
            ];
        }

        $skillFile = $path . '/SKILL.md';
        if (!file_exists($skillFile)) {
            return [
                'valid' => false,
                'errors' => ["SKILL.md not found in: '{$path}'"],
                'warnings' => [],
            ];
        }

        // Validate SKILL.md content
        $content = file_get_contents($skillFile);
        if ($content === false) {
            return [
                'valid' => false,
                'errors' => ["Cannot read SKILL.md in: '{$path}'"],
                'warnings' => [],
            ];
        }

        $contentResult = $this->validate($content);
        $errors = array_merge($errors, $contentResult['errors']);
        $warnings = array_merge($warnings, $contentResult['warnings']);

        // Check for known subdirectories
        $knownDirs = ['scripts', 'references', 'assets'];
        $items = scandir($path);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === 'SKILL.md') {
                    continue;
                }
                if (is_dir($path . '/' . $item) && !in_array($item, $knownDirs)) {
                    $warnings[] = "Unknown directory '{$item}' found. Standard directories are: scripts/, references/, assets/";
                }
            }
        }

        // Check for deeply nested references
        $refsDir = $path . '/references';
        if (is_dir($refsDir)) {
            $this->checkNestingDepth($refsDir, 1, $warnings);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate a Skill object.
     */
    public function validateSkill(Skill $skill): array
    {
        $errors = [];
        $warnings = [];

        if (empty($skill->getName())) {
            $errors[] = "Skill name is empty";
        }

        if (empty($skill->getDescription())) {
            $errors[] = "Skill description is empty";
        }

        if (empty($skill->getInstructions())) {
            $warnings[] = "Skill instructions are empty";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check nesting depth of reference files.
     */
    private function checkNestingDepth(string $dir, int $currentDepth, array &$warnings, int $maxDepth = 2): void
    {
        if ($currentDepth > $maxDepth) {
            $warnings[] = "References are nested deeper than {$maxDepth} levels. Keep file references one level deep from SKILL.md.";
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($dir . '/' . $item)) {
                $this->checkNestingDepth($dir . '/' . $item, $currentDepth + 1, $warnings, $maxDepth);
            }
        }
    }
}
