<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Parsers\JsonParser;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;

/**
 * Chain that wraps a single LLM call.
 *
 * Integrates with PromptTemplate for variable substitution and
 * supports output parsing for structured responses.
 */
class LLMChain extends Chain
{
    private ?PromptTemplate $promptTemplate = null;

    /**
     * @var callable|null
     */
    private $outputParser = null;

    /**
     * @var array<string, string>
     */
    private array $modelParams = [
        'model' => 'claude-sonnet-4-5',
        'max_tokens' => '1024',
    ];

    public function __construct(
        private readonly ClaudePhp $client,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    /**
     * Create a new LLM chain.
     */
    public static function create(ClaudePhp $client, ?LoggerInterface $logger = null): self
    {
        return new self($client, $logger);
    }

    /**
     * Set the prompt template.
     */
    public function withPromptTemplate(PromptTemplate $template): self
    {
        $this->promptTemplate = $template;

        return $this;
    }

    /**
     * Set a custom output parser.
     *
     * Parser can be a callable that takes a string and returns mixed.
     */
    public function withOutputParser(callable $parser): self
    {
        $this->outputParser = $parser;

        return $this;
    }

    /**
     * Use JSON parser for output (default).
     */
    public function withJsonParser(): self
    {
        $this->outputParser = fn ($text) => (new JsonParser())->parse($text);

        return $this;
    }

    /**
     * Set the model to use.
     */
    public function withModel(string $model): self
    {
        $this->modelParams['model'] = $model;

        return $this;
    }

    /**
     * Set max tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->modelParams['max_tokens'] = (string) $maxTokens;

        return $this;
    }

    /**
     * Set temperature.
     */
    public function withTemperature(float $temperature): self
    {
        $this->modelParams['temperature'] = (string) $temperature;

        return $this;
    }

    protected function execute(ChainInput $input): ChainOutput
    {
        try {
            // Format the prompt if template is set
            $prompt = $this->formatPrompt($input->all());

            $this->logger->info('Executing LLM chain', [
                'model' => $this->modelParams['model'],
                'prompt_length' => strlen($prompt),
            ]);

            // Call the LLM
            $response = $this->client->messages()->create([
                'model' => $this->modelParams['model'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => (int) $this->modelParams['max_tokens'],
            ]);

            // Extract the text response from Message object
            $responseText = '';
            if (is_array($response->content)) {
                foreach ($response->content as $block) {
                    if (isset($block['type']) && $block['type'] === 'text') {
                        $responseText = $block['text'];

                        break;
                    }
                }
            }

            // Parse the output if parser is set
            $output = $responseText;
            if ($this->outputParser !== null) {
                try {
                    $output = ($this->outputParser)($responseText);
                } catch (\Throwable $e) {
                    $this->logger->warning("Output parsing failed, returning raw text: {$e->getMessage()}");
                    $output = $responseText;
                }
            }

            // Prepare metadata
            $metadata = [
                'model' => $this->modelParams['model'],
                'input_tokens' => $response->usage->input_tokens ?? 0,
                'output_tokens' => $response->usage->output_tokens ?? 0,
            ];

            // Return as structured output
            $result = is_array($output) ? $output : ['result' => $output];

            return ChainOutput::create($result, $metadata);
        } catch (\Throwable $e) {
            $this->logger->error("LLM execution failed: {$e->getMessage()}");

            throw new ChainExecutionException(
                "LLM execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Format the prompt with input variables.
     *
     * @param array<string, mixed> $input
     */
    private function formatPrompt(array $input): string
    {
        if ($this->promptTemplate === null) {
            // If no template, use input as prompt if it has a 'prompt' key
            return $input['prompt'] ?? json_encode($input);
        }

        // Validate and format the template
        $this->promptTemplate->validate($input);

        return $this->promptTemplate->format($input);
    }
}
