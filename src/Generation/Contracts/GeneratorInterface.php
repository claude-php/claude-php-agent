<?php

declare(strict_types=1);

namespace ClaudeAgents\Generation\Contracts;

use ClaudeAgents\Generation\ComponentResult;

/**
 * Interface for code generators.
 */
interface GeneratorInterface
{
    /**
     * Generate code from a description.
     *
     * @param string $description Natural language description
     * @param array<string, mixed> $context Additional context
     * @return ComponentResult Generated code with validation
     */
    public function generate(string $description, array $context = []): ComponentResult;

    /**
     * Get the generator name.
     */
    public function getName(): string;
}
