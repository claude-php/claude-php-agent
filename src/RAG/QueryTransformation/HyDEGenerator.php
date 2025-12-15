<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\QueryTransformation;

use ClaudePhp\ClaudePhp;

/**
 * HyDE (Hypothetical Document Embeddings) query transformation.
 *
 * Generates a hypothetical answer to the query, then uses that
 * to improve retrieval by searching for similar documents.
 */
class HyDEGenerator
{
    /**
     * @param ClaudePhp $client Claude API client
     * @param string $model Model to use for generation
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly string $model = 'claude-3-haiku-20240307',
    ) {
    }

    /**
     * Generate a hypothetical document for the query.
     *
     * This document represents what an ideal answer might look like,
     * which can be used for semantic search instead of the original query.
     */
    public function generate(string $query): string
    {
        $prompt = <<<PROMPT
            Write a hypothetical document that would perfectly answer the following question.
            Make it factual, detailed, and informative. Do not say "I don't know" or mention that
            this is hypothetical - just write as if you're providing the real answer.

            Question: {$query}

            Hypothetical answer:
            PROMPT;

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $content = $response->content[0] ?? null;
            if ($content === null || ! isset($content['text'])) {
                return $query;
            }

            return trim($content['text']);
        } catch (\Throwable $e) {
            // Fallback to original query on error
            return $query;
        }
    }

    /**
     * Generate query with HyDE augmentation.
     *
     * Combines original query with hypothetical document for enhanced retrieval.
     */
    public function augmentQuery(string $query): string
    {
        $hypothetical = $this->generate($query);

        // Combine query and hypothetical for richer semantic search
        return "{$query}\n\n{$hypothetical}";
    }
}
