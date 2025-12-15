<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;

/**
 * Parses XML/HTML from LLM responses.
 *
 * Supports:
 * - XML parsing with namespace support
 * - HTML parsing and cleaning
 * - XPath queries for element extraction
 * - Conversion to array structures
 */
class XmlParser implements ParserInterface
{
    /**
     * @var bool Whether to parse as HTML instead of XML
     */
    private bool $asHtml = false;

    /**
     * @var bool Whether to suppress XML errors
     */
    private bool $suppressErrors = true;

    /**
     * @var bool Whether to return as array instead of SimpleXMLElement
     */
    private bool $asArray = true;

    /**
     * Parse as HTML instead of XML.
     *
     * @return self
     */
    public function asHtml(): self
    {
        $this->asHtml = true;

        return $this;
    }

    /**
     * Return as SimpleXMLElement instead of array.
     *
     * @return self
     */
    public function asObject(): self
    {
        $this->asArray = false;

        return $this;
    }

    /**
     * Show XML parsing errors.
     *
     * @return self
     */
    public function showErrors(): self
    {
        $this->suppressErrors = false;

        return $this;
    }

    /**
     * Parse XML/HTML from text.
     *
     * @param string $text The text containing XML/HTML
     * @throws \RuntimeException If parsing fails
     * @return array<string, mixed>|\SimpleXMLElement Parsed structure
     */
    public function parse(string $text): array|\SimpleXMLElement
    {
        // Extract XML from markdown code blocks if present
        $xml = $this->extractXml($text);

        if ($this->suppressErrors) {
            libxml_use_internal_errors(true);
        }

        try {
            if ($this->asHtml) {
                $dom = new \DOMDocument();
                $dom->loadHTML($xml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $element = simplexml_import_dom($dom);
            } else {
                $element = simplexml_load_string($xml);
            }

            if ($element === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = ! empty($errors) ? $errors[0]->message : 'Unknown XML parsing error';

                throw new \RuntimeException('Failed to parse XML: ' . trim($errorMsg));
            }

            if ($this->asArray) {
                return $this->xmlToArray($element);
            }

            return $element;
        } finally {
            if ($this->suppressErrors) {
                libxml_clear_errors();
            }
        }
    }

    /**
     * Extract XML from text (handles code blocks).
     *
     * @param string $text The text to extract from
     * @return string The extracted XML
     */
    private function extractXml(string $text): string
    {
        // Try to find XML in code blocks
        if (preg_match('/```(?:xml|html)\s*([\s\S]*?)\s*```/', $text, $matches)) {
            return $matches[1];
        }

        // Try to find raw XML (starts with < or <?xml)
        if (preg_match('/<\?xml[\s\S]*/', $text, $matches)) {
            return $matches[0];
        }

        if (preg_match('/<[\s\S]+>/', $text, $matches)) {
            return $matches[0];
        }

        // Return as-is if no patterns match
        return $text;
    }

    /**
     * Convert SimpleXMLElement to array.
     *
     * @param \SimpleXMLElement $xml The XML element
     * @return array<string, mixed> Array representation
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $json = json_encode($xml);
        $array = json_decode($json, true);

        return is_array($array) ? $array : [];
    }

    /**
     * Query XML using XPath.
     *
     * @param string $text The XML text
     * @param string $xpath XPath query
     * @return array<string> Matching element values
     */
    public function xpath(string $text, string $xpath): array
    {
        $xml = $this->parse($text);

        if ($xml instanceof \SimpleXMLElement) {
            $results = $xml->xpath($xpath);
            if ($results === false) {
                return [];
            }

            return array_map(fn ($el) => (string) $el, $results);
        }

        throw new \RuntimeException('XPath queries require parsing as object. Call asObject() first.');
    }

    /**
     * Extract text content from all elements.
     *
     * @param string $text The XML/HTML text
     * @return string Plain text content
     */
    public function extractText(string $text): string
    {
        $xml = $this->extractXml($text);

        if ($this->asHtml) {
            return strip_tags($xml);
        }

        $element = simplexml_load_string($xml);
        if ($element === false) {
            return '';
        }

        return (string) $element;
    }

    /**
     * Extract specific element by tag name.
     *
     * @param string $text The XML text
     * @param string $tagName Tag name to extract
     * @return array<string> Element values
     */
    public function extractTag(string $text, string $tagName): array
    {
        $pattern = "/<{$tagName}[^>]*>(.*?)<\\/{$tagName}>/s";

        if (preg_match_all($pattern, $text, $matches)) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        if ($this->asHtml) {
            return "Return your response as valid HTML. Wrap it in ```html code block.\n\n" .
                   "Example:\n```html\n<div>\n  <p>Content here</p>\n</div>\n```";
        }

        return "Return your response as valid XML. Wrap it in ```xml code block.\n\n" .
               "Example:\n```xml\n<?xml version=\"1.0\"?>\n<root>\n  <item>Value</item>\n</root>\n```";
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->asHtml ? 'html' : 'xml';
    }
}
