<?php

declare(strict_types=1);

namespace ClaudeAgents\MultiAgent;

/**
 * Defines communication protocols for multi-agent systems.
 */
class Protocol
{
    public const PROTOCOL_REQUEST_RESPONSE = 'request_response';
    public const PROTOCOL_BROADCAST = 'broadcast';
    public const PROTOCOL_CONTRACT_NET = 'contract_net';
    public const PROTOCOL_AUCTION = 'auction';

    private string $name;
    private array $rules;

    /**
     * @param string $name Protocol name
     * @param array<string, mixed> $rules Protocol rules
     */
    public function __construct(string $name, array $rules = [])
    {
        $this->name = $name;
        $this->rules = $rules;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Validate a message against protocol rules.
     */
    public function validateMessage(Message $message): bool
    {
        // Basic validation based on protocol type
        switch ($this->name) {
            case self::PROTOCOL_REQUEST_RESPONSE:
                return in_array($message->getType(), ['request', 'response']);

            case self::PROTOCOL_BROADCAST:
                return $message->isBroadcast();

            case self::PROTOCOL_CONTRACT_NET:
                return in_array($message->getType(), ['cfp', 'proposal', 'award', 'reject']);

            case self::PROTOCOL_AUCTION:
                return in_array($message->getType(), ['bid', 'accept', 'reject']);

            default:
                return true; // Unknown protocols allow all messages
        }
    }

    /**
     * Create a request-response protocol.
     */
    public static function requestResponse(): self
    {
        return new self(self::PROTOCOL_REQUEST_RESPONSE);
    }

    /**
     * Create a broadcast protocol.
     */
    public static function broadcast(): self
    {
        return new self(self::PROTOCOL_BROADCAST);
    }

    /**
     * Create a contract-net protocol.
     */
    public static function contractNet(): self
    {
        return new self(self::PROTOCOL_CONTRACT_NET);
    }

    /**
     * Create an auction protocol.
     */
    public static function auction(): self
    {
        return new self(self::PROTOCOL_AUCTION);
    }
}
