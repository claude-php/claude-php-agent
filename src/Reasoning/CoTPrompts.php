<?php

declare(strict_types=1);

namespace ClaudeAgents\Reasoning;

/**
 * Predefined prompts for Chain-of-Thought reasoning.
 */
class CoTPrompts
{
    /**
     * Get zero-shot CoT trigger phrase.
     */
    public static function zeroShotTrigger(): string
    {
        return "Let's think step by step.";
    }

    /**
     * Get common zero-shot trigger phrases.
     *
     * @return array<string>
     */
    public static function zeroShotTriggers(): array
    {
        return [
            "Let's think step by step.",
            "Let's work this out systematically.",
            "Let's break this down.",
            "Let's approach this logically.",
            "Let's reason through this.",
        ];
    }

    /**
     * Get few-shot examples for math problems.
     *
     * @return array<array{question: string, answer: string}>
     */
    public static function mathExamples(): array
    {
        return [
            [
                'question' => 'If a book costs $15 and is on 20% discount, what\'s the sale price?',
                'answer' => "Let me work through this step by step:\n" .
                           "Step 1: Calculate the discount amount: 20% of $15 = $15 × 0.20 = $3\n" .
                           "Step 2: Subtract discount from original price: $15 - $3 = $12\n" .
                           'Final Answer: The sale price is $12',
            ],
            [
                'question' => 'A car travels at 60 mph for 2.5 hours. How far does it go?',
                'answer' => "Let me solve this step by step:\n" .
                           "Step 1: Use the formula Distance = Speed × Time\n" .
                           "Step 2: Plug in the values: Distance = 60 mph × 2.5 hours\n" .
                           "Step 3: Calculate: Distance = 150 miles\n" .
                           'Final Answer: The car travels 150 miles',
            ],
        ];
    }

    /**
     * Get few-shot examples for logic problems.
     *
     * @return array<array{question: string, answer: string}>
     */
    public static function logicExamples(): array
    {
        return [
            [
                'question' => 'Alice, Bob, and Carol are sitting in a row. Alice is not sitting next to Carol. Bob is sitting to the right of Alice. Who is sitting in the middle?',
                'answer' => "Let me work through this systematically:\n" .
                           "Constraints:\n" .
                           "1. Alice is not next to Carol\n" .
                           "2. Bob is to the right of Alice\n\n" .
                           "Possibilities:\n" .
                           "- If Alice is leftmost: Alice, Bob, Carol - But this violates constraint 1 (Alice next to Carol)\n" .
                           "- If Alice is leftmost: Alice, Carol, Bob - Violates constraint 2 (Bob must be RIGHT of Alice, not left)\n" .
                           "- If Alice is middle: Carol, Alice, Bob - Satisfies both constraints!\n" .
                           "- If Alice is rightmost: Violates constraint 2 (Bob can't be to her right)\n\n" .
                           'Final Answer: Carol is in the middle',
            ],
        ];
    }

    /**
     * Get few-shot examples for decision-making.
     *
     * @return array<array{question: string, answer: string}>
     */
    public static function decisionExamples(): array
    {
        return [
            [
                'question' => 'Should a startup use AWS or a VPS provider for hosting?',
                'answer' => "Let me analyze this decision:\n\n" .
                           "Key factors:\n" .
                           "1. Scalability - AWS wins (auto-scaling)\n" .
                           "2. Cost - VPS wins (cheaper for stable load)\n" .
                           "3. Ease of setup - AWS wins (managed services)\n" .
                           "4. Learning curve - VPS wins (simpler)\n" .
                           "5. Flexibility - AWS wins (more options)\n\n" .
                           "Analysis:\n" .
                           "- For early startups with variable traffic: AWS\n" .
                           "- For stable, predictable loads: VPS\n" .
                           "- For rapid scaling needs: AWS\n\n" .
                           'Recommendation: AWS for most startups due to flexibility and growth accommodation',
            ],
        ];
    }

    /**
     * Create a few-shot system prompt for CoT.
     *
     * @param array<array{question: string, answer: string}> $examples
     */
    public static function fewShotSystem(array $examples): string
    {
        $exampleText = '';

        foreach ($examples as $i => $example) {
            $exampleText .= "\nExample " . ($i + 1) . ":\n";
            $exampleText .= "Q: {$example['question']}\n";
            $exampleText .= "A: {$example['answer']}\n";
        }

        return 'You are an expert problem solver. ' .
               'Solve problems by thinking through them step by step. ' .
               "Here are examples of how to approach problems:\n" .
               $exampleText . "\n\n" .
               'Now solve new problems using this same step-by-step format. ' .
               'Always show your reasoning before the final answer.';
    }
}
