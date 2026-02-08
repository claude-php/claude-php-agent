<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Vendor\Adapters\GeminiAdapter;
use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;
use ClaudeAgents\Vendor\Contracts\VendorAdapterInterface;
use ClaudeAgents\Vendor\Tools\GeminiCodeExecTool;
use ClaudeAgents\Vendor\Tools\GeminiGroundingTool;
use ClaudeAgents\Vendor\Tools\GeminiImageTool;
use ClaudeAgents\Vendor\Tools\OpenAIImageTool;
use ClaudeAgents\Vendor\Tools\OpenAITTSTool;
use ClaudeAgents\Vendor\Tools\OpenAIWebSearchTool;
use ClaudeAgents\Vendor\Tools\VendorChatTool;

/**
 * Factory that auto-creates vendor tools based on available API keys.
 *
 * Uses the VendorRegistry (keys) and ModelRegistry (capabilities) to
 * determine which tools to create. Only creates tools for vendors
 * that have valid API keys registered.
 */
class CrossVendorToolFactory
{
    private VendorRegistry $vendorRegistry;

    private ModelRegistry $modelRegistry;

    /**
     * @var array<string, VendorAdapterInterface> Cached adapters
     */
    private array $adapters = [];

    public function __construct(
        VendorRegistry $vendorRegistry,
        ?ModelRegistry $modelRegistry = null,
    ) {
        $this->vendorRegistry = $vendorRegistry;
        $this->modelRegistry = $modelRegistry ?? ModelRegistry::default();
    }

    /**
     * Create all available vendor tools based on registered API keys.
     *
     * @return ToolInterface[]
     */
    public function createAllTools(): array
    {
        $tools = [];

        // Generic vendor chat tool (if any external vendor is available)
        $chatAdapters = $this->getChatAdapters();
        if (! empty($chatAdapters)) {
            $tools[] = new VendorChatTool($chatAdapters);
        }

        // OpenAI-specific tools
        if ($this->vendorRegistry->isAvailable('openai')) {
            $tools = array_merge($tools, $this->createOpenAITools());
        }

        // Gemini-specific tools
        if ($this->vendorRegistry->isAvailable('google')) {
            $tools = array_merge($tools, $this->createGeminiTools());
        }

        return $tools;
    }

    /**
     * Create only OpenAI-specific tools.
     *
     * @return ToolInterface[]
     */
    public function createOpenAITools(): array
    {
        $adapter = $this->getOpenAIAdapter();
        if ($adapter === null) {
            return [];
        }

        return [
            new OpenAIWebSearchTool($adapter),
            new OpenAIImageTool($adapter),
            new OpenAITTSTool($adapter),
        ];
    }

    /**
     * Create only Gemini-specific tools.
     *
     * @return ToolInterface[]
     */
    public function createGeminiTools(): array
    {
        $adapter = $this->getGeminiAdapter();
        if ($adapter === null) {
            return [];
        }

        return [
            new GeminiGroundingTool($adapter),
            new GeminiCodeExecTool($adapter),
            new GeminiImageTool($adapter),
        ];
    }

    /**
     * Create tools that provide a specific capability.
     *
     * @return ToolInterface[]
     */
    public function createToolsForCapability(Capability $capability): array
    {
        $tools = [];

        if ($this->vendorRegistry->isAvailable('openai')) {
            $adapter = $this->getOpenAIAdapter();
            if ($adapter !== null && $adapter->supportsCapability($capability)) {
                $tools = array_merge($tools, match ($capability) {
                    Capability::WebSearch => [new OpenAIWebSearchTool($adapter)],
                    Capability::ImageGeneration => [new OpenAIImageTool($adapter)],
                    Capability::TextToSpeech => [new OpenAITTSTool($adapter)],
                    Capability::Chat => [], // Handled by VendorChatTool
                    default => [],
                });
            }
        }

        if ($this->vendorRegistry->isAvailable('google')) {
            $adapter = $this->getGeminiAdapter();
            if ($adapter !== null && $adapter->supportsCapability($capability)) {
                $tools = array_merge($tools, match ($capability) {
                    Capability::Grounding => [new GeminiGroundingTool($adapter)],
                    Capability::CodeExecution => [new GeminiCodeExecTool($adapter)],
                    Capability::ImageGeneration => [new GeminiImageTool($adapter)],
                    Capability::Chat => [], // Handled by VendorChatTool
                    default => [],
                });
            }
        }

        return $tools;
    }

    /**
     * Get the OpenAI adapter (creates and caches it).
     */
    public function getOpenAIAdapter(): ?OpenAIAdapter
    {
        if (! $this->vendorRegistry->isAvailable('openai')) {
            return null;
        }

        if (! isset($this->adapters['openai'])) {
            $this->adapters['openai'] = new OpenAIAdapter(
                $this->vendorRegistry->getKey('openai'),
                $this->vendorRegistry->getConfig('openai'),
            );
        }

        return $this->adapters['openai'];
    }

    /**
     * Get the Gemini adapter (creates and caches it).
     */
    public function getGeminiAdapter(): ?GeminiAdapter
    {
        if (! $this->vendorRegistry->isAvailable('google')) {
            return null;
        }

        if (! isset($this->adapters['google'])) {
            $this->adapters['google'] = new GeminiAdapter(
                $this->vendorRegistry->getKey('google'),
                $this->vendorRegistry->getConfig('google'),
            );
        }

        return $this->adapters['google'];
    }

    /**
     * Get all available chat adapters (for the VendorChatTool).
     *
     * @return array<string, VendorAdapterInterface>
     */
    private function getChatAdapters(): array
    {
        $adapters = [];

        $openai = $this->getOpenAIAdapter();
        if ($openai !== null) {
            $adapters['openai'] = $openai;
        }

        $gemini = $this->getGeminiAdapter();
        if ($gemini !== null) {
            $adapters['google'] = $gemini;
        }

        return $adapters;
    }
}
