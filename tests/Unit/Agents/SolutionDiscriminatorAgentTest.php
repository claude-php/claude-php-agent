<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SolutionDiscriminatorAgentTest extends TestCase
{
    private ClaudePhp $client;
    private SolutionDiscriminatorAgent $agent;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->agent = new SolutionDiscriminatorAgent($this->client, ['name' => 'test_discriminator']);
    }

    public function test_creates_agent_with_default_options(): void
    {
        $agent = new SolutionDiscriminatorAgent($this->client);

        $this->assertSame('solution_discriminator', $agent->getName());
    }

    public function test_creates_agent_with_custom_options(): void
    {
        $logger = new NullLogger();
        $agent = new SolutionDiscriminatorAgent($this->client, [
            'name' => 'custom_discriminator',
            'criteria' => ['accuracy', 'efficiency', 'clarity'],
            'logger' => $logger,
        ]);

        $this->assertSame('custom_discriminator', $agent->getName());
    }

    public function test_get_name(): void
    {
        $this->assertSame('test_discriminator', $this->agent->getName());
    }

    public function test_evaluates_single_solution(): void
    {
        $this->mockLlmResponses(['0.85', '0.90', '0.88']);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Solution A'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        $this->assertCount(1, $evaluations);
        $this->assertArrayHasKey('solution_id', $evaluations[0]);
        $this->assertArrayHasKey('scores', $evaluations[0]);
        $this->assertArrayHasKey('total_score', $evaluations[0]);
        $this->assertSame('sol_1', $evaluations[0]['solution_id']);
    }

    public function test_evaluates_multiple_solutions(): void
    {
        // Mock responses for multiple solutions * criteria (3 solutions × 3 criteria = 9 calls)
        $this->mockLlmResponses([
            '0.85', '0.90', '0.88',  // Solution 1
            '0.75', '0.80', '0.78',  // Solution 2
            '0.95', '0.92', '0.94',  // Solution 3
        ]);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Solution A'],
            ['id' => 'sol_2', 'content' => 'Solution B'],
            ['id' => 'sol_3', 'content' => 'Solution C'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        $this->assertCount(3, $evaluations);

        // Best solution should have highest score
        $this->assertGreaterThan($evaluations[1]['total_score'], $evaluations[2]['total_score']);
        $this->assertGreaterThan($evaluations[0]['total_score'], $evaluations[2]['total_score']);
    }

    public function test_evaluates_solutions_with_context(): void
    {
        $this->mockLlmResponses(['0.85', '0.90', '0.88']);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Solution A'],
        ];

        $context = 'Optimize for speed and accuracy';
        $evaluations = $this->agent->evaluateSolutions($solutions, $context);

        $this->assertCount(1, $evaluations);
        $this->assertIsArray($evaluations[0]['scores']);
    }

    public function test_run_method_with_json_solutions(): void
    {
        $this->mockLlmResponses([
            '0.85', '0.90', '0.88',  // Solution 1
            '0.95', '0.92', '0.94',  // Solution 2
        ]);

        $task = json_encode([
            ['id' => 'sol_1', 'content' => 'Solution A'],
            ['id' => 'sol_2', 'content' => 'Solution B'],
        ]);

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Best solution:', $result->getAnswer());
        $this->assertSame(2, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('evaluations', $metadata);
        $this->assertArrayHasKey('best_solution', $metadata);
        $this->assertCount(2, $metadata['evaluations']);
    }

    public function test_run_method_with_single_text_solution(): void
    {
        $this->mockLlmResponses(['0.85', '0.90', '0.88']);

        $task = 'This is a simple text solution';

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Best solution:', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());
    }

    public function test_run_handles_evaluation_errors_gracefully(): void
    {
        // When evaluation fails, the agent should still succeed with fallback scores (0.5)
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('API Error'));
        $this->client->method('messages')->willReturn($messages);

        $task = json_encode([['id' => 'sol_1', 'content' => 'Test']]);

        $result = $this->agent->run($task);

        // Should still succeed with fallback scores
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Best solution:', $result->getAnswer());

        $metadata = $result->getMetadata();
        $evaluations = $metadata['evaluations'];

        // All scores should be 0.5 (fallback value)
        $this->assertSame(0.5, $evaluations[0]['total_score']);
    }

    public function test_scores_are_normalized(): void
    {
        $this->mockLlmResponses(['0.5', '1.0', '0.75']);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Test solution'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        $totalScore = $evaluations[0]['total_score'];
        $this->assertGreaterThanOrEqual(0.0, $totalScore);
        $this->assertLessThanOrEqual(1.0, $totalScore);
    }

    public function test_handles_evaluation_failure_gracefully(): void
    {
        // Mock some failures - will return 0.5 as fallback
        $messages = $this->createMock(Messages::class);
        $messages->method('create')->willThrowException(new \RuntimeException('Evaluation failed'));
        $this->client->method('messages')->willReturn($messages);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Test solution'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        $this->assertCount(1, $evaluations);
        // Should use default 0.5 score when evaluation fails
        $this->assertSame(0.5, $evaluations[0]['total_score']);
    }

    public function test_evaluates_with_custom_criteria(): void
    {
        $agent = new SolutionDiscriminatorAgent($this->client, [
            'criteria' => ['speed', 'accuracy'],
        ]);

        $this->mockLlmResponses(['0.9', '0.85']);

        $solutions = [
            ['id' => 'sol_1', 'content' => 'Fast solution'],
        ];

        $evaluations = $agent->evaluateSolutions($solutions);

        $this->assertCount(1, $evaluations);
        $this->assertArrayHasKey('speed', $evaluations[0]['scores']);
        $this->assertArrayHasKey('accuracy', $evaluations[0]['scores']);
    }

    public function test_selects_best_solution_correctly(): void
    {
        $this->mockLlmResponses([
            '0.5', '0.6', '0.55',  // Solution 1 - avg 0.55
            '0.9', '0.85', '0.95', // Solution 2 - avg 0.9 (best)
            '0.7', '0.65', '0.75', // Solution 3 - avg 0.7
        ]);

        $solutions = [
            ['id' => 'low_score', 'content' => 'Poor solution'],
            ['id' => 'high_score', 'content' => 'Best solution'],
            ['id' => 'mid_score', 'content' => 'Average solution'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        // Find the best one
        $best = array_reduce($evaluations, function ($carry, $item) {
            return (! $carry || $item['total_score'] > $carry['total_score']) ? $item : $carry;
        });

        $this->assertSame('high_score', $best['solution_id']);
    }

    public function test_handles_empty_solutions(): void
    {
        $task = json_encode([]);

        $result = $this->agent->run($task);

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('best_solution', $metadata);
        $this->assertSame('none', $metadata['best_solution']['solution_id']);
    }

    public function test_solution_id_generation_for_missing_ids(): void
    {
        $this->mockLlmResponses(['0.8', '0.85', '0.9']);

        $solutions = [
            ['content' => 'Solution without ID'],
        ];

        $evaluations = $this->agent->evaluateSolutions($solutions);

        $this->assertCount(1, $evaluations);
        $this->assertStringStartsWith('sol_', $evaluations[0]['solution_id']);
    }

    public function test_metadata_includes_all_evaluations(): void
    {
        $this->mockLlmResponses([
            '0.8', '0.85', '0.9',
            '0.7', '0.75', '0.8',
        ]);

        $task = json_encode([
            ['id' => 'sol_1', 'content' => 'Solution A'],
            ['id' => 'sol_2', 'content' => 'Solution B'],
        ]);

        $result = $this->agent->run($task);
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('evaluations', $metadata);
        $this->assertCount(2, $metadata['evaluations']);

        foreach ($metadata['evaluations'] as $evaluation) {
            $this->assertArrayHasKey('solution_id', $evaluation);
            $this->assertArrayHasKey('scores', $evaluation);
            $this->assertArrayHasKey('total_score', $evaluation);
        }
    }

    public function test_answer_includes_best_solution_details(): void
    {
        $this->mockLlmResponses([
            '0.9', '0.85', '0.95',
        ]);

        $task = json_encode([
            ['id' => 'winner', 'content' => 'Best solution'],
        ]);

        $result = $this->agent->run($task);

        $this->assertStringContainsString('winner', $result->getAnswer());
        $this->assertMatchesRegularExpression('/score:\s*[\d.]+/', $result->getAnswer());
    }

    public function test_criteria_are_used_in_evaluation(): void
    {
        $agent = new SolutionDiscriminatorAgent($this->client, [
            'criteria' => ['correctness', 'efficiency', 'maintainability'],
        ]);

        $this->mockLlmResponses(['0.8', '0.9', '0.85']);

        $solutions = [
            ['id' => 'test', 'content' => 'Test solution'],
        ];

        $evaluations = $agent->evaluateSolutions($solutions);

        $scores = $evaluations[0]['scores'];
        $this->assertArrayHasKey('correctness', $scores);
        $this->assertArrayHasKey('efficiency', $scores);
        $this->assertArrayHasKey('maintainability', $scores);
    }

    private function mockLlmResponses(array $ratings): void
    {
        $usage = new Usage(
            input_tokens: 100,
            output_tokens: 10
        );

        $messages = $this->createMock(Messages::class);
        $callCount = 0;

        $messages->method('create')->willReturnCallback(
            function () use ($ratings, &$callCount, $usage) {
                $rating = $ratings[$callCount % count($ratings)] ?? '0.5';
                $callCount++;

                return new Message(
                    id: 'msg_test_' . $callCount,
                    type: 'message',
                    role: 'assistant',
                    content: [
                        ['type' => 'text', 'text' => $rating],
                    ],
                    model: 'claude-sonnet-4-5',
                    stop_reason: 'end_turn',
                    stop_sequence: null,
                    usage: $usage
                );
            }
        );

        $this->client->method('messages')->willReturn($messages);
    }
}
