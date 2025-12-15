<?php

declare(strict_types=1);

namespace ClaudeAgents\Tools\BuiltIn;

use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;

/**
 * HTTP tool for making HTTP requests to external APIs.
 */
class HTTPTool
{
    /**
     * Create an HTTP request tool.
     *
     * @param array{
     *     timeout?: int,
     *     follow_redirects?: bool,
     *     allowed_domains?: array<string>,
     *     max_response_size?: int,
     *     user_agent?: string
     * } $config Configuration options
     */
    public static function create(array $config = []): Tool
    {
        $timeout = $config['timeout'] ?? 30;
        $followRedirects = $config['follow_redirects'] ?? true;
        $allowedDomains = $config['allowed_domains'] ?? [];
        $maxResponseSize = $config['max_response_size'] ?? 1024 * 1024; // 1MB default
        $userAgent = $config['user_agent'] ?? 'ClaudeAgents-PHP-HTTPTool/1.0';

        return Tool::create('http_request')
            ->description(
                'Make HTTP requests to external APIs or web services. ' .
                'Supports GET, POST, PUT, DELETE methods with custom headers and body.'
            )
            ->stringParam('url', 'URL to request')
            ->stringParam('method', 'HTTP method', true, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])
            ->arrayParam('headers', 'HTTP headers as key-value pairs', false)
            ->stringParam('body', 'Request body (for POST/PUT/PATCH)', false)
            ->numberParam('timeout', 'Request timeout in seconds', false, 1, 300)
            ->handler(function (array $input) use (
                $timeout,
                $followRedirects,
                $allowedDomains,
                $maxResponseSize,
                $userAgent
            ): ToolResult {
                $url = $input['url'];
                $method = strtoupper($input['method']);
                $headers = $input['headers'] ?? [];
                $body = $input['body'] ?? null;
                $requestTimeout = $input['timeout'] ?? $timeout;

                // Validate URL
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    return ToolResult::error('Invalid URL provided');
                }

                // Check allowed domains if configured
                if (! empty($allowedDomains)) {
                    $parsedUrl = parse_url($url);
                    $host = $parsedUrl['host'] ?? '';

                    $allowed = false;
                    foreach ($allowedDomains as $domain) {
                        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                            $allowed = true;

                            break;
                        }
                    }

                    if (! $allowed) {
                        return ToolResult::error("Domain not allowed: {$host}");
                    }
                }

                // Initialize cURL
                $ch = curl_init($url);

                if ($ch === false) {
                    return ToolResult::error('Failed to initialize HTTP request');
                }

                // Set basic options
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

                // Set headers
                $curlHeaders = [];
                foreach ($headers as $key => $value) {
                    $curlHeaders[] = "{$key}: {$value}";
                }
                if (! empty($curlHeaders)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
                }

                // Set body for POST/PUT/PATCH
                if (in_array($method, ['POST', 'PUT', 'PATCH']) && $body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }

                // Execute request
                $response = curl_exec($ch);

                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);

                    return ToolResult::error("HTTP request failed: {$error}");
                }

                // Get response info
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

                curl_close($ch);

                // Split headers and body
                $responseHeaders = substr($response, 0, $headerSize);
                $responseBody = substr($response, $headerSize);

                // Check response size
                if (strlen($responseBody) > $maxResponseSize) {
                    $responseBody = substr($responseBody, 0, $maxResponseSize);
                    $truncated = true;
                } else {
                    $truncated = false;
                }

                // Parse headers
                $parsedHeaders = [];
                $headerLines = explode("\r\n", trim($responseHeaders));
                foreach ($headerLines as $line) {
                    if (strpos($line, ':') !== false) {
                        [$key, $value] = explode(':', $line, 2);
                        $parsedHeaders[trim($key)] = trim($value);
                    }
                }

                return ToolResult::success([
                    'status_code' => $statusCode,
                    'headers' => $parsedHeaders,
                    'body' => $responseBody,
                    'content_type' => $contentType,
                    'time_seconds' => round($totalTime, 3),
                    'truncated' => $truncated,
                ]);
            });
    }
}
