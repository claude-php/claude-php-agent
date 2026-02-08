<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

/**
 * Per-vendor configuration overrides.
 */
class VendorConfig
{
    public function __construct(
        public readonly string $vendor,
        public readonly ?string $defaultChatModel = null,
        public readonly ?string $defaultImageModel = null,
        public readonly ?string $defaultTTSModel = null,
        public readonly ?string $baseUrl = null,
        public readonly float $timeout = 30.0,
        public readonly int $maxRetries = 2,
    ) {
    }

    /**
     * Create from array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            vendor: $config['vendor'] ?? '',
            defaultChatModel: $config['default_chat_model'] ?? null,
            defaultImageModel: $config['default_image_model'] ?? null,
            defaultTTSModel: $config['default_tts_model'] ?? null,
            baseUrl: $config['base_url'] ?? null,
            timeout: (float) ($config['timeout'] ?? 30.0),
            maxRetries: (int) ($config['max_retries'] ?? 2),
        );
    }
}
