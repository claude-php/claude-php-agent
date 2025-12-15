<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\RAG\RAGPipeline;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * RAG-enabled agent that grounds responses in external knowledge.
 */
class RAGAgent implements AgentInterface
{
    private RAGPipeline $rag;
    private string $name;
    private LoggerInterface $logger;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - top_k: Number of sources to retrieve (default: 3)
     *   - logger: PSR-3 logger
     */
    public function __construct(
        ClaudePhp $client,
        array $options = [],
    ) {
        $this->rag = RAGPipeline::create($client);
        $this->name = $options['name'] ?? 'rag_agent';
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Add a document to the knowledge base.
     *
     * @param string $title Document title
     * @param string $content Document content
     * @param array<string, mixed> $metadata Optional metadata
     * @return self
     */
    public function addDocument(string $title, string $content, array $metadata = []): self
    {
        $this->rag->addDocument($title, $content, $metadata);

        return $this;
    }

    /**
     * Add multiple documents.
     *
     * @param array<array{title: string, content: string, metadata?: array}> $documents
     * @return self
     */
    public function addDocuments(array $documents): self
    {
        foreach ($documents as $doc) {
            $this->rag->addDocument(
                $doc['title'],
                $doc['content'],
                $doc['metadata'] ?? []
            );
        }

        return $this;
    }

    /**
     * Run the agent - answer a question using RAG.
     */
    public function run(string $task): AgentResult
    {
        $this->logger->info("RAG Agent: {$task}");

        if ($this->rag->getDocumentCount() === 0) {
            return AgentResult::failure(
                error: 'No documents in knowledge base. Add documents before querying.',
            );
        }

        try {
            $result = $this->rag->query($task, topK: 3);

            $metadata = [
                'answer' => $result['answer'],
                'sources' => $result['sources'],
                'citations' => $result['citations'],
                'tokens' => $result['tokens'] ?? [],
                'document_count' => $this->rag->getDocumentCount(),
                'chunk_count' => $this->rag->getChunkCount(),
            ];

            if (isset($result['error'])) {
                return AgentResult::failure(
                    error: $result['error'],
                    metadata: $metadata,
                );
            }

            return AgentResult::success(
                answer: $result['answer'],
                messages: [],
                iterations: 1,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            $this->logger->error("RAG Agent failed: {$e->getMessage()}");

            return AgentResult::failure(
                error: $e->getMessage(),
                metadata: [
                    'document_count' => $this->rag->getDocumentCount(),
                    'chunk_count' => $this->rag->getChunkCount(),
                ],
            );
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the RAG pipeline for advanced usage.
     */
    public function getRag(): RAGPipeline
    {
        return $this->rag;
    }
}
