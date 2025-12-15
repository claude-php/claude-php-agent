<?php

declare(strict_types=1);

namespace Tests\Unit\MultiAgent;

use ClaudeAgents\MultiAgent\Message;
use ClaudeAgents\MultiAgent\Protocol;
use PHPUnit\Framework\TestCase;

class ProtocolTest extends TestCase
{
    public function test_creates_request_response_protocol(): void
    {
        $protocol = Protocol::requestResponse();

        $this->assertEquals(Protocol::PROTOCOL_REQUEST_RESPONSE, $protocol->getName());
    }

    public function test_creates_broadcast_protocol(): void
    {
        $protocol = Protocol::broadcast();

        $this->assertEquals(Protocol::PROTOCOL_BROADCAST, $protocol->getName());
    }

    public function test_creates_contract_net_protocol(): void
    {
        $protocol = Protocol::contractNet();

        $this->assertEquals(Protocol::PROTOCOL_CONTRACT_NET, $protocol->getName());
    }

    public function test_creates_auction_protocol(): void
    {
        $protocol = Protocol::auction();

        $this->assertEquals(Protocol::PROTOCOL_AUCTION, $protocol->getName());
    }

    public function test_custom_protocol_with_rules(): void
    {
        $rules = ['max_participants' => 5, 'timeout' => 30];
        $protocol = new Protocol('custom', $rules);

        $this->assertEquals('custom', $protocol->getName());
        $this->assertEquals($rules, $protocol->getRules());
    }

    public function test_request_response_validates_request_message(): void
    {
        $protocol = Protocol::requestResponse();
        $message = new Message('agent1', 'agent2', 'Question?', 'request');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_request_response_validates_response_message(): void
    {
        $protocol = Protocol::requestResponse();
        $message = new Message('agent2', 'agent1', 'Answer!', 'response');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_request_response_rejects_invalid_type(): void
    {
        $protocol = Protocol::requestResponse();
        $message = new Message('agent1', 'agent2', 'Invalid', 'notification');

        $this->assertFalse($protocol->validateMessage($message));
    }

    public function test_broadcast_validates_broadcast_message(): void
    {
        $protocol = Protocol::broadcast();
        $message = new Message('agent1', 'broadcast', 'Announcement');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_broadcast_rejects_non_broadcast_message(): void
    {
        $protocol = Protocol::broadcast();
        $message = new Message('agent1', 'agent2', 'Direct message');

        $this->assertFalse($protocol->validateMessage($message));
    }

    public function test_contract_net_validates_cfp(): void
    {
        $protocol = Protocol::contractNet();
        $message = new Message('manager', 'broadcast', 'Call for proposals', 'cfp');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_contract_net_validates_proposal(): void
    {
        $protocol = Protocol::contractNet();
        $message = new Message('agent1', 'manager', 'My proposal', 'proposal');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_contract_net_validates_award(): void
    {
        $protocol = Protocol::contractNet();
        $message = new Message('manager', 'agent1', 'You won', 'award');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_contract_net_validates_reject(): void
    {
        $protocol = Protocol::contractNet();
        $message = new Message('manager', 'agent2', 'Sorry', 'reject');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_contract_net_rejects_invalid_type(): void
    {
        $protocol = Protocol::contractNet();
        $message = new Message('agent1', 'agent2', 'Invalid', 'chat');

        $this->assertFalse($protocol->validateMessage($message));
    }

    public function test_auction_validates_bid(): void
    {
        $protocol = Protocol::auction();
        $message = new Message('agent1', 'auctioneer', 'Bid: $100', 'bid');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_auction_validates_accept(): void
    {
        $protocol = Protocol::auction();
        $message = new Message('auctioneer', 'agent1', 'Accepted', 'accept');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_auction_validates_reject(): void
    {
        $protocol = Protocol::auction();
        $message = new Message('auctioneer', 'agent2', 'Too low', 'reject');

        $this->assertTrue($protocol->validateMessage($message));
    }

    public function test_unknown_protocol_allows_all_messages(): void
    {
        $protocol = new Protocol('unknown');
        $message = new Message('agent1', 'agent2', 'Anything', 'any_type');

        $this->assertTrue($protocol->validateMessage($message));
    }
}
