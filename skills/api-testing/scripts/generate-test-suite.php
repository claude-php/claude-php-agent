<?php

/**
 * Generate an API test suite skeleton.
 *
 * Usage: php generate-test-suite.php <base-url> <endpoints-json>
 */

$baseUrl = $argv[1] ?? 'https://api.example.com';
$endpointsFile = $argv[2] ?? null;

$endpoints = [];
if ($endpointsFile && file_exists($endpointsFile)) {
    $endpoints = json_decode(file_get_contents($endpointsFile), true) ?? [];
}

$template = <<<'PHP'
<?php

declare(strict_types=1);

namespace Tests\Api;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class %sTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => '%s',
            'timeout' => 30,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
%s
}
PHP;

foreach ($endpoints as $name => $config) {
    $methods = '';
    $method = strtoupper($config['method'] ?? 'GET');
    $path = $config['path'] ?? '/';

    $methods .= "\n    public function test_{$name}_success(): void\n    {\n";
    $methods .= "        \$response = \$this->client->request('{$method}', '{$path}');\n";
    $methods .= "        \$this->assertEquals(200, \$response->getStatusCode());\n";
    $methods .= "    }\n";

    $methods .= "\n    public function test_{$name}_not_found(): void\n    {\n";
    $methods .= "        \$response = \$this->client->request('{$method}', '{$path}/nonexistent');\n";
    $methods .= "        \$this->assertEquals(404, \$response->getStatusCode());\n";
    $methods .= "    }\n";

    $className = str_replace(['-', '_'], '', ucwords($name, '-_'));
    echo sprintf($template, $className, $baseUrl, $methods);
    echo "\n\n";
}
