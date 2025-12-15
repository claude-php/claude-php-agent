<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Tools\BuiltIn;

use ClaudeAgents\Tools\BuiltIn\DatabaseTool;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseToolTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        // Use in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test table
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
        ');

        // Insert test data
        $this->pdo->exec("
            INSERT INTO users (name, email) VALUES 
            ('Alice', 'alice@example.com'),
            ('Bob', 'bob@example.com'),
            ('Charlie', 'charlie@example.com')
        ");
    }

    public function testCreateRequiresConnection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DatabaseTool::create([]);
    }

    public function testReadOnlySelect(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'SELECT * FROM users',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(3, $data['count']);
        $this->assertCount(3, $data['rows']);
    }

    public function testReadOnlyBlocksInsert(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'INSERT INTO users (name, email) VALUES (?, ?)',
            'parameters' => ['David', 'david@example.com'],
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('only select', strtolower($result->getContent()));
    }

    public function testParameterizedQuery(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'SELECT * FROM users WHERE name = :name',
            'parameters' => ['name' => 'Alice'],
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('Alice', $data['rows'][0]['name']);
    }

    public function testFullAccessInsert(): void
    {
        $tool = DatabaseTool::fullAccess($this->pdo);

        $result = $tool->execute([
            'query' => 'INSERT INTO users (name, email) VALUES (?, ?)',
            'parameters' => ['David', 'david@example.com'],
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(1, $data['affected_rows']);
    }

    public function testResultLimit(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo, ['max_results' => 2]);

        $result = $tool->execute([
            'query' => 'SELECT * FROM users',
            'limit' => 2,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertEquals(2, $data['count']);
        $this->assertTrue($data['has_more']);
    }

    public function testAllowedTables(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo, [
            'allowed_tables' => ['users'],
        ]);

        // Should work for allowed table
        $result1 = $tool->execute([
            'query' => 'SELECT * FROM users',
        ]);
        $this->assertTrue($result1->isSuccess());

        // Should fail for non-allowed table (if it existed)
        $result2 = $tool->execute([
            'query' => 'SELECT * FROM other_table',
        ]);
        $this->assertTrue($result2->isError());
    }

    public function testExecutionTimeTracking(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'SELECT * FROM users',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('execution_time_ms', $data);
        $this->assertIsNumeric($data['execution_time_ms']);
    }

    public function testColumnInformation(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'SELECT * FROM users LIMIT 1',
        ]);

        $this->assertTrue($result->isSuccess());
        $data = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('columns', $data);
        $this->assertContains('name', $data['columns']);
        $this->assertContains('email', $data['columns']);
    }

    public function testSQLError(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => 'SELECT * FROM nonexistent_table',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('error', strtolower($result->getContent()));
    }

    public function testEmptyQuery(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $result = $tool->execute([
            'query' => '',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('required', strtolower($result->getContent()));
    }

    public function testDangerousOperationsBlocked(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);

        $dangerousQueries = [
            'SELECT LOAD_FILE("/etc/passwd")',
            'SELECT * INTO OUTFILE "/tmp/test"',
        ];

        foreach ($dangerousQueries as $query) {
            $result = $tool->execute(['query' => $query]);
            $this->assertTrue($result->isError(), "Query should be blocked: {$query}");
        }
    }

    public function testInputSchema(): void
    {
        $tool = DatabaseTool::readOnly($this->pdo);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertContains('query', $schema['required']);
    }
}
