<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Adapters;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\Contracts\VendorAdapterInterface;
use ClaudeAgents\Vendor\VendorConfig;

/**
 * Base class for vendor adapters with shared HTTP and error handling.
 *
 * Uses PHP's built-in cURL for HTTP requests -- no external dependencies.
 */
abstract class AbstractVendorAdapter implements VendorAdapterInterface
{
    protected string $apiKey;

    protected ?VendorConfig $config;

    public function __construct(string $apiKey, ?VendorConfig $config = null)
    {
        $this->apiKey = $apiKey;
        $this->config = $config;
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function supportsCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getSupportedCapabilities(), true);
    }

    /**
     * Get the request timeout in seconds.
     */
    protected function getTimeout(): float
    {
        return $this->config?->timeout ?? 30.0;
    }

    /**
     * Get the max retries.
     */
    protected function getMaxRetries(): int
    {
        return $this->config?->maxRetries ?? 2;
    }

    /**
     * Send an HTTP POST request with JSON body.
     *
     * @param string $url Full URL to POST to
     * @param array<string, mixed> $body Request body (will be JSON-encoded)
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed> Decoded JSON response
     *
     * @throws \RuntimeException On HTTP or decoding errors
     */
    protected function httpPost(string $url, array $body, array $headers = []): array
    {
        return $this->httpPostRaw($url, json_encode($body, JSON_THROW_ON_ERROR), $headers, true);
    }

    /**
     * Send an HTTP POST request and return raw response bytes.
     *
     * Used for binary responses (audio, images).
     *
     * @param string $url Full URL
     * @param array<string, mixed> $body Request body
     * @param array<string, string> $headers Additional headers
     * @return string Raw response body
     *
     * @throws \RuntimeException On HTTP errors
     */
    protected function httpPostBinary(string $url, array $body, array $headers = []): string
    {
        return $this->httpPostRaw($url, json_encode($body, JSON_THROW_ON_ERROR), $headers, false);
    }

    /**
     * Internal HTTP POST with retry logic.
     *
     * @param string $url Full URL
     * @param string $jsonBody JSON-encoded request body
     * @param array<string, string> $headers Additional headers
     * @param bool $decodeJson Whether to JSON-decode the response
     * @return mixed Decoded array or raw string
     *
     * @throws \RuntimeException On failure after retries
     */
    private function httpPostRaw(string $url, string $jsonBody, array $headers, bool $decodeJson): mixed
    {
        $maxRetries = $this->getMaxRetries();
        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                // Exponential backoff: 1s, 2s, 4s...
                usleep((int) (pow(2, $attempt - 1) * 1_000_000));
            }

            try {
                return $this->doPost($url, $jsonBody, $headers, $decodeJson);
            } catch (\RuntimeException $e) {
                $lastError = $e;

                // Don't retry client errors (4xx)
                if (str_contains($e->getMessage(), 'HTTP 4')) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new \RuntimeException("HTTP request to {$url} failed after {$maxRetries} retries");
    }

    /**
     * Execute a single HTTP POST request.
     *
     * @param string $url Full URL
     * @param string $jsonBody JSON-encoded body
     * @param array<string, string> $extraHeaders Additional headers
     * @param bool $decodeJson Whether to decode the response as JSON
     * @return mixed Decoded array or raw string
     *
     * @throws \RuntimeException On cURL or HTTP errors
     */
    private function doPost(string $url, string $jsonBody, array $extraHeaders, bool $decodeJson): mixed
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException("Failed to initialize cURL for {$url}");
        }

        $allHeaders = array_merge(
            ['Content-Type: application/json'],
            $this->getAuthHeaders(),
            $extraHeaders
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => (int) ceil($this->getTimeout()),
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("cURL request to {$url} failed: {$curlError}");
        }

        if ($httpCode >= 400) {
            $errorBody = is_string($response) ? substr($response, 0, 500) : 'unknown';

            throw new \RuntimeException(
                "HTTP {$httpCode} error from {$url}: {$errorBody}"
            );
        }

        if (! $decodeJson) {
            return $response;
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Failed to decode JSON response from {$url}: " . json_last_error_msg()
            );
        }

        return $decoded;
    }

    /**
     * Get the authorization headers for this vendor.
     *
     * @return string[]
     */
    abstract protected function getAuthHeaders(): array;
}
