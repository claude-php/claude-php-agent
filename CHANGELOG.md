# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2025-12-16

### Changed
- Updated `claude-php/claude-php-sdk` from ^0.1 to ^0.5 for latest features and fixes
- Updated `phpstan/phpstan` from ^1.10 to ^2.1 for improved static analysis
- Updated `friendsofphp/php-cs-fixer` from ^3.0 to ^3.92 for latest code style rules
- Updated GitHub Actions dependencies:
  - `actions/checkout` from v4 to v6
  - `actions/cache` from v4 to v5
  - `codecov/codecov-action` from v4 to v5

### Fixed
- Test failures due to changes in agent logging behavior
- Test failures related to ConfigurationException type changes
- Test assertions for cost tracking with proper budget values
- Code style issues (spacing in arrow functions and anonymous classes)

## [0.1.0] - 2025-12-16

### Added
- Initial release of Claude PHP Agent Framework
- Core Agent class with fluent API
- Loop strategies: ReactLoop, PlanExecuteLoop, ReflectionLoop
- Agent patterns:
  - ReactAgent - General-purpose autonomous tasks
  - ReflectionAgent - Quality-critical outputs with refinement
  - HierarchicalAgent - Master-worker pattern
  - CoordinatorAgent - Agent orchestration and load balancing
  - DialogAgent - Conversational AI with context tracking
  - IntentClassifierAgent - Intent recognition and routing
  - MonitoringAgent - System monitoring and anomaly detection
  - SchedulerAgent - Task scheduling and cron jobs
  - AlertAgent - Intelligent alerting and notifications
  - RefLexAgent - Rule-based responses
  - ModelBasedAgent - State-aware decision making
  - UtilityBasedAgent - Optimization and trade-offs
  - LearningAgent - Adaptive behavior with feedback loops
  - TaskPrioritizationAgent - BabyAGI-inspired task management
  - EnvironmentSimulatorAgent - What-if analysis and prediction
  - SolutionDiscriminatorAgent - Solution evaluation and voting
  - MemoryManagerAgent - Knowledge management and retrieval
  - MakerAgent - MDAP framework for million-step tasks
  - AdaptiveAgentService - Meta-agent selection and validation
- Tool system with fluent API for tool creation
- Memory management (Memory, FileMemory, VectorMemory, ConversationMemory)
- Chain composition (Sequential, Parallel, Router, Transform, LLM)
- Output parsers with auto-detection (JSON, XML, Markdown, CSV, List, Regex)
- Async/concurrent execution with AMPHP
  - BatchProcessor for concurrent agent tasks
  - ParallelToolExecutor for parallel tool calls
  - Promise-based workflows
- Multi-agent systems and collaboration
- Debate system for solution refinement
- RAG (Retrieval-Augmented Generation) support
  - Document chunking and embeddings
  - Vector stores (Memory, Redis, PostgreSQL with pgvector)
  - Hybrid search (semantic + keyword)
  - Query rewriting and result reranking
- Streaming support for real-time responses
- Production features:
  - Retry logic with exponential backoff
  - Error handling and recovery
  - PSR-3 logging integration
  - Event hooks and callbacks
  - Observability and monitoring
- Comprehensive documentation and tutorials
- 70+ working examples
- Extensive test suite (unit and integration tests)

### Dependencies
- PHP 8.1+
- claude-php/claude-php-sdk ^0.1
- PSR-3 logger interface
- AMPHP for async operations

[Unreleased]: https://github.com/claude-php/agent/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/claude-php/agent/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/claude-php/agent/releases/tag/v0.1.0

