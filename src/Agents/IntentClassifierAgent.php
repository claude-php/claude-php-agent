<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Intent Classifier Agent - Classifies user intents and extracts entities (Rasa-style).
 *
 * This agent analyzes user input to determine their intent and extract relevant entities,
 * similar to NLU systems like Rasa. Perfect for chatbots, voice assistants, and
 * conversational AI applications.
 *
 * Features:
 * - Intent classification with confidence scores
 * - Entity extraction (names, dates, locations, etc.)
 * - Multiple intent support
 * - Custom entity types
 * - Training examples per intent
 * - Fallback handling for unknown intents
 *
 * @package ClaudeAgents\Agents
 */
class IntentClassifierAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $intents = [];
    private array $entityTypes = [];
    private float $confidenceThreshold;
    private string $fallbackIntent;
    private LoggerInterface $logger;

    /**
     * Create a new Intent Classifier Agent.
     *
     * @param ClaudePhp $client Claude PHP client instance
     * @param array $options Configuration options:
     *   - name: Agent name (default: 'intent_classifier')
     *   - intents: Initial intents map [intent => [examples]]
     *   - entity_types: Custom entity types to extract
     *   - confidence_threshold: Minimum confidence for classification (default: 0.5)
     *   - fallback_intent: Intent to use when confidence is low (default: 'unknown')
     *   - logger: PSR-3 logger instance
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'intent_classifier';
        $this->intents = $options['intents'] ?? [];
        $this->entityTypes = $options['entity_types'] ?? [];
        $this->confidenceThreshold = $options['confidence_threshold'] ?? 0.5;
        $this->fallbackIntent = $options['fallback_intent'] ?? 'unknown';
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Run intent classification on the given text.
     *
     * @param string $task User input text to classify
     * @return AgentResult Classification result with intent, confidence, and entities
     */
    public function run(string $task): AgentResult
    {
        $this->logger->info("Classifying intent: {$task}");

        try {
            $classification = $this->classifyIntent($task);

            // Apply confidence threshold
            if ($classification['confidence'] < $this->confidenceThreshold) {
                $this->logger->warning(
                    "Low confidence ({$classification['confidence']}) for intent '{$classification['intent']}'"
                );
                $classification['intent'] = $this->fallbackIntent;
                $classification['original_intent'] = $classification['intent'];
            }

            return AgentResult::success(
                answer: json_encode($classification, JSON_PRETTY_PRINT),
                messages: [],
                iterations: 1,
                metadata: $classification,
            );
        } catch (\Throwable $e) {
            $this->logger->error("Intent classification failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Add a new intent with optional training examples.
     *
     * @param string $intent Intent name
     * @param array $examples Training examples for this intent
     * @param string|null $description Optional description of the intent
     */
    public function addIntent(string $intent, array $examples = [], ?string $description = null): void
    {
        $this->intents[$intent] = [
            'examples' => $examples,
            'description' => $description,
        ];
        $this->logger->debug("Added intent: {$intent} with " . count($examples) . ' examples');
    }

    /**
     * Add a custom entity type to extract.
     *
     * @param string $entityType Entity type name (e.g., 'product_name', 'order_id')
     * @param string|null $description Optional description to help with extraction
     */
    public function addEntityType(string $entityType, ?string $description = null): void
    {
        $this->entityTypes[$entityType] = $description;
        $this->logger->debug("Added entity type: {$entityType}");
    }

    /**
     * Remove an intent from the classifier.
     *
     * @param string $intent Intent name to remove
     */
    public function removeIntent(string $intent): void
    {
        unset($this->intents[$intent]);
        $this->logger->debug("Removed intent: {$intent}");
    }

    /**
     * Get all configured intents.
     *
     * @return array Map of intent names to their configurations
     */
    public function getIntents(): array
    {
        return $this->intents;
    }

    /**
     * Get all configured entity types.
     *
     * @return array Map of entity type names to their descriptions
     */
    public function getEntityTypes(): array
    {
        return $this->entityTypes;
    }

    /**
     * Classify intent from text using Claude.
     *
     * @param string $text Input text to classify
     * @return array Classification result with intent, confidence, and entities
     */
    private function classifyIntent(string $text): array
    {
        $intentsStr = $this->buildIntentsDescription();
        $entitiesStr = $this->buildEntityTypesDescription();

        $prompt = <<<PROMPT
            User input: "{$text}"

            {$intentsStr}

            {$entitiesStr}

            Classify the user's intent and extract any relevant entities.
            Respond ONLY with valid JSON in this exact format:
            {
              "intent": "intent_name",
              "confidence": 0.95,
              "entities": [
                {"type": "entity_type", "value": "entity_value"}
              ]
            }

            Rules:
            - Choose the most appropriate intent from the available intents
            - Confidence should be between 0.0 (no confidence) and 1.0 (certain)
            - Extract all relevant entities found in the text
            - If no entities are found, use an empty array
            - Return ONLY the JSON, no explanation or additional text
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 512,
            'system' => 'You are an intent classification system. Respond with JSON only. Do not include markdown code blocks or explanations.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $json = $this->extractTextContent($response->content ?? []);

        // Clean up potential markdown code blocks
        $json = preg_replace('/```(?:json)?\s*/', '', $json);
        $json = trim($json);

        $result = json_decode($json, true);

        if (! is_array($result) || ! isset($result['intent'])) {
            $this->logger->warning('Failed to parse classification result, using fallback');

            return [
                'intent' => $this->fallbackIntent,
                'confidence' => 0.0,
                'entities' => [],
                'raw_response' => $json,
            ];
        }

        return [
            'intent' => $result['intent'] ?? $this->fallbackIntent,
            'confidence' => (float) ($result['confidence'] ?? 0.0),
            'entities' => $result['entities'] ?? [],
        ];
    }

    /**
     * Build a description of available intents for the prompt.
     *
     * @return string Formatted intent descriptions
     */
    private function buildIntentsDescription(): string
    {
        if (empty($this->intents)) {
            return 'Available intents: [No intents configured - classify as "unknown"]';
        }

        $lines = ['Available intents:'];
        foreach ($this->intents as $intent => $config) {
            $lines[] = "- {$intent}";
            if (! empty($config['description'])) {
                $lines[] = "  Description: {$config['description']}";
            }
            if (! empty($config['examples'])) {
                $examples = array_slice($config['examples'], 0, 3);
                $lines[] = '  Examples: ' . implode(', ', array_map(fn ($ex) => "\"{$ex}\"", $examples));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build a description of entity types to extract.
     *
     * @return string Formatted entity type descriptions
     */
    private function buildEntityTypesDescription(): string
    {
        if (empty($this->entityTypes)) {
            return 'Extract standard entities: person names, dates, times, locations, numbers, email addresses, phone numbers.';
        }

        $lines = ['Entity types to extract:'];
        foreach ($this->entityTypes as $type => $description) {
            $lines[] = $description
                ? "- {$type}: {$description}"
                : "- {$type}";
        }

        return implode("\n", $lines);
    }

    /**
     * Extract text content from Claude response.
     *
     * @param array $content Response content blocks
     * @return string Extracted text
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get the agent name.
     *
     * @return string Agent name
     */
    public function getName(): string
    {
        return $this->name;
    }
}
