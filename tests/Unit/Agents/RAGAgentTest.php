<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\RAGAgent;
use ClaudeAgents\RAG\RAGPipeline;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RAGAgentTest extends TestCase
{
    private ClaudePhp $client;
    private RAGAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new RAGAgent($this->client);
    }

    public function testConstruction(): void
    {
        $agent = new RAGAgent($this->client);

        $this->assertInstanceOf(RAGAgent::class, $agent);
    }

    public function testConstructionWithOptions(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $agent = new RAGAgent($this->client, [
            'name' => 'test_rag_agent',
            'logger' => $logger,
        ]);

        $this->assertEquals('test_rag_agent', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new RAGAgent($this->client, ['name' => 'custom_name']);

        $this->assertEquals('custom_name', $agent->getName());
    }

    public function testGetNameWithDefault(): void
    {
        $this->assertEquals('rag_agent', $this->agent->getName());
    }

    public function testAddDocument(): void
    {
        $result = $this->agent->addDocument(
            'Test Document',
            'This is test content.',
            ['author' => 'Test']
        );

        $this->assertSame($this->agent, $result);
    }

    public function testAddDocuments(): void
    {
        $documents = [
            ['title' => 'Doc 1', 'content' => 'Content 1'],
            ['title' => 'Doc 2', 'content' => 'Content 2', 'metadata' => ['key' => 'value']],
            ['title' => 'Doc 3', 'content' => 'Content 3'],
        ];

        $result = $this->agent->addDocuments($documents);

        $this->assertSame($this->agent, $result);
    }

    public function testGetRag(): void
    {
        $rag = $this->agent->getRag();

        $this->assertInstanceOf(RAGPipeline::class, $rag);
    }

    public function testRunWithoutDocuments(): void
    {
        $result = $this->agent->run('What is PHP?');

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No documents in knowledge base', $result->getError());
    }

    public function testRunWithDocuments(): void
    {
        // Add documents
        $this->agent->addDocument(
            'PHP Basics',
            'PHP is a server-side scripting language used for web development.'
        );

        // Mock Claude API response
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'PHP is a server-side scripting language. [Source 0]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        // Run the agent
        $result = $this->agent->run('What is PHP?');

        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
    }

    public function testRunMetadataContainsAnswer(): void
    {
        $this->agent->addDocument('Test', 'Content');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Test answer'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Test question');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('answer', $metadata);
        $this->assertEquals('Test answer', $metadata['answer']);
    }

    public function testRunMetadataContainsSources(): void
    {
        $this->agent->addDocument('PHP Guide', 'PHP content here.');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer text'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('sources', $metadata);
        $this->assertIsArray($metadata['sources']);
    }

    public function testRunMetadataContainsCitations(): void
    {
        $this->agent->addDocument('Test', 'Content');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer with [Source 0] citation'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('citations', $metadata);
        $this->assertIsArray($metadata['citations']);
    }

    public function testRunMetadataContainsTokens(): void
    {
        $this->agent->addDocument('Test', 'Content');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 150, output_tokens: 75)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('tokens', $metadata);
        $this->assertEquals(150, $metadata['tokens']['input']);
        $this->assertEquals(75, $metadata['tokens']['output']);
    }

    public function testRunMetadataContainsCounts(): void
    {
        $this->agent->addDocument('Doc 1', 'Content 1');
        $this->agent->addDocument('Doc 2', 'Content 2');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('document_count', $metadata);
        $this->assertArrayHasKey('chunk_count', $metadata);
        $this->assertEquals(2, $metadata['document_count']);
        $this->assertGreaterThan(0, $metadata['chunk_count']);
    }

    public function testRunHandlesRagError(): void
    {
        $this->agent->addDocument('Test', 'Content');

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willThrowException(new \RuntimeException('API Error'));
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    public function testRunReturnsIterationCount(): void
    {
        $this->agent->addDocument('Test', 'Content');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->agent->run('Question');

        $this->assertEquals(1, $result->getIterations());
    }

    public function testRunWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('RAG Agent:'));

        $agent = new RAGAgent($this->client, ['logger' => $logger]);
        $agent->addDocument('Test', 'Content');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $agent->run('Test question');
    }

    public function testFluentInterface(): void
    {
        $result = $this->agent
            ->addDocument('Doc 1', 'Content 1')
            ->addDocument('Doc 2', 'Content 2')
            ->addDocuments([
                ['title' => 'Doc 3', 'content' => 'Content 3'],
            ]);

        $this->assertSame($this->agent, $result);
        $this->assertEquals(3, $this->agent->getRag()->getDocumentCount());
    }
}
