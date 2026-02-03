<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\Variable;

/**
 * Variable type enumeration.
 */
enum VariableType: string
{
    /**
     * Credential type - stored encrypted, hidden from logs.
     */
    case CREDENTIAL = 'credential';

    /**
     * Generic type - stored as-is, visible.
     */
    case GENERIC = 'generic';
}
