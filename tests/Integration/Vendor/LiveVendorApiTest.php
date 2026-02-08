<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Vendor;

use ClaudeAgents\Vendor\Adapters\GeminiAdapter;
use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;
use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\CrossVendorToolFactory;
use ClaudeAgents\Vendor\ModelRegistry;
use ClaudeAgents\Vendor\VendorRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Live integration tests that make real API calls to vendor endpoints.
 *
 * These tests require valid API keys in the .env file.
 * They are designed to be minimal (cheap) while verifying connectivity.
 *
 * Run with: php vendor/bin/phpunit tests/Integration/Vendor/LiveVendorApiTest.php
 */
class LiveVendorApiTest extends TestCase
{
    private static ?VendorRegistry $vendorRegistry = null;

    public static function setUpBeforeClass(): void
    {
        // Load .env the same way examples do
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    if (! empty($name) && ! empty($value)) {
                        $_ENV[$name] = $value;
                        putenv("{$name}={$value}");
                    }
                }
            }
        }

        self::$vendorRegistry = VendorRegistry::fromEnvironment();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VendorRegistry: verify keys are loaded
    // ═══════════════════════════════════════════════════════════════════════════

    public function testVendorRegistryLoadsKeysFromEnvironment(): void
    {
        $registry = self::$vendorRegistry;

        $this->assertTrue($registry->isAvailable('anthropic'), 'ANTHROPIC_API_KEY should be set');
        $this->assertTrue($registry->isAvailable('openai'), 'OPENAI_API_KEY should be set');
        $this->assertTrue($registry->isAvailable('google'), 'GEMINI_API_KEY should be set');
        $this->assertTrue($registry->hasExternalVendors());

        $vendors = $registry->getAvailableVendors();
        $this->assertCount(3, $vendors);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // OpenAI: live API tests
    // ═══════════════════════════════════════════════════════════════════════════

    public function testOpenAIChatCompletion(): void
    {
        $this->requireKey('openai');

        $adapter = new OpenAIAdapter(self::$vendorRegistry->getKey('openai'));

        $response = $adapter->chat('Reply with exactly one word: hello', [
            'model' => 'gpt-4.1-nano',
            'max_tokens' => 10,
        ]);

        $this->assertNotEmpty($response, 'OpenAI chat should return a non-empty response');
        $this->assertIsString($response);
    }

    public function testOpenAIWebSearch(): void
    {
        $this->requireKey('openai');

        $adapter = new OpenAIAdapter(self::$vendorRegistry->getKey('openai'));

        $response = $adapter->webSearch('What year was PHP first released?');

        $this->assertNotEmpty($response, 'OpenAI web search should return results');
        $this->assertIsString($response);
    }

    public function testOpenAIWebSearchToolExecute(): void
    {
        $this->requireKey('openai');

        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createOpenAITools();

        $webSearchTool = null;
        foreach ($tools as $tool) {
            if ($tool->getName() === 'openai_web_search') {
                $webSearchTool = $tool;
                break;
            }
        }

        $this->assertNotNull($webSearchTool, 'openai_web_search tool should be created');

        $result = $webSearchTool->execute(['query' => 'PHP 8.4 release date']);

        $this->assertTrue($result->isSuccess(), "Web search tool failed: {$result->getContent()}");
        $this->assertNotEmpty($result->getContent());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Gemini: live API tests
    // ═══════════════════════════════════════════════════════════════════════════

    public function testGeminiChatCompletion(): void
    {
        $this->requireKey('google');

        $adapter = new GeminiAdapter(self::$vendorRegistry->getKey('google'));

        $response = $adapter->chat('Reply with exactly one word: hello', [
            'model' => 'gemini-2.5-flash-lite',
            'max_tokens' => 10,
        ]);

        $this->assertNotEmpty($response, 'Gemini chat should return a non-empty response');
        $this->assertIsString($response);
    }

    public function testGeminiGroundedSearch(): void
    {
        $this->requireKey('google');

        $adapter = new GeminiAdapter(self::$vendorRegistry->getKey('google'));

        $response = $adapter->groundedSearch(
            'What is the latest stable version of PHP?',
            'gemini-2.5-flash-lite',
        );

        $this->assertNotEmpty($response, 'Gemini grounded search should return results');
        $this->assertIsString($response);
    }

    public function testGeminiGroundingToolExecute(): void
    {
        $this->requireKey('google');

        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createGeminiTools();

        $groundingTool = null;
        foreach ($tools as $tool) {
            if ($tool->getName() === 'gemini_grounding') {
                $groundingTool = $tool;
                break;
            }
        }

        $this->assertNotNull($groundingTool, 'gemini_grounding tool should be created');

        $result = $groundingTool->execute([
            'query' => 'When was PHP 8.4 released?',
            'model' => 'gemini-2.5-flash-lite',
        ]);

        $this->assertTrue($result->isSuccess(), "Grounding tool failed: {$result->getContent()}");
        $this->assertNotEmpty($result->getContent());
    }

    public function testGeminiCodeExecution(): void
    {
        $this->requireKey('google');

        $adapter = new GeminiAdapter(self::$vendorRegistry->getKey('google'));

        $response = $adapter->codeExecution(
            'Calculate 42 * 17 and print the result',
            'gemini-2.5-flash-lite',
        );

        $this->assertNotEmpty($response, 'Gemini code execution should return output');
        $this->assertIsString($response);
        // The result should contain '714' somewhere (42*17 = 714)
        $this->assertStringContainsString('714', $response, 'Code execution should compute 42*17=714');
    }

    public function testGeminiCodeExecToolExecute(): void
    {
        $this->requireKey('google');

        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createGeminiTools();

        $codeExecTool = null;
        foreach ($tools as $tool) {
            if ($tool->getName() === 'gemini_code_execution') {
                $codeExecTool = $tool;
                break;
            }
        }

        $this->assertNotNull($codeExecTool, 'gemini_code_execution tool should be created');

        $result = $codeExecTool->execute([
            'prompt' => 'Write Python to calculate the sum of numbers 1 to 100 and print it',
            'model' => 'gemini-2.5-flash-lite',
        ]);

        $this->assertTrue($result->isSuccess(), "Code exec tool failed: {$result->getContent()}");
        $this->assertStringContainsString('5050', $result->getContent(), 'Should compute sum 1..100 = 5050');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Cross-vendor: VendorChatTool with real APIs
    // ═══════════════════════════════════════════════════════════════════════════

    public function testVendorChatToolWithOpenAI(): void
    {
        $this->requireKey('openai');

        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createAllTools();

        $chatTool = null;
        foreach ($tools as $tool) {
            if ($tool->getName() === 'vendor_chat') {
                $chatTool = $tool;
                break;
            }
        }

        $this->assertNotNull($chatTool, 'vendor_chat tool should be created');

        $result = $chatTool->execute([
            'prompt' => 'Reply with exactly: "OpenAI here"',
            'vendor' => 'openai',
            'model' => 'gpt-4.1-nano',
        ]);

        $this->assertTrue($result->isSuccess(), "Vendor chat (openai) failed: {$result->getContent()}");
        $this->assertNotEmpty($result->getContent());
    }

    public function testVendorChatToolWithGemini(): void
    {
        $this->requireKey('google');

        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createAllTools();

        $chatTool = null;
        foreach ($tools as $tool) {
            if ($tool->getName() === 'vendor_chat') {
                $chatTool = $tool;
                break;
            }
        }

        $this->assertNotNull($chatTool, 'vendor_chat tool should be created');

        $result = $chatTool->execute([
            'prompt' => 'Reply with exactly: "Gemini here"',
            'vendor' => 'google',
            'model' => 'gemini-2.5-flash-lite',
        ]);

        $this->assertTrue($result->isSuccess(), "Vendor chat (google) failed: {$result->getContent()}");
        $this->assertNotEmpty($result->getContent());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Full factory: all tools created and definitions valid
    // ═══════════════════════════════════════════════════════════════════════════

    public function testFullFactoryCreatesAllTools(): void
    {
        $factory = new CrossVendorToolFactory(self::$vendorRegistry);
        $tools = $factory->createAllTools();

        // With all 3 keys: vendor_chat + 3 OpenAI + 3 Gemini = 7
        $this->assertCount(7, $tools, 'Should have 7 tools with all vendor keys');

        $toolNames = array_map(fn ($t) => $t->getName(), $tools);

        $expectedTools = [
            'vendor_chat',
            'openai_web_search',
            'openai_image_generation',
            'openai_text_to_speech',
            'gemini_grounding',
            'gemini_code_execution',
            'gemini_image_generation',
        ];

        foreach ($expectedTools as $expected) {
            $this->assertContains($expected, $toolNames, "Missing tool: {$expected}");
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    private function requireKey(string $vendor): void
    {
        if (! self::$vendorRegistry->isAvailable($vendor)) {
            $envVar = VendorRegistry::getEnvVarName($vendor);
            $this->markTestSkipped("{$envVar} not set in .env -- skipping live {$vendor} test");
        }
    }
}
