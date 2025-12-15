<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG;

use ClaudeAgents\Contracts\ChunkerInterface;
use ClaudeAgents\Contracts\RetrieverInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Complete RAG pipeline orchestration.
 *
 * Manages document indexing, chunking, retrieval, and generation.
 */
class RAGPipeline
{
    private DocumentStore $documentStore;
    private ChunkerInterface $chunker;
    private RetrieverInterface $retriever;
    private LoggerInterface $logger;

    /**
     * @var array<array<string, mixed>> All chunks from all documents
     */
    private array $chunks = [];

    private int $nextChunkId = 0;
    private int $nextDocId = 0;

    public function __construct(
        private readonly ClaudePhp $client,
        ?ChunkerInterface $chunker = null,
        ?RetrieverInterface $retriever = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->documentStore = new DocumentStore();
        $this->chunker = $chunker ?? new Chunker();
        $this->retriever = $retriever ?? new KeywordRetriever();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create a new RAG pipeline.
     */
    public static function create(ClaudePhp $client): self
    {
        return new self($client);
    }

    /**
     * Set the chunker strategy.
     */
    public function withChunker(ChunkerInterface $chunker): self
    {
        $this->chunker = $chunker;

        return $this;
    }

    /**
     * Set the retriever strategy.
     */
    public function withRetriever(RetrieverInterface $retriever): self
    {
        $this->retriever = $retriever;

        return $this;
    }

    /**
     * Set the logger.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Add a document to the knowledge base.
     *
     * @param string $title Document title
     * @param string $content Document content
     * @param array<string, mixed> $metadata Optional metadata
     * @return string Document ID
     */
    public function addDocument(string $title, string $content, array $metadata = []): string
    {
        $docId = (string) $this->nextDocId++;
        $this->logger->debug("Adding document: {$title}");

        // Store the document
        $this->documentStore->add($docId, $title, $content, $metadata);

        // Chunk the document
        $textChunks = $this->chunker->chunk($content);
        $this->logger->debug('Created ' . count($textChunks) . " chunks from {$title}");

        // Store chunks with metadata
        foreach ($textChunks as $index => $text) {
            $chunkId = (string) $this->nextChunkId++;
            $this->chunks[] = [
                'id' => $chunkId,
                'document_id' => $docId,
                'source' => $title,
                'index' => $index,
                'text' => $text,
                'metadata' => array_merge(
                    $metadata,
                    ['chunk_index' => $index, 'total_chunks' => count($textChunks)]
                ),
            ];
        }

        // Update retriever chunks
        $this->retriever->setChunks($this->chunks);

        return $docId;
    }

    /**
     * Query the knowledge base.
     *
     * @param string $question The question to answer
     * @param int $topK Number of sources to retrieve
     * @param array<string, mixed> $filters Metadata filters to apply
     * @return array<string, mixed> Result with answer, sources, and citations
     */
    public function query(string $question, int $topK = 3, array $filters = []): array
    {
        $this->logger->info("RAG Query: {$question}");

        // Retrieve relevant chunks
        $retrievedChunks = $this->retriever->retrieve($question, $topK, $filters);
        $this->logger->debug('Retrieved ' . count($retrievedChunks) . ' chunks');

        if (empty($retrievedChunks)) {
            return [
                'answer' => 'No relevant information found in the knowledge base.',
                'sources' => [],
                'citations' => [],
                'query' => $question,
            ];
        }

        // Build context from retrieved chunks
        $context = "Reference Information:\n\n";
        $sources = [];
        foreach ($retrievedChunks as $i => $chunk) {
            $source = $chunk['source'] ?? 'Unknown';
            $context .= "[Source {$i}] {$source}:\n";
            $context .= $chunk['text'] . "\n\n";

            $sources[] = [
                'index' => $i,
                'source' => $source,
                'text_preview' => substr($chunk['text'], 0, 100) . '...',
                'metadata' => $chunk['metadata'] ?? [],
            ];
        }

        // Generate answer with context
        $prompt = $context .
            "Question: {$question}\n\n" .
            'Answer the question based on the reference information provided above. ' .
            'Cite sources using [Source N] notation. ' .
            'If the information is not in the references, say so clearly.';

        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1536,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $answer = $this->extractTextContent($response->content ?? []);

            return [
                'answer' => $answer,
                'sources' => $sources,
                'citations' => $this->extractCitations($answer),
                'query' => $question,
                'tokens' => [
                    'input' => $response->usage->input_tokens ?? 0,
                    'output' => $response->usage->output_tokens ?? 0,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error("RAG generation failed: {$e->getMessage()}");

            return [
                'answer' => "Error generating answer: {$e->getMessage()}",
                'sources' => $sources,
                'citations' => [],
                'query' => $question,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract citation indices from answer.
     *
     * @param string $answer The generated answer
     * @return array<int> Cited source indices
     */
    private function extractCitations(string $answer): array
    {
        $citations = [];
        if (preg_match_all('/\[Source (\d+)\]/', $answer, $matches)) {
            $citations = array_map('intval', $matches[1]);
        }

        return array_unique($citations);
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get document store.
     */
    public function getDocumentStore(): DocumentStore
    {
        return $this->documentStore;
    }

    /**
     * Get all chunks.
     *
     * @return array<array<string, mixed>>
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * Get chunk count.
     */
    public function getChunkCount(): int
    {
        return count($this->chunks);
    }

    /**
     * Get document count.
     */
    public function getDocumentCount(): int
    {
        return $this->documentStore->count();
    }

    /**
     * Clear all documents and chunks.
     */
    public function clear(): void
    {
        $this->documentStore->clear();
        $this->chunks = [];
        $this->nextChunkId = 0;
        $this->nextDocId = 0;
    }
}
