<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

/**
 * Library of commonly used prompt templates.
 *
 * Provides pre-built templates for common tasks like classification,
 * summarization, extraction, analysis, and more.
 */
class PromptLibrary
{
    /**
     * Summarization prompt.
     */
    public static function summarization(): PromptTemplate
    {
        return PromptTemplate::create(
            "Summarize the following text in {length} or less:\n\n{text}\n\n" .
            'Summary:'
        );
    }

    /**
     * Classification prompt.
     *
     * @param array<string> $categories List of valid categories
     */
    public static function classification(array $categories): PromptTemplate
    {
        $categoryList = implode(', ', $categories);

        return PromptTemplate::create(
            "Classify the following text into one of these categories: {$categoryList}\n\n" .
            "Text: {text}\n\n" .
            'Category:'
        );
    }

    /**
     * Sentiment analysis prompt.
     */
    public static function sentimentAnalysis(): PromptTemplate
    {
        return PromptTemplate::create(
            'Analyze the sentiment of the following text. ' .
            "Respond with: positive, negative, or neutral.\n\n" .
            "Text: {text}\n\n" .
            'Sentiment:'
        );
    }

    /**
     * Entity extraction prompt.
     */
    public static function entityExtraction(): PromptTemplate
    {
        return PromptTemplate::create(
            'Extract all named entities (people, places, organizations, dates) ' .
            "from the following text:\n\n{text}\n\n" .
            'Entities:'
        );
    }

    /**
     * Question answering prompt.
     */
    public static function questionAnswering(): PromptTemplate
    {
        return PromptTemplate::create(
            "Answer the question based on the provided context.\n\n" .
            "Context: {context}\n\n" .
            "Question: {question}\n\n" .
            'Answer:'
        );
    }

    /**
     * Translation prompt.
     */
    public static function translation(): PromptTemplate
    {
        return PromptTemplate::create(
            "Translate the following text from {source_language} to {target_language}:\n\n" .
            "{text}\n\n" .
            'Translation:'
        );
    }

    /**
     * Code explanation prompt.
     */
    public static function codeExplanation(): PromptTemplate
    {
        return PromptTemplate::create(
            "Explain what the following {language} code does:\n\n" .
            "```{language}\n{code}\n```\n\n" .
            'Explanation:'
        );
    }

    /**
     * Code review prompt.
     */
    public static function codeReview(): PromptTemplate
    {
        return PromptTemplate::create(
            "Review the following {language} code for:\n" .
            "- Bugs and errors\n" .
            "- Performance issues\n" .
            "- Security vulnerabilities\n" .
            "- Best practices\n\n" .
            "```{language}\n{code}\n```\n\n" .
            'Review:'
        );
    }

    /**
     * Text rewriting prompt.
     */
    public static function rewrite(): PromptTemplate
    {
        return PromptTemplate::create(
            "Rewrite the following text to be {style}:\n\n" .
            "{text}\n\n" .
            'Rewritten text:'
        );
    }

    /**
     * Brainstorming prompt.
     */
    public static function brainstorm(): PromptTemplate
    {
        return PromptTemplate::create(
            "Generate {count} creative ideas for {topic}.\n\n" .
            'Ideas:'
        );
    }

    /**
     * Comparison prompt.
     */
    public static function comparison(): PromptTemplate
    {
        return PromptTemplate::create(
            "Compare {item1} and {item2} in terms of:\n" .
            "- Pros and cons\n" .
            "- Key differences\n" .
            "- Use cases\n\n" .
            'Comparison:'
        );
    }

    /**
     * Pros and cons analysis.
     */
    public static function prosAndCons(): PromptTemplate
    {
        return PromptTemplate::create(
            "List the pros and cons of {topic}:\n\n" .
            "Pros:\n\n" .
            'Cons:'
        );
    }

    /**
     * Step-by-step explanation.
     */
    public static function stepByStep(): PromptTemplate
    {
        return PromptTemplate::create(
            "Explain how to {task} in a step-by-step manner:\n\n" .
            'Steps:'
        );
    }

    /**
     * JSON output format instruction.
     */
    public static function jsonOutput(): PromptTemplate
    {
        return PromptTemplate::create(
            "{instruction}\n\n" .
            "Input: {input}\n\n" .
            "Provide your response as valid JSON with the following structure:\n" .
            "{schema}\n\n" .
            'JSON Response:'
        );
    }

    /**
     * Chain of thought reasoning.
     */
    public static function chainOfThought(): PromptTemplate
    {
        return PromptTemplate::create(
            "{problem}\n\n" .
            "Let's solve this step by step:\n" .
            "1. First, let's understand what we're being asked\n" .
            "2. Then, let's break down the problem\n" .
            "3. Finally, let's work through the solution\n\n" .
            'Solution:'
        );
    }

