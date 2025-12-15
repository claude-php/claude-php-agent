<?php

declare(strict_types=1);

namespace ClaudeAgents\Conversation\Export;

use ClaudeAgents\Contracts\SessionExporterInterface;
use ClaudeAgents\Conversation\Session;

/**
 * Export sessions to HTML format.
 */
class HtmlSessionExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        $includeMetadata = $options['include_metadata'] ?? false;
        $includeStyles = $options['include_styles'] ?? true;
        $title = $options['title'] ?? 'Conversation';

        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '    <meta charset="UTF-8">';
        $html[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = "    <title>{$title}</title>";

        if ($includeStyles) {
            $html[] = $this->getStyles();
        }

        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '    <div class="container">';
        $html[] = '        <header>';
        $html[] = "            <h1>{$title}</h1>";
        $html[] = '            <div class="session-info">';
        $html[] = "                <p><strong>Session ID:</strong> {$session->getId()}</p>";
        $html[] = "                <p><strong>Turn Count:</strong> {$session->getTurnCount()}</p>";
        $html[] = '                <p><strong>Created:</strong> ' . date('Y-m-d H:i:s', (int)$session->getCreatedAt()) . '</p>';
        $html[] = '            </div>';
        $html[] = '        </header>';
        $html[] = '        <main class="conversation">';

        foreach ($session->getTurns() as $turn) {
            $timestamp = date('H:i:s', (int)$turn->getTimestamp());

            $html[] = '            <div class="turn">';
            $html[] = '                <div class="message user-message">';
            $html[] = '                    <div class="message-header">';
            $html[] = '                        <span class="sender">User</span>';
            $html[] = "                        <span class=\"timestamp\">{$timestamp}</span>";
            $html[] = '                    </div>';
            $html[] = '                    <div class="message-content">' . nl2br(htmlspecialchars($turn->getUserInput())) . '</div>';
            $html[] = '                </div>';
            $html[] = '                <div class="message agent-message">';
            $html[] = '                    <div class="message-header">';
            $html[] = '                        <span class="sender">Agent</span>';
            $html[] = "                        <span class=\"timestamp\">{$timestamp}</span>";
            $html[] = '                    </div>';
            $html[] = '                    <div class="message-content">' . nl2br(htmlspecialchars($turn->getAgentResponse())) . '</div>';
            $html[] = '                </div>';

            if ($includeMetadata && ! empty($turn->getMetadata())) {
                $html[] = '                <details class="metadata">';
                $html[] = '                    <summary>Metadata</summary>';
                $html[] = '                    <pre>' . htmlspecialchars(json_encode($turn->getMetadata(), JSON_PRETTY_PRINT)) . '</pre>';
                $html[] = '                </details>';
            }

            $html[] = '            </div>';
        }

        $html[] = '        </main>';
        $html[] = '    </div>';
        $html[] = '</body>';
        $html[] = '</html>';

        return implode("\n", $html);
    }

    private function getStyles(): string
    {
        return <<<'STYLES'
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
                    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                    header { padding: 30px; border-bottom: 2px solid #e0e0e0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0; }
                    header h1 { font-size: 28px; margin-bottom: 15px; }
                    .session-info p { margin: 5px 0; opacity: 0.9; }
                    .conversation { padding: 30px; }
                    .turn { margin-bottom: 30px; }
                    .message { margin: 10px 0; padding: 15px; border-radius: 8px; }
                    .user-message { background: #e3f2fd; margin-right: 50px; }
                    .agent-message { background: #f3e5f5; margin-left: 50px; }
                    .message-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; color: #666; }
                    .sender { font-weight: bold; }
                    .timestamp { opacity: 0.7; }
                    .message-content { line-height: 1.6; color: #333; }
                    .metadata { margin: 10px 0; padding: 10px; background: #fafafa; border-radius: 4px; font-size: 12px; }
                    .metadata summary { cursor: pointer; font-weight: bold; margin-bottom: 10px; }
                    .metadata pre { background: white; padding: 10px; border-radius: 4px; overflow-x: auto; }
                </style>
            STYLES;
    }

    public function getFormat(): string
    {
        return 'html';
    }

    public function getExtension(): string
    {
        return 'html';
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }
}
