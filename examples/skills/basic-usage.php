<?php declare(strict_types=1);

/**
 * Agent Skills - Basic Usage Example
 *
 * Demonstrates the fundamental workflow of the Agent Skills system:
 * - Discovering skills from a directory
 * - Loading skills into a registry
 * - Accessing skill metadata, instructions, and resources
 * - Searching skills by query
 *
 * No API key or network access required.
 *
 * @see https://agentskills.io/specification
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Skills\SkillLoader;
use ClaudeAgents\Skills\SkillRegistry;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;

$skillsPath = __DIR__ . '/../../skills';

echo "============================================================================\n";
echo "  Agent Skills - Basic Usage Example\n";
echo "============================================================================\n\n";

// ---------------------------------------------------------------------------
// 1. Discover and load skills using SkillLoader
// ---------------------------------------------------------------------------
echo "--- 1. Discover Skills with SkillLoader ---\n\n";

$loader = new SkillLoader($skillsPath);
$skills = $loader->loadAll();

echo "Skills directory: {$skillsPath}\n";
echo "Skills discovered: " . count($skills) . "\n\n";

foreach ($skills as $name => $skill) {
    echo "  [{$name}]\n";
    echo "    Description: {$skill->getDescription()}\n";
    echo "    Path:        {$skill->getPath()}\n\n";
}

// ---------------------------------------------------------------------------
// 2. Register skills in the SkillRegistry
// ---------------------------------------------------------------------------
echo "--- 2. Register Skills in SkillRegistry ---\n\n";

$registry = new SkillRegistry();
$registry->registerMany($skills);

echo "Registered skills: {$registry->count()}\n";
echo "Skill names: " . implode(', ', $registry->names()) . "\n\n";

// Check for a specific skill
$checkName = 'code-review';
if ($registry->has($checkName)) {
    echo "Registry has '{$checkName}': yes\n";
    $codeReview = $registry->get($checkName);
    echo "  Name:        {$codeReview->getName()}\n";
    echo "  Description: {$codeReview->getDescription()}\n";
} else {
    echo "Registry has '{$checkName}': no\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 3. Access skill metadata details
// ---------------------------------------------------------------------------
echo "--- 3. Access Skill Metadata ---\n\n";

foreach ($registry->all() as $skill) {
    $meta = $skill->getMetadata();
    echo "Skill: {$meta->name}\n";
    echo "  Description: {$meta->description}\n";
    echo "  License:     " . ($meta->license ?? 'not specified') . "\n";
    echo "  Version:     " . ($meta->version ?? 'not specified') . "\n";
    echo "  Author:      " . ($meta->getAuthor() ?? 'not specified') . "\n";
    echo "  Tags:        " . (empty($meta->getTags()) ? 'none' : implode(', ', $meta->getTags())) . "\n";

    if (!empty($meta->dependencies)) {
        echo "  Dependencies:\n";
        foreach ($meta->dependencies as $dep) {
            echo "    - {$dep}\n";
        }
    }
    echo "\n";
}

// ---------------------------------------------------------------------------
// 4. Read skill instructions and resources
// ---------------------------------------------------------------------------
echo "--- 4. Read Skill Instructions and Resources ---\n\n";

$firstSkill = reset($skills);
if ($firstSkill !== false) {
    $instructions = $firstSkill->getInstructions();
    $preview = substr($instructions, 0, 200);
    echo "Skill: {$firstSkill->getName()}\n";
    echo "Instructions preview (first 200 chars):\n";
    echo "  " . str_replace("\n", "\n  ", $preview) . "...\n\n";

    echo "Scripts:    " . (empty($firstSkill->getScripts()) ? 'none' : implode(', ', $firstSkill->getScripts())) . "\n";
    echo "References: " . (empty($firstSkill->getReferences()) ? 'none' : implode(', ', $firstSkill->getReferences())) . "\n";
    echo "Assets:     " . (empty($firstSkill->getAssets()) ? 'none' : implode(', ', $firstSkill->getAssets())) . "\n\n";
}

// ---------------------------------------------------------------------------
// 5. Search skills by query
// ---------------------------------------------------------------------------
echo "--- 5. Search Skills ---\n\n";

$queries = ['review', 'data', 'api', 'refactoring', 'nonexistent'];

foreach ($queries as $query) {
    $results = $registry->search($query);
    $names = array_map(fn($s) => $s->getName(), $results);
    echo "Search '{$query}': " . (empty($names) ? 'no matches' : implode(', ', $names)) . "\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 6. Using the SkillManager (unified high-level API)
// ---------------------------------------------------------------------------
echo "--- 6. SkillManager - Unified API ---\n\n";

// Reset singleton to avoid conflicts with previous examples
SkillManager::resetInstance();

$manager = new SkillManager($skillsPath);
$discovered = $manager->discover();

echo "Manager discovered " . count($discovered) . " skills\n";
echo "Manager skill count: {$manager->count()}\n\n";

// Get a specific skill through the manager
try {
    $skill = $manager->get('code-review');
    echo "Got skill via manager: {$skill->getName()}\n";
    echo "  Description: {$skill->getDescription()}\n\n";
} catch (SkillNotFoundException $e) {
    echo "Skill not found: {$e->getMessage()}\n\n";
}

// Search through the manager
$searchResults = $manager->search('documentation');
echo "Manager search 'documentation': " . count($searchResults) . " result(s)\n";
foreach ($searchResults as $result) {
    echo "  - {$result->getName()}: {$result->getDescription()}\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 7. Load a single skill by name
// ---------------------------------------------------------------------------
echo "--- 7. Load a Single Skill by Name ---\n\n";

$loader2 = new SkillLoader($skillsPath);

if ($loader2->exists('api-testing')) {
    $apiTesting = $loader2->load('api-testing');
    echo "Loaded: {$apiTesting->getName()}\n";
    echo "  Auto-invocable: " . ($apiTesting->isAutoInvocable() ? 'yes' : 'no') . "\n";
    echo "  Is mode:        " . ($apiTesting->isMode() ? 'yes' : 'no') . "\n";
    echo "  Is loaded:      " . ($apiTesting->isLoaded() ? 'yes' : 'no') . "\n";

    // Mark as loaded (simulating that instructions have been consumed)
    $apiTesting->markLoaded();
    echo "  Is loaded (after markLoaded): " . ($apiTesting->isLoaded() ? 'yes' : 'no') . "\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 8. Error handling
// ---------------------------------------------------------------------------
echo "--- 8. Error Handling ---\n\n";

try {
    $manager->get('nonexistent-skill');
} catch (SkillNotFoundException $e) {
    echo "Caught SkillNotFoundException: {$e->getMessage()}\n";
}

try {
    $registry->unregister('nonexistent-skill');
} catch (SkillNotFoundException $e) {
    echo "Caught SkillNotFoundException: {$e->getMessage()}\n";
}
echo "\n";

echo "============================================================================\n";
echo "  Basic usage example complete.\n";
echo "============================================================================\n";
