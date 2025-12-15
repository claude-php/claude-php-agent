<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate;

use ClaudeAgents\Debate\DebateRound;
use PHPUnit\Framework\TestCase;

class DebateRoundTest extends TestCase
{
    public function testConstructorWithBasicArguments(): void
    {
        $statements = [
            'Agent A' => 'Statement from Agent A',
            'Agent B' => 'Statement from Agent B',
        ];

        $round = new DebateRound(1, $statements);

        $this->assertSame(1, $round->getRoundNumber());
        $this->assertSame($statements, $round->getStatements());
        $this->assertSame(0, $round->getTimestamp());
    }

    public function testConstructorWithTimestamp(): void
    {
        $statements = ['Agent A' => 'Statement A'];
        $timestamp = time();

        $round = new DebateRound(2, $statements, $timestamp);

        $this->assertSame(2, $round->getRoundNumber());
        $this->assertSame($timestamp, $round->getTimestamp());
    }

    public function testGetRoundNumber(): void
    {
        $round = new DebateRound(5, ['Agent' => 'Statement']);

        $this->assertSame(5, $round->getRoundNumber());
    }

    public function testGetStatements(): void
    {
        $statements = [
            'Pro' => 'I support this',
            'Con' => 'I oppose this',
            'Neutral' => 'Both sides have merit',
        ];

        $round = new DebateRound(1, $statements);

        $this->assertSame($statements, $round->getStatements());
    }

    public function testGetStatementExisting(): void
    {
        $statements = [
            'Alice' => 'My opinion is X',
            'Bob' => 'My opinion is Y',
        ];

        $round = new DebateRound(1, $statements);

        $this->assertSame('My opinion is X', $round->getStatement('Alice'));
        $this->assertSame('My opinion is Y', $round->getStatement('Bob'));
    }

    public function testGetStatementNonExisting(): void
    {
        $round = new DebateRound(1, ['Alice' => 'Statement']);

        $this->assertNull($round->getStatement('Bob'));
        $this->assertNull($round->getStatement('Charlie'));
    }

    public function testGetParticipants(): void
    {
        $statements = [
            'Engineer' => 'Technical perspective',
            'Designer' => 'UX perspective',
            'Manager' => 'Business perspective',
        ];

        $round = new DebateRound(1, $statements);

        $participants = $round->getParticipants();

        $this->assertCount(3, $participants);
        $this->assertContains('Engineer', $participants);
        $this->assertContains('Designer', $participants);
        $this->assertContains('Manager', $participants);
    }

    public function testGetParticipantsEmpty(): void
    {
        $round = new DebateRound(1, []);

        $this->assertEmpty($round->getParticipants());
    }

    public function testToArray(): void
    {
        $statements = [
            'Agent A' => 'Statement A',
            'Agent B' => 'Statement B',
        ];
        $timestamp = 1234567890;

        $round = new DebateRound(3, $statements, $timestamp);

        $array = $round->toArray();

        $this->assertIsArray($array);
        $this->assertSame(3, $array['round_number']);
        $this->assertSame($statements, $array['statements']);
        $this->assertSame(['Agent A', 'Agent B'], $array['participants']);
        $this->assertSame($timestamp, $array['timestamp']);
    }

    public function testToArrayStructure(): void
    {
        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $array = $round->toArray();

        $this->assertArrayHasKey('round_number', $array);
        $this->assertArrayHasKey('statements', $array);
        $this->assertArrayHasKey('participants', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function testMultipleRounds(): void
    {
        $round1 = new DebateRound(1, ['A' => 'First round A', 'B' => 'First round B']);
        $round2 = new DebateRound(2, ['A' => 'Second round A', 'B' => 'Second round B']);
        $round3 = new DebateRound(3, ['A' => 'Third round A', 'B' => 'Third round B']);

        $this->assertSame(1, $round1->getRoundNumber());
        $this->assertSame(2, $round2->getRoundNumber());
        $this->assertSame(3, $round3->getRoundNumber());

        $this->assertSame('First round A', $round1->getStatement('A'));
        $this->assertSame('Second round A', $round2->getStatement('A'));
        $this->assertSame('Third round A', $round3->getStatement('A'));
    }

    public function testEmptyStatements(): void
    {
        $round = new DebateRound(1, []);

        $this->assertEmpty($round->getStatements());
        $this->assertEmpty($round->getParticipants());
        $this->assertNull($round->getStatement('Anyone'));
    }

    public function testLongStatements(): void
    {
        $longStatement = str_repeat('This is a very long statement. ', 100);
        $statements = ['Verbose Agent' => $longStatement];

        $round = new DebateRound(1, $statements);

        $this->assertSame($longStatement, $round->getStatement('Verbose Agent'));
        $this->assertGreaterThan(1000, strlen($round->getStatement('Verbose Agent')));
    }
}
