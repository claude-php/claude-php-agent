<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

/**
 * Central catalog of all known vendor models and their capabilities.
 *
 * Pre-populated with researched model data (Feb 2026). Supports
 * runtime registration of custom or fine-tuned models.
 */
class ModelRegistry
{
    /**
     * @var array<string, ModelInfo> Models indexed by ID
     */
    private array $models = [];

    /**
     * Create a registry pre-populated with all known models.
     */
    public static function default(): self
    {
        $registry = new self();
        $registry->registerDefaults();

        return $registry;
    }

    /**
     * Register a model in the registry.
     */
    public function register(ModelInfo $model): void
    {
        $this->models[$model->id] = $model;
    }

    /**
     * Get a model by its ID.
     */
    public function get(string $id): ?ModelInfo
    {
        return $this->models[$id] ?? null;
    }

    /**
     * Check if a model is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->models[$id]);
    }

    /**
     * Get all models for a specific vendor.
     *
     * @return array<string, ModelInfo>
     */
    public function getModelsForVendor(string $vendor): array
    {
        return array_filter(
            $this->models,
            fn (ModelInfo $m) => $m->vendor === $vendor
        );
    }

    /**
     * Get all models that support a specific capability.
     *
     * @return array<string, ModelInfo>
     */
    public function getModelsWithCapability(Capability $capability): array
    {
        return array_filter(
            $this->models,
            fn (ModelInfo $m) => $m->hasCapability($capability)
        );
    }

    /**
     * Get the default model ID for a vendor and capability.
     */
    public function getDefaultModel(string $vendor, Capability $capability): ?string
    {
        $vendorModels = $this->getModelsForVendor($vendor);

        // First look for an explicitly marked default with the capability
        foreach ($vendorModels as $model) {
            if ($model->isDefault && $model->hasCapability($capability)) {
                return $model->id;
            }
        }

        // Fall back to the first model with the capability
        foreach ($vendorModels as $model) {
            if ($model->hasCapability($capability)) {
                return $model->id;
            }
        }

        return null;
    }

    /**
     * Get all registered models.
     *
     * @return array<string, ModelInfo>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * Get the list of vendors that have at least one model registered.
     *
     * @return string[]
     */
    public function getVendors(): array
    {
        $vendors = [];
        foreach ($this->models as $model) {
            $vendors[$model->vendor] = true;
        }

        return array_keys($vendors);
    }

    /**
     * Register all known default models.
     */
    private function registerDefaults(): void
    {
        $this->registerAnthropicModels();
        $this->registerOpenAIModels();
        $this->registerGeminiModels();
    }

    private function registerAnthropicModels(): void
    {
        $this->register(new ModelInfo(
            id: 'claude-opus-4-6',
            vendor: 'anthropic',
            capabilities: [Capability::Chat],
            description: 'Most intelligent model for agents and coding',
            maxTokens: 128000,
            contextWindow: 200000,
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'claude-sonnet-4-5',
            vendor: 'anthropic',
            capabilities: [Capability::Chat],
            description: 'Best speed + intelligence balance',
            maxTokens: 64000,
            contextWindow: 200000,
        ));

        $this->register(new ModelInfo(
            id: 'claude-haiku-4-5',
            vendor: 'anthropic',
            capabilities: [Capability::Chat],
            description: 'Fastest, near-frontier intelligence',
            maxTokens: 64000,
            contextWindow: 200000,
        ));
    }

