<?php

/**
 * Code review checklist helper script.
 *
 * Provides structured checklist items for code reviews.
 */

return [
    'security' => [
        'sql-injection' => 'Check for SQL injection vulnerabilities',
        'xss' => 'Check for cross-site scripting vulnerabilities',
        'csrf' => 'Verify CSRF protection on state-changing operations',
        'input-validation' => 'Validate all user input at system boundaries',
        'file-operations' => 'Check for insecure file operations',
        'credentials' => 'Look for hardcoded credentials or secrets',
        'auth' => 'Verify authentication and authorization checks',
    ],
    'quality' => [
        'types' => 'Verify proper type declarations',
        'error-handling' => 'Check error handling and exceptions',
        'srp' => 'Ensure single responsibility principle',
        'duplication' => 'Check for code duplication',
        'naming' => 'Verify PSR-12 naming conventions',
        'complexity' => 'Check cyclomatic complexity',
        'dead-code' => 'Look for dead code',
    ],
    'performance' => [
        'n-plus-1' => 'Check for N+1 query problems',
        'caching' => 'Verify proper use of caching',
        'memory' => 'Check for memory leaks in loops',
        'instantiation' => 'Look for unnecessary object instantiation',
        'strings' => 'Verify efficient string operations',
        'indexing' => 'Check database indexing usage',
    ],
    'testing' => [
        'coverage' => 'Verify test coverage for critical paths',
        'mocking' => 'Check proper mocking and isolation',
        'edge-cases' => 'Ensure edge cases are tested',
        'integration' => 'Verify integration test coverage',
    ],
];
