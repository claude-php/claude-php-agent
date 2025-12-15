# Documentation

Welcome to the Claude PHP Agent Framework documentation!

## 📚 Table of Contents

### Getting Started
- [Installation & Quick Start](../README.md#installation)
- [Core Concepts](../README.md#core-concepts)
- [Agent Selection Guide](agent-selection-guide.md)

### Loop Strategies
- [Loop Strategies Overview](loop-strategies.md)
- [ReAct Loop](ReactAgent.md)
- [Plan-Execute Loop](PlanExecuteAgent.md)
- [Reflection Loop](ReflectionAgent.md)

### Agent Patterns

#### Basic Agents
- [ReAct Agent](ReactAgent.md) - General-purpose autonomous tasks
- [Reflex Agent](ReflexAgent.md) - Rule-based responses
- [Dialog Agent](DialogAgent.md) - Conversational AI

#### Advanced Reasoning
- [Chain of Thought Agent](ChainOfThoughtAgent.md) - Step-by-step reasoning
- [Tree of Thoughts Agent](TreeOfThoughtsAgent.md) - Exploration-based reasoning
- [Reflection Agent](ReflectionAgent.md) - Self-improving with refinement
- [Plan-Execute Agent](PlanExecuteAgent.md) - Multi-step planning

#### Specialized Agents
- [RAG Agent](RAGAgent.md) - Retrieval-Augmented Generation
- [Learning Agent](LearningAgent.md) - Adaptive behavior with feedback
- [Model-Based Agent](ModelBasedAgent.md) - State-aware decision making
- [Utility-Based Agent](UtilityBasedAgent.md) - Optimization and trade-offs
- [Intent Classifier Agent](IntentClassifierAgent.md) - Intent recognition
- [Environment Simulator Agent](EnvironmentSimulatorAgent.md) - What-if analysis

#### Multi-Agent Systems
- [Hierarchical Agent](HierarchicalAgent.md) - Master-worker pattern
- [Multi-Agent Collaboration](MultiAgent.md) - Team coordination
- [Coordinator Agent](CoordinatorAgent.md) - Agent orchestration
- [Worker Agent](WorkerAgent.md) - Specialized task execution
- [Debate System](DebateSystem.md) - Multi-perspective reasoning

#### Production Agents
- [Monitoring Agent](MonitoringAgent.md) - System monitoring
- [Scheduler Agent](SchedulerAgent.md) - Task scheduling
- [Alert Agent](AlertAgent.md) - Intelligent alerting
- [Autonomous Agent](AutonomousAgent.md) - Long-running autonomy

#### Meta-Agents
- [MAKER Agent](MakerAgent.md) - Million-step reliable tasks (MDAP)
- [Adaptive Agent Service](adaptive-agent-service.md) - Intelligent agent selection
- [Task Prioritization Agent](TaskPrioritizationAgent.md) - BabyAGI-inspired
- [Solution Discriminator Agent](SolutionDiscriminatorAgent.md) - Solution evaluation
- [Memory Manager Agent](MemoryManagerAgent.md) - Knowledge management
- [Micro Agent](MicroAgent.md) - Lightweight task-specific agents

### Core Systems

#### Design Patterns
- [Design Patterns Overview](DesignPatterns.md) - Production-ready patterns
- [Factory Pattern](Factory.md) - Consistent agent creation
- [Builder Pattern](Builder.md) - Fluent configuration API
- [Observer Pattern (Events)](Events.md) - Lifecycle monitoring
- [Best Practices](BestPractices.md) - Production patterns and guidelines

#### Tools & Execution
- [Tools](Tools.md) - Creating and using tools
- [Built-in Tools](BuiltInTools.md) - Pre-built tool library

#### Memory & Context
- [Memory Systems](Memory/README.md) - Persistent state management
- [Advanced Memory Types](AdvancedMemoryTypes.md) - Vector, conversation, semantic
- [Context Management](ContextManagement.md) - Managing context windows

#### Chain Composition
- [Chains](Chains.md) - Sequential, parallel, and router chains

#### Output Processing
- [Parsers](Parsers.md) - Structured output extraction (includes Strategy pattern)
- [Prompts](Prompts.md) - Prompt engineering and templates (includes Template pattern)

#### Observability
- [Observability](Observability.md) - Monitoring, logging, and metrics
- [Streaming](Streaming.md) - Real-time response streaming

#### Advanced Features
- [Conversation Enhancements](ConversationEnhancements.md) - Context tracking and history
- [Conversation Quick Reference](ConversationQuickReference.md) - API reference
- [RAG Advanced Features](RAG_Advanced_Features.md) - Vector stores, embeddings, reranking
- [Contracts](contracts.md) - Interfaces and abstractions
- [ENHANCEMENTS](ENHANCEMENTS.md) - Future enhancements

## 🎓 Tutorials

Step-by-step tutorials for learning the framework:

1. [Adaptive Agent Service](tutorials/AdaptiveAgentService_Tutorial.md)
2. [Alert Agent](tutorials/AlertAgent_Tutorial.md)
3. [Autonomous Agent](tutorials/AutonomousAgent_Tutorial.md)
4. [Chain of Thought](tutorials/ChainOfThoughtAgent_Tutorial.md)
5. [Chains](tutorials/Chains_Tutorial.md)
6. [Coordinator Agent](tutorials/CoordinatorAgent_Tutorial.md)
7. [Debate System](tutorials/DebateSystem_Tutorial.md)
8. [Dialog Agent](tutorials/DialogAgent_Tutorial.md)
9. [Environment Simulator](tutorials/EnvironmentSimulatorAgent_Tutorial.md)
10. [Hierarchical Agent](tutorials/HierarchicalAgent_Tutorial.md)
11. [Intent Classifier](tutorials/IntentClassifierAgent_Tutorial.md)
12. [Learning Agent](tutorials/LearningAgent_Tutorial.md)
13. [MAKER Agent](tutorials/MakerAgent_Tutorial.md)
14. [Memory Manager](tutorials/MemoryManagerAgent_Tutorial.md)
15. [Micro Agent](tutorials/MicroAgent_Tutorial.md)
16. [Model-Based Agent](tutorials/ModelBasedAgent_Tutorial.md)
17. [Monitoring Agent](tutorials/MonitoringAgent_Tutorial.md)
18. [Multi-Agent Systems](tutorials/MultiAgent_Tutorial.md)
19. [Plan-Execute Agent](tutorials/PlanExecuteAgent_Tutorial.md)
20. [Prompts](tutorials/Prompts_Tutorial.md)
21. [RAG Agent](tutorials/RAGAgent_Tutorial.md)
22. [ReAct Agent](tutorials/ReactAgent_Tutorial.md)
23. [Reflection Agent](tutorials/ReflectionAgent_Tutorial.md)
24. [Reflex Agent](tutorials/ReflexAgent_Tutorial.md)
25. [Scheduler Agent](tutorials/SchedulerAgent_Tutorial.md)
26. [Solution Discriminator](tutorials/SolutionDiscriminatorAgent_Tutorial.md)
27. [Task Prioritization](tutorials/TaskPrioritizationAgent_Tutorial.md)
28. [Tree of Thoughts](tutorials/TreeOfThoughtsAgent_Tutorial.md)
29. [Utility-Based Agent](tutorials/UtilityBasedAgent_Tutorial.md)
30. [Worker Agent](tutorials/WorkerAgent_Tutorial.md)

## 📖 Examples

Check the [examples](../examples/) directory for complete working examples:

- Basic agent usage
- Multi-tool agents
- Hierarchical systems
- Production setups
- Async/parallel execution
- Output parsing
- Chain composition
- And 60+ more!

## 🔍 Quick Navigation

### By Use Case

**Building a Chatbot?**
→ Start with [Dialog Agent](DialogAgent.md)

**Need to Search & Answer?**
→ Check out [RAG Agent](RAGAgent.md)

**Complex Multi-Step Tasks?**
→ Use [Plan-Execute Agent](PlanExecuteAgent.md) or [Hierarchical Agent](HierarchicalAgent.md)

**High-Quality Output?**
→ Try [Reflection Agent](ReflectionAgent.md)

**System Monitoring?**
→ Use [Monitoring Agent](MonitoringAgent.md) or [Alert Agent](AlertAgent.md)

**Million-Step Tasks?**
→ Use [MAKER Agent](MakerAgent.md)

**Don't Know Which Agent?**
→ Use [Adaptive Agent Service](adaptive-agent-service.md)

### By Complexity

- **Beginner**: ReAct, Reflex, Dialog
- **Intermediate**: Plan-Execute, Reflection, Hierarchical
- **Advanced**: Multi-Agent, MAKER, Coordinator, Adaptive Service

## 🤝 Contributing

Want to improve the documentation?

1. Check [CONTRIBUTING.md](../CONTRIBUTING.md)
2. Documentation is in Markdown format
3. Include code examples where helpful
4. Test all code examples before submitting

## 📝 Documentation Conventions

### Code Examples

All code examples should:
- Be complete and runnable
- Include necessary imports
- Follow PSR-12 coding standards
- Include comments for clarity

### Structure

Documentation should include:
- Clear title and description
- Table of contents for long docs
- Code examples with explanations
- Best practices and tips
- Related documentation links

## 🆘 Getting Help

- **Documentation Issues**: Open an issue on GitHub
- **Questions**: Use GitHub Discussions
- **Bugs**: Report in the issue tracker
- **Security**: See [SECURITY.md](../SECURITY.md)

## 📜 License

The documentation is part of the Claude PHP Agent Framework and is licensed under the [MIT License](../LICENSE).

---

**Tip**: Use the search function (Ctrl/Cmd + F) to quickly find what you're looking for!

