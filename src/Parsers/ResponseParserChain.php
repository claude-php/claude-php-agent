<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Exceptions\ParseException;

/**
 * Chain of Responsibility pattern for response parsing.
 *
 * Tries multiple parsers in sequence until one succeeds.
 *
 * @example
 * ```php
 * $chain = new ResponseParserChain([
 *     new JsonResponseParser(),
 *     new XmlResponseParser(),
 *     new MarkdownResponseParser(),
 * ]);
 * $result = $chain->parse($response);
 * ```
 */
class ResponseParserChain implements ResponseParserInterface
{
    /**
     * @var array<ResponseParserInterface>
     */
    private array $parsers = [];

    /**
     * @param array<ResponseParserInterface> $parsers
     */
    public function __construct(array $parsers = [])
    {
        $this->parsers = $parsers;
    }

    /**
     * Add a parser to the chain.
     */
    public function addParser(ResponseParserInterface $parser): self
    {
        $this->parsers[] = $parser;

        return $this;
    }

    /**
     * @throws ParseException
     * @return mixed
     */
    public function parse(string $text)
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($text)) {
                return $parser->parse($text);
            }
        }

        throw new ParseException('No parser could handle the response');
    }

    public function canParse(string $text): bool
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($text)) {
                return true;
            }
        }

        return false;
    }
}