    /**
     * Error diagnosis prompt.
     */
    public static function errorDiagnosis(): PromptTemplate
    {
        return PromptTemplate::create(
            "Diagnose the following error:\n\n" .
            "Error: {error}\n\n" .
            "Context: {context}\n\n" .
            "Provide:\n" .
            "1. Root cause\n" .
            "2. Solution\n" .
            "3. Prevention tips\n\n" .
            'Diagnosis:'
        );
    }

    /**
     * Data formatting prompt.
     */
    public static function dataFormatting(): PromptTemplate
    {
        return PromptTemplate::create(
            "Convert the following data from {source_format} to {target_format}:\n\n" .
            "{data}\n\n" .
            'Converted data:'
        );
    }

    /**
     * Meeting notes summary.
     */
    public static function meetingNotesSummary(): PromptTemplate
    {
        return PromptTemplate::create(
            "Summarize the following meeting notes into:\n" .
            "- Key decisions\n" .
            "- Action items\n" .
            "- Next steps\n\n" .
            "Notes: {notes}\n\n" .
            'Summary:'
        );
    }

    /**
     * Email response generator.
     */
    public static function emailResponse(): PromptTemplate
    {
        return PromptTemplate::create(
            "Generate a professional email response with a {tone} tone:\n\n" .
            "Original email: {original_email}\n\n" .
            "Key points to address: {key_points}\n\n" .
            'Response:'
        );
    }

    /**
     * Fact checking prompt.
     */
    public static function factCheck(): PromptTemplate
    {
        return PromptTemplate::create(
            "Fact-check the following statement:\n\n" .
            "{statement}\n\n" .
            "Provide:\n" .
            "1. Verdict (True/False/Partially True/Unverifiable)\n" .
            "2. Explanation\n" .
            "3. Sources (if available)\n\n" .
            'Analysis:'
        );
    }

    /**
     * Create a custom template from a chat conversation style.
     */
    public static function conversational(): ChatTemplate
    {
        return ChatTemplate::create()
            ->system('You are a helpful, friendly assistant. Engage in natural conversation.')
            ->user('{user_message}');
    }

    /**
     * Expert advisor template.
     */
    public static function expertAdvisor(): ChatTemplate
    {
        return ChatTemplate::create()
            ->system('You are an expert {expertise}. Provide detailed, professional advice.')
            ->user('{question}');
    }

    /**
     * Socratic teaching template.
     */
    public static function socraticTeaching(): ChatTemplate
    {
        return ChatTemplate::create()
            ->system(
                'You are a Socratic teacher. Instead of giving direct answers, ' .
                'ask thought-provoking questions to guide the student to discover the answer.'
            )
            ->user('{question}');
    }

    /**
     * Debate opponent template.
     */
    public static function debateOpponent(): ChatTemplate
    {
        return ChatTemplate::create()
            ->system(
                'You are a skilled debater. Argue the position that: {position}. ' .
                'Use logic, evidence, and persuasive techniques.'
            )
            ->user('{argument}');
    }

    /**
     * Creative writing assistant.
     */
    public static function creativeWriting(): PromptTemplate
    {
        return PromptTemplate::create(
            "Write a creative {genre} story with the following elements:\n" .
            "- Setting: {setting}\n" .
            "- Characters: {characters}\n" .
            "- Conflict: {conflict}\n\n" .
            'Story:'
        );
    }

    /**
     * SQL query generator.
     */
    public static function sqlGenerator(): PromptTemplate
    {
        return PromptTemplate::create(
            "Generate a SQL query to {task}.\n\n" .
            "Database schema:\n{schema}\n\n" .
            "Requirements:\n{requirements}\n\n" .
            'SQL Query:'
        );
    }

    /**
     * API documentation generator.
     */
    public static function apiDocumentation(): PromptTemplate
    {
        return PromptTemplate::create(
            "Generate API documentation for the following {language} code:\n\n" .
            "```{language}\n{code}\n```\n\n" .
            "Include:\n" .
            "- Description\n" .
            "- Parameters\n" .
            "- Return value\n" .
            "- Example usage\n\n" .
            'Documentation:'
        );
    }

    /**
     * User story generator.
     */
    public static function userStory(): PromptTemplate
    {
        return PromptTemplate::create(
            "Generate a user story for: {feature}\n\n" .
            "Format:\n" .
            "As a [user type]\n" .
            "I want to [action]\n" .
            "So that [benefit]\n\n" .
            "Acceptance Criteria:\n" .
            "- [criterion 1]\n" .
            "- [criterion 2]\n\n" .
            'User Story:'
        );
    }
}
