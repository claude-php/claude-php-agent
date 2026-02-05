<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;

/**
 * Composes system prompts with skill instructions.
 *
 * Follows the progressive disclosure pattern: skill summaries are
 * always available, but full instructions are only included for
 * skills that have been matched to the current task.
 */
class SkillPromptComposer
{
    /**
     * Compose a system prompt with skill instructions.
     *
     * @param string $basePrompt The base system prompt
     * @param SkillInterface[] $skills Skills to include
     * @return string Enhanced system prompt
     */
    public function compose(string $basePrompt, array $skills): string
    {
        if (empty($skills)) {
            return $basePrompt;
        }

        $sections = [$basePrompt];

        // Add skill instructions section
        $sections[] = $this->buildSkillsSection($skills);

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Build the skills section of the prompt.
     */
    public function buildSkillsSection(array $skills): string
    {
        $lines = ["## Active Skills\n"];

        foreach ($skills as $skill) {
            $lines[] = "### Skill: {$skill->getName()}";
            $lines[] = "**Description:** {$skill->getDescription()}\n";

            $instructions = $skill->getInstructions();
            if (!empty($instructions)) {
                $lines[] = $instructions;
            }

            // List available resources
            $resources = $this->listResources($skill);
            if (!empty($resources)) {
                $lines[] = "\n**Available Resources:**";
                foreach ($resources as $type => $files) {
                    $lines[] = "- {$type}: " . implode(', ', $files);
                }
            }

            $lines[] = ''; // Blank line between skills
        }

        return implode("\n", $lines);
    }

    /**
     * Build a compact skills index for progressive disclosure.
     *
     * @param array<string, array{name: string, description: string}> $summaries
     */
    public function buildSkillsIndex(array $summaries): string
    {
        if (empty($summaries)) {
            return '';
        }

        $lines = ["## Available Skills\n"];
        $lines[] = "These skills are available. When a request matches a skill's description, the skill will be loaded with full instructions.\n";

        foreach ($summaries as $summary) {
            $lines[] = "- **{$summary['name']}**: {$summary['description']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Compose with both loaded skills and available summaries.
     */
    public function composeWithDiscovery(
        string $basePrompt,
        array $loadedSkills,
        array $availableSummaries
    ): string {
        $sections = [$basePrompt];

        // Add loaded skill instructions
        if (!empty($loadedSkills)) {
            $sections[] = $this->buildSkillsSection($loadedSkills);
        }

        // Add summaries of unloaded skills
        $loadedNames = array_map(
            fn(SkillInterface $s) => $s->getName(),
            $loadedSkills
        );

        $unloadedSummaries = array_filter(
            $availableSummaries,
            fn(array $summary) => !in_array($summary['name'], $loadedNames)
        );

        if (!empty($unloadedSummaries)) {
            $sections[] = $this->buildSkillsIndex($unloadedSummaries);
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * List available resource files for a skill.
     */
    private function listResources(SkillInterface $skill): array
    {
        $resources = [];

        $scripts = $skill->getScripts();
        if (!empty($scripts)) {
            $resources['Scripts'] = $scripts;
        }

        $references = $skill->getReferences();
        if (!empty($references)) {
            $resources['References'] = $references;
        }

        $assets = $skill->getAssets();
        if (!empty($assets)) {
            $resources['Assets'] = $assets;
        }

        return $resources;
    }
}