    private function registerOpenAIModels(): void
    {
        // Frontier chat models
        $this->register(new ModelInfo(
            id: 'gpt-5.2',
            vendor: 'openai',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Latest, best for coding and agentic tasks',
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'gpt-5.2-pro',
            vendor: 'openai',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Smarter/more precise GPT-5.2',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-5',
            vendor: 'openai',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Previous reasoning model',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-5-mini',
            vendor: 'openai',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Fast, cost-efficient GPT-5',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-5-nano',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Fastest, most cost-efficient GPT-5',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-4.1',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Smartest non-reasoning model',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-4.1-mini',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Smaller, faster GPT-4.1',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-4.1-nano',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Most cost-efficient GPT-4.1',
        ));

        // Reasoning models
        $this->register(new ModelInfo(
            id: 'o3',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Complex reasoning (succeeded by GPT-5)',
        ));

        $this->register(new ModelInfo(
            id: 'o3-pro',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'More compute for better responses',
        ));

        $this->register(new ModelInfo(
            id: 'o4-mini',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Fast, cost-efficient reasoning',
        ));

        // Image generation models
        $this->register(new ModelInfo(
            id: 'gpt-image-1.5',
            vendor: 'openai',
            capabilities: [Capability::ImageGeneration],
            description: 'State-of-the-art image generation',
            endpoint: '/v1/images/generations',
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'gpt-image-1',
            vendor: 'openai',
            capabilities: [Capability::ImageGeneration],
            description: 'Previous image generation model',
            endpoint: '/v1/images/generations',
        ));

        $this->register(new ModelInfo(
            id: 'gpt-image-1-mini',
            vendor: 'openai',
            capabilities: [Capability::ImageGeneration],
            description: 'Cost-efficient image generation',
            endpoint: '/v1/images/generations',
        ));

        // Text-to-speech models
        $this->register(new ModelInfo(
            id: 'gpt-4o-mini-tts',
            vendor: 'openai',
            capabilities: [Capability::TextToSpeech],
            description: 'Text-to-speech with style instructions',
            endpoint: '/v1/audio/speech',
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'tts-1',
            vendor: 'openai',
            capabilities: [Capability::TextToSpeech],
            description: 'TTS optimized for speed',
            endpoint: '/v1/audio/speech',
        ));

        $this->register(new ModelInfo(
            id: 'tts-1-hd',
            vendor: 'openai',
            capabilities: [Capability::TextToSpeech],
            description: 'TTS optimized for quality',
            endpoint: '/v1/audio/speech',
        ));

        // Speech-to-text
        $this->register(new ModelInfo(
            id: 'gpt-4o-transcribe',
            vendor: 'openai',
            capabilities: [Capability::SpeechToText],
            description: 'Speech-to-text powered by GPT-4o',
            endpoint: '/v1/audio/transcriptions',
            isDefault: true,
        ));

        // Deep research
        $this->register(new ModelInfo(
            id: 'o3-deep-research',
            vendor: 'openai',
            capabilities: [Capability::DeepResearch],
            description: 'Most powerful deep research model',
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'o4-mini-deep-research',
            vendor: 'openai',
            capabilities: [Capability::DeepResearch],
            description: 'Faster, more affordable deep research',
        ));
    }

    private function registerGeminiModels(): void
    {
        // Chat / reasoning models
        $this->register(new ModelInfo(
            id: 'gemini-3-pro-preview',
            vendor: 'google',
            capabilities: [Capability::Chat, Capability::Grounding, Capability::CodeExecution],
            description: 'Most intelligent, multimodal',
            maxTokens: 65536,
            contextWindow: 1048576,
        ));

        $this->register(new ModelInfo(
            id: 'gemini-3-flash-preview',
            vendor: 'google',
            capabilities: [Capability::Chat, Capability::Grounding, Capability::CodeExecution],
            description: 'Fast, balanced',
            maxTokens: 65536,
            contextWindow: 1048576,
        ));

        $this->register(new ModelInfo(
            id: 'gemini-2.5-pro',
            vendor: 'google',
            capabilities: [Capability::Chat, Capability::Grounding, Capability::CodeExecution],
            description: 'Advanced reasoning',
            maxTokens: 65536,
            contextWindow: 1048576,
        ));

        $this->register(new ModelInfo(
            id: 'gemini-2.5-flash',
            vendor: 'google',
            capabilities: [Capability::Chat, Capability::Grounding, Capability::CodeExecution],
            description: 'Balanced speed and quality',
            maxTokens: 65536,
            contextWindow: 1048576,
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'gemini-2.5-flash-lite',
            vendor: 'google',
            capabilities: [Capability::Chat],
            description: 'Fastest, most cost-efficient',
            contextWindow: 1048576,
        ));

        // Image generation models (Nano Banana)
        $this->register(new ModelInfo(
            id: 'gemini-2.5-flash-image',
            vendor: 'google',
            capabilities: [Capability::ImageGeneration],
            description: 'Nano Banana - fast image generation and editing',
            isDefault: true,
        ));

        $this->register(new ModelInfo(
            id: 'gemini-3-pro-image-preview',
            vendor: 'google',
            capabilities: [Capability::ImageGeneration, Capability::Grounding],
            description: 'Nano Banana Pro - professional 4K image production with search grounding',
        ));
    }
}
