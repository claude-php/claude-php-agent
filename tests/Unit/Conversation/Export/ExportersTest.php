<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Export;

use ClaudeAgents\Conversation\Export\CsvSessionExporter;
use ClaudeAgents\Conversation\Export\HtmlSessionExporter;
use ClaudeAgents\Conversation\Export\JsonSessionExporter;
use ClaudeAgents\Conversation\Export\MarkdownSessionExporter;
use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class ExportersTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session('test_session');
        $this->session->setState(['user_id' => '123', 'language' => 'en']);
        $this->session->addTurn(new Turn('Hello', 'Hi there'));
        $this->session->addTurn(new Turn('How are you?', 'I am fine, thank you'));
    }

    public function test_json_exporter_exports_session(): void
    {
        $exporter = new JsonSessionExporter();

        $output = $exporter->export($this->session);

        $this->assertNotEmpty($output);
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertSame('test_session', $data['session_id']);
        $this->assertCount(2, $data['turns']);
    }

    public function test_json_exporter_metadata(): void
    {
        $exporter = new JsonSessionExporter();

        $this->assertSame('json', $exporter->getFormat());
        $this->assertSame('json', $exporter->getExtension());
        $this->assertSame('application/json', $exporter->getMimeType());
    }

    public function test_json_exporter_without_metadata(): void
    {
        $exporter = new JsonSessionExporter();

        $output = $exporter->export($this->session, ['include_metadata' => false]);
        $data = json_decode($output, true);

        $this->assertArrayNotHasKey('state', $data);
        $this->assertArrayNotHasKey('metadata', $data['turns'][0]);
    }

    public function test_csv_exporter_exports_session(): void
    {
        $exporter = new CsvSessionExporter();

        $output = $exporter->export($this->session);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Turn ID', $output);
        $this->assertStringContainsString('Hello', $output);
        $this->assertStringContainsString('Hi there', $output);
    }

    public function test_csv_exporter_metadata(): void
    {
        $exporter = new CsvSessionExporter();

        $this->assertSame('csv', $exporter->getFormat());
        $this->assertSame('csv', $exporter->getExtension());
        $this->assertSame('text/csv', $exporter->getMimeType());
    }

    public function test_csv_exporter_with_custom_delimiter(): void
    {
        $exporter = new CsvSessionExporter();

        $output = $exporter->export($this->session, ['delimiter' => ';']);

        $this->assertStringContainsString(';', $output);
    }

    public function test_markdown_exporter_exports_session(): void
    {
        $exporter = new MarkdownSessionExporter();

        $output = $exporter->export($this->session);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('# Conversation:', $output);
        $this->assertStringContainsString('**User:**', $output);
        $this->assertStringContainsString('**Agent:**', $output);
        $this->assertStringContainsString('Hello', $output);
    }

    public function test_markdown_exporter_metadata(): void
    {
        $exporter = new MarkdownSessionExporter();

        $this->assertSame('markdown', $exporter->getFormat());
        $this->assertSame('md', $exporter->getExtension());
        $this->assertSame('text/markdown', $exporter->getMimeType());
    }

    public function test_markdown_exporter_chat_style(): void
    {
        $exporter = new MarkdownSessionExporter();

        $output = $exporter->export($this->session, ['style' => 'chat']);

        $this->assertStringContainsString('👤 User', $output);
        $this->assertStringContainsString('🤖 Agent', $output);
        $this->assertStringContainsString('>', $output);
    }

    public function test_markdown_exporter_detailed_style(): void
    {
        $exporter = new MarkdownSessionExporter();

        $output = $exporter->export($this->session, ['style' => 'detailed']);

        $this->assertStringContainsString('# Detailed Conversation Report', $output);
        $this->assertStringContainsString('## Summary', $output);
        $this->assertStringContainsString('| Property | Value |', $output);
    }

    public function test_html_exporter_exports_session(): void
    {
        $exporter = new HtmlSessionExporter();

        $output = $exporter->export($this->session);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('Hello', $output);
        $this->assertStringContainsString('Hi there', $output);
    }

    public function test_html_exporter_metadata(): void
    {
        $exporter = new HtmlSessionExporter();

        $this->assertSame('html', $exporter->getFormat());
        $this->assertSame('html', $exporter->getExtension());
        $this->assertSame('text/html', $exporter->getMimeType());
    }

    public function test_html_exporter_with_styles(): void
    {
        $exporter = new HtmlSessionExporter();

        $output = $exporter->export($this->session, ['include_styles' => true]);

        $this->assertStringContainsString('<style>', $output);
    }

    public function test_html_exporter_without_styles(): void
    {
        $exporter = new HtmlSessionExporter();

        $output = $exporter->export($this->session, ['include_styles' => false]);

        $this->assertStringNotContainsString('<style>', $output);
    }

    public function test_all_exporters_handle_empty_session(): void
    {
        $emptySession = new Session('empty');

        $jsonExporter = new JsonSessionExporter();
        $csvExporter = new CsvSessionExporter();
        $mdExporter = new MarkdownSessionExporter();
        $htmlExporter = new HtmlSessionExporter();

        $this->assertNotEmpty($jsonExporter->export($emptySession));
        $this->assertNotEmpty($csvExporter->export($emptySession));
        $this->assertNotEmpty($mdExporter->export($emptySession));
        $this->assertNotEmpty($htmlExporter->export($emptySession));
    }
}
