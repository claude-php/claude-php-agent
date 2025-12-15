<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\RAG;

use ClaudeAgents\RAG\Chunker;
use ClaudeAgents\RAG\KeywordRetriever;
use ClaudeAgents\RAG\RAGPipeline;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;

class RAGPipelineTest extends TestCase
{
    private ClaudePhp $client;
    private RAGPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClaudePhp::class);
        $this->pipeline = new RAGPipeline($this->client);
    }

    public function testCreateFactoryMethod(): void
    {
        $pipeline = RAGPipeline::create($this->client);

        $this->assertInstanceOf(RAGPipeline::class, $pipeline);
    }

    public function testWithChunker(): void
    {
        $chunker = new Chunker(chunkSize: 100, overlap: 10);

        $result = $this->pipeline->withChunker($chunker);

        $this->assertSame($this->pipeline, $result);
    }

    public function testWithRetriever(): void
    {
        $retriever = new KeywordRetriever();

        $result = $this->pipeline->withRetriever($retriever);

        $this->assertSame($this->pipeline, $result);
    }

    public function testAddDocument(): void
    {
        $docId = $this->pipeline->addDocument(
            'Test Document',
            'This is test content.',
            ['author' => 'Test Author']
        );

        $this->assertIsString($docId);
        $this->assertEquals(1, $this->pipeline->getDocumentCount());
        $this->assertGreaterThan(0, $this->pipeline->getChunkCount());
    }

    public function testAddMultipleDocuments(): void
    {
        $this->pipeline->addDocument('Doc 1', 'Content 1');
        $this->pipeline->addDocument('Doc 2', 'Content 2');
        $this->pipeline->addDocument('Doc 3', 'Content 3');

        $this->assertEquals(3, $this->pipeline->getDocumentCount());
    }

    public function testGetDocumentStore(): void
    {
        $this->pipeline->addDocument('Test', 'Content');

        $store = $this->pipeline->getDocumentStore();

        $this->assertEquals(1, $store->count());
    }

    public function testGetChunks(): void
    {
        $this->pipeline->addDocument('Test', 'This is a test. It has sentences.');

        $chunks = $this->pipeline->getChunks();

        $this->assertIsArray($chunks);
        $this->assertNotEmpty($chunks);
        $this->assertArrayHasKey('text', $chunks[0]);
        $this->assertArrayHasKey('source', $chunks[0]);
    }

    public function testGetChunkCount(): void
    {
        $this->assertEquals(0, $this->pipeline->getChunkCount());

        $this->pipeline->addDocument('Test', 'Short content.');

        $this->assertGreaterThan(0, $this->pipeline->getChunkCount());
    }

    public function testGetDocumentCount(): void
    {
        $this->assertEquals(0, $this->pipeline->getDocumentCount());

        $this->pipeline->addDocument('Test', 'Content');

        $this->assertEquals(1, $this->pipeline->getDocumentCount());
    }

    public function testClear(): void
    {
        $this->pipeline->addDocument('Test 1', 'Content 1');
        $this->pipeline->addDocument('Test 2', 'Content 2');

        $this->pipeline->clear();

        $this->assertEquals(0, $this->pipeline->getDocumentCount());
        $this->assertEquals(0, $this->pipeline->getChunkCount());
    }

    public function testQueryWithNoDocuments(): void
    {
        $result = $this->pipeline->query('What is PHP?');

        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('sources', $result);
        $this->assertEmpty($result['sources']);
        $this->assertStringContainsString('No relevant information', $result['answer']);
    }

    public function testQueryReturnsAnswer(): void
    {
        // Add document
        $this->pipeline->addDocument(
            'PHP Guide',
            'PHP is a server-side scripting language. It is used for web development.'
        );

        // Mock the Claude API response
        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'PHP is a server-side scripting language used for web development. [Source 0]'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);

        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->pipeline->query('What is PHP?', topK: 1);

        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('sources', $result);
        $this->assertArrayHasKey('citations', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('tokens', $result);

        $this->assertNotEmpty($result['answer']);
        $this->assertNotEmpty($result['sources']);
        $this->assertEquals('What is PHP?', $result['query']);
    }

    public function testQueryExtractsCitations(): void
    {
        $this->pipeline->addDocument('Test', 'Content here.');

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'According to [Source 0] and [Source 1], the answer is...'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->pipeline->query('Test query');

        $this->assertContains(0, $result['citations']);
        $this->assertContains(1, $result['citations']);
    }

    public function testQueryHandlesApiError(): void
    {
        $this->pipeline->addDocument('Test', 'Content');

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willThrowException(new \Exception('API Error'));
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->pipeline->query('Test query');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('API Error', $result['answer']);
    }

    public function testQueryWithTopK(): void
    {
        // Add multiple documents
        for ($i = 1; $i <= 5; $i++) {
            $this->pipeline->addDocument("Doc $i", "Content for document $i with relevant info.");
        }

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'The answer based on sources...'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 200, output_tokens: 100)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->pipeline->query('relevant info', topK: 3);

        // Should return at most 3 sources
        $this->assertLessThanOrEqual(3, count($result['sources']));
    }

    public function testChunksContainMetadata(): void
    {
        $this->pipeline->addDocument(
            'Test Doc',
            'First sentence. Second sentence. Third sentence.',
            ['category' => 'test']
        );

        $chunks = $this->pipeline->getChunks();

        $this->assertArrayHasKey('metadata', $chunks[0]);
        $this->assertEquals('test', $chunks[0]['metadata']['category']);
        $this->assertArrayHasKey('chunk_index', $chunks[0]['metadata']);
        $this->assertArrayHasKey('total_chunks', $chunks[0]['metadata']);
    }

    public function testSourcesContainPreview(): void
    {
        $longContent = str_repeat('This is a very long piece of content that should be truncated in the preview. ', 10);
        $this->pipeline->addDocument('Long Doc', $longContent);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Answer based on source.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $messagesApi = $this->createMock(\ClaudePhp\Resources\Messages\Messages::class);
        $messagesApi->method('create')->willReturn($mockResponse);
        $this->client->method('messages')->willReturn($messagesApi);

        $result = $this->pipeline->query('test query');

        $this->assertNotEmpty($result['sources']);
        $this->assertArrayHasKey('text_preview', $result['sources'][0]);
        $this->assertStringContainsString('...', $result['sources'][0]['text_preview']);
    }
}
