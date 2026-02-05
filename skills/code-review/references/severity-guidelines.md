# Severity Guidelines

## Critical
Issues that could lead to security breaches, data loss, or system compromise.
Must be fixed before any deployment.

Examples:
- SQL injection
- Authentication bypass
- Sensitive data exposure
- Remote code execution

## Major
Issues that could cause bugs, significant performance degradation, or poor user experience.
Should be fixed in the current development cycle.

Examples:
- Logic errors
- N+1 query problems
- Missing error handling for external services
- Race conditions

## Minor
Issues related to code style, minor improvements, or non-critical optimizations.
Can be addressed during regular maintenance.

Examples:
- Naming convention violations
- Minor code duplication
- Missing type hints on internal methods
- Suboptimal but functional algorithms

## Info
Suggestions for improvement that are not bugs or violations.
Consider during future refactoring.

Examples:
- Architecture suggestions
- Design pattern recommendations
- Documentation improvements
- Test coverage suggestions
