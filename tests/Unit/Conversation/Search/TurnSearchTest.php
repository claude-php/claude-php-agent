<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation\Search;

use ClaudeAgents\Conversation\Search\TurnSearch;
use ClaudeAgents\Conversation\Session;
use ClaudeAgents\Conversation\Turn;
use PHPUnit\Framework\TestCase;

class TurnSearchTest extends TestCase
{
    private TurnSearch $search;
    private Session $session;

    protected function setUp(): void
    {
        $this->search = new TurnSearch();
        $this->session = new Session('test_session');

        // Add test turns
        $this->session->addTurn(new Turn('Hello world', 'Hi there'));
        $this->session->addTurn(new Turn('What is PHP?', 'PHP is a programming language'));
        $this->session->addTurn(new Turn('Tell me about Python', 'Python is also a programming language'));
        $this->session->addTurn(new Turn('Goodbye', 'See you later', ['important' => true]));
    }

    public function test_searches_by_content_in_user_input(): void
    {
        $results = $this->search->searchByContent($this->session, 'PHP');

        $this->assertCount(1, $results);
        $this->assertSame('What is PHP?', $results[0]->getUserInput());
    }

    public function test_searches_by_content_in_agent_response(): void
    {
        $results = $this->search->searchByContent($this->session, 'programming language');

        $this->assertCount(2, $results);
    }

    public function test_search_is_case_insensitive_by_default(): void
    {
        $results = $this->search->searchByContent($this->session, 'hello');

        $this->assertCount(1, $results);
    }

    public function test_search_can_be_case_sensitive(): void
    {
        $results = $this->search->searchByContent(
            $this->session,
            'hello',
            ['case_sensitive' => true]
        );

        $this->assertEmpty($results);

        $results = $this->search->searchByContent(
            $this->session,
            'Hello',
            ['case_sensitive' => true]
        );

        $this->assertCount(1, $results);
    }

    public function test_search_with_whole_word_option(): void
    {
        $results = $this->search->searchByContent(
            $this->session,
            'Hi',
            ['whole_word' => true]
        );

        $this->assertCount(1, $results);
        $this->assertSame('Hi there', $results[0]->getAgentResponse());
    }

    public function test_search_only_in_user_input(): void
    {
        $results = $this->search->searchByContent(
            $this->session,
            'programming',
            ['search_in' => 'user']
        );

        $this->assertEmpty($results);
    }

    public function test_search_only_in_agent_response(): void
    {
        $results = $this->search->searchByContent(
            $this->session,
            'programming',
            ['search_in' => 'agent']
        );

        $this->assertCount(2, $results);
    }

    public function test_searches_by_metadata(): void
    {
        $results = $this->search->searchByMetadata(
            $this->session,
            ['important' => true]
        );

        $this->assertCount(1, $results);
        $this->assertSame('Goodbye', $results[0]->getUserInput());
    }

    public function test_searches_by_time_range(): void
    {
        $now = microtime(true);
        $results = $this->search->searchByTimeRange(
            $this->session,
            $now - 60,
            $now + 60
        );

        $this->assertCount(4, $results);
    }

    public function test_searches_by_time_range_with_narrow_window(): void
    {
        // Create a new session with controlled timing
        $testSession = new Session('time_test');
        $testSession->addTurn(new Turn('First', 'Response 1'));

        $turns = $testSession->getTurns();
        $firstTime = $turns[0]->getTimestamp();

        $results = $this->search->searchByTimeRange(
            $testSession,
            $firstTime - 0.1,
            $firstTime + 0.1
        );

        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_searches_by_pattern(): void
    {
        $results = $this->search->searchByPattern(
            $this->session,
            '/\b[A-Z]{3}\b/' // Three uppercase letters
        );

        $this->assertCount(1, $results);
        $this->assertSame('What is PHP?', $results[0]->getUserInput());
    }

    public function test_searches_by_pattern_in_specific_field(): void
    {
        $results = $this->search->searchByPattern(
            $this->session,
            '/programming/',
            'agent_response'
        );

        $this->assertCount(2, $results);
    }

    public function test_handles_invalid_regex_pattern(): void
    {
        $results = $this->search->searchByPattern(
            $this->session,
            '/invalid[/'
        );

        $this->assertEmpty($results);
    }

    public function test_returns_empty_array_when_no_matches(): void
    {
        $results = $this->search->searchByContent($this->session, 'nonexistent');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
