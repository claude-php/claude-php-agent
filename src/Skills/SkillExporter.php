<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Skills\Exceptions\SkillException;

/**
 * Exports Agent Skills to the standard format.
 *
 * Creates SKILL.md files with proper YAML frontmatter and directory
 * structure following the agentskills.io specification.
 */
class SkillExporter
{
    /**
     * Export a skill to a target directory.
     *
     * @param SkillInterface $skill The skill to export
     * @param string $targetPath Directory where skill will be created
     * @return string Path to the exported skill directory
     */
    public function export(SkillInterface $skill, string $targetPath): string
    {
        $skillDir = $targetPath . '/' . $skill->getName();

        if (!is_dir($skillDir)) {
            if (!mkdir($skillDir, 0755, true)) {
                throw new SkillException("Cannot create directory: {$skillDir}");
            }
        }

        // Generate SKILL.md
        $content = $this->generateSkillMd($skill);
        file_put_contents($skillDir . '/SKILL.md', $content);

        // Copy resources if available from source
        if ($skill instanceof Skill && !empty($skill->getPath())) {
            $this->copyResources($skill, $skillDir);
        }

        // Create standard subdirectories
        foreach (['scripts', 'references', 'assets'] as $subDir) {
            $subPath = $skillDir . '/' . $subDir;
            if (!is_dir($subPath)) {
                mkdir($subPath, 0755, true);
            }
        }

        return $skillDir;
    }

    /**
     * Create a new Skill object from data (without saving to filesystem).
     */
    public function createSkill(array $data): Skill
    {
        $metadata = SkillMetadata::fromArray([
            'name' => $data['name'] ?? 'unnamed-skill',
            'description' => $data['description'] ?? '',
            'license' => $data['license'] ?? null,
            'version' => $data['version'] ?? '1.0.0',
            'metadata' => $data['metadata'] ?? [],
            'dependencies' => $data['dependencies'] ?? [],
            'compatibility' => $data['compatibility'] ?? [],
        ]);

        return new Skill(
            metadata: $metadata,
            instructions: $data['instructions'] ?? '',
            path: $data['path'] ?? '',
        );
    }

    /**
     * Generate SKILL.md content from a skill.
     */
    public function generateSkillMd(SkillInterface $skill): string
    {
        $frontmatter = FrontmatterParser::generate($skill->getMetadata()->toArray());

        return $frontmatter . "\n" . $skill->getInstructions() . "\n";
    }

    /**
     * Generate a template SKILL.md for a new skill.
     */
    public function generateTemplate(string $name, string $description): string
    {
        $data = [
            'name' => $name,
            'description' => $description,
        ];

        $frontmatter = FrontmatterParser::generate($data);

        return $frontmatter . "\n# {$name}\n\nInsert your skill instructions here.\n";
    }

    /**
     * Export multiple skills to a target directory.
     *
     * @param SkillInterface[] $skills
     * @return string[] Paths to exported skill directories
     */
    public function exportMany(array $skills, string $targetPath): array
    {
        $paths = [];
        foreach ($skills as $skill) {
            $paths[] = $this->export($skill, $targetPath);
        }

        return $paths;
    }

    /**
     * Copy resource files from source skill to exported directory.
     */
    private function copyResources(Skill $skill, string $targetDir): void
    {
        $sourcePath = $skill->getPath();

        foreach (['scripts', 'references', 'assets'] as $subDir) {
            $sourceDir = $sourcePath . '/' . $subDir;
            $destDir = $targetDir . '/' . $subDir;

            if (!is_dir($sourceDir)) {
                continue;
            }

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $items = scandir($sourceDir);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                copy($sourceDir . '/' . $item, $destDir . '/' . $item);
            }
        }
    }
}
