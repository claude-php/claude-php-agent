<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor;

/**
 * Capabilities that vendor models can provide.
 *
 * Each capability maps to a specific type of tool that
 * the CrossVendorToolFactory can create for the agent.
 */
enum Capability: string
{
    case Chat = 'chat';
    case WebSearch = 'web_search';
    case ImageGeneration = 'image_generation';
    case TextToSpeech = 'text_to_speech';
    case SpeechToText = 'speech_to_text';
    case CodeExecution = 'code_execution';
    case Grounding = 'grounding';
    case DeepResearch = 'deep_research';
}
