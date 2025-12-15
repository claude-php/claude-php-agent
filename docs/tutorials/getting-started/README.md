# Getting Started with Claude PHP Agent Framework

A progressive, beginner-friendly tutorial series that teaches you how to build intelligent AI agents from the ground up.

## 🎯 What You'll Learn

By the end of this series, you'll understand:

- Core concepts of agentic AI
- How to build agents with tools
- The ReAct pattern for multi-step reasoning
- Production-ready error handling
- Best practices for agent development

## 👥 Who This Is For

This series is designed for **PHP developers new to AI agents**. We'll explain AI concepts as we go, so prior experience with LLMs is helpful but not required.

You should be comfortable with:

- PHP 8.1+ syntax
- Composer for dependency management
- Basic OOP concepts
- JSON structures

## 📋 Prerequisites

Before starting, make sure you have:

1. **PHP 8.1 or higher** installed
2. **Composer** for dependency management
3. **Anthropic API Key** ([Get one here](https://console.anthropic.com/))
4. **Claude PHP Agent Framework** installed:

   ```bash
   composer require claude-php/claude-php-agent
   ```

5. **Environment Setup**:

   Create a `.env` file in your project root:

   ```env
   ANTHROPIC_API_KEY=your-api-key-here
   ```

## 🚀 Tutorial Series

### [Tutorial 0: Introduction to Agentic AI](./00-Introduction.md)

**Time: 20 minutes** | **Difficulty: Beginner**

Understand the fundamental concepts of AI agents, autonomy, and the ReAct pattern.

**What You'll Learn:**

- Agents vs chatbots
- What makes an agent "agentic"
- The ReAct (Reason-Act-Observe) pattern
- When to use agents vs simple API calls
- Agent taxonomy overview

---

### [Tutorial 1: Your First Agent](./01-First-Agent.md)

**Time: 30 minutes** | **Difficulty: Beginner**

Build your first working agent with a single tool (calculator).

**What You'll Learn:**

- Tool definitions and input schemas
- Creating an agent with the framework
- Request → Tool call → Execute → Response flow
- Handling tool results
- Basic debugging with callbacks
- Token usage tracking

---

### [Tutorial 2: ReAct Loop Basics](./02-ReAct-Basics.md)

**Time: 45 minutes** | **Difficulty: Intermediate**

Implement a ReAct loop that enables iterative reasoning and action.

**What You'll Learn:**

- The Reason → Act → Observe loop
- Stop conditions and loop control
- Multi-turn conversations with state
- Iteration limits and why they matter
- Debugging agent reasoning

---

### [Tutorial 3: Multi-Tool Agent](./03-Multi-Tool.md)

**Time: 45 minutes** | **Difficulty: Intermediate**

Expand your agent with multiple tools and intelligent tool selection.

**What You'll Learn:**

- Defining multiple diverse tools
- How Claude selects the right tool
- Parameter extraction and validation
- Tool result formatting
- Debugging tool selection
- Best practices for tool design

---

### [Tutorial 4: Production-Ready Patterns](./04-Production-Patterns.md)

**Time: 60 minutes** | **Difficulty: Intermediate**

Build robust, production-ready agents with proper error handling and resilience.

**What You'll Learn:**

- Comprehensive error handling
- Retry logic with exponential backoff
- Circuit breaker pattern
- Tool execution error reporting
- Graceful degradation
- Logging and monitoring
- Rate limiting

---

### [Tutorial 5: Advanced Patterns](./05-Advanced-Patterns.md)

**Time: 60 minutes** | **Difficulty: Advanced**

Master advanced patterns including planning, reflection, and self-correction.

**What You'll Learn:**

- Plan → Execute → Reflect → Adjust pattern
- Extended thinking for complex reasoning
- Self-correction and adaptation
- Multi-step task decomposition
- Reasoning transparency
- When to use advanced patterns

---

### [Tutorial 6: Design Patterns](./06-Design-Patterns.md)

**Time: 45 minutes** | **Difficulty: Intermediate**

Learn industry-standard design patterns for production-quality agent code.

**What You'll Learn:**

- Factory Pattern for consistent agent creation
- Builder Pattern for type-safe configuration
- Observer Pattern for event-driven monitoring
- Strategy Pattern for flexible response parsing
- Template Method for structured prompts
- Combining patterns for production code

---

## 🎓 Learning Path

Follow these tutorials in order to build core competency:

```
Tutorial 0 (Concepts)
    ↓
Tutorial 1 (First Agent)
    ↓
Tutorial 2 (ReAct Loop)
    ↓
Tutorial 3 (Multi-Tool)
    ↓
Tutorial 4 (Production)
    ↓
Tutorial 5 (Advanced)
    ↓
Tutorial 6 (Design Patterns) ← Recommended for production!
```

### Quick Start Options

If you're already familiar with certain concepts, you can jump to:

- **Tutorial 1** if you understand agentic AI concepts
- **Tutorial 2** if you've built simple agents before
- **Tutorial 3** if you understand ReAct loops
- **Tutorial 4** if you're ready for production
- **Tutorial 5** if you want advanced patterns
- **Tutorial 6** if you want production-quality code patterns

## 💡 Tips for Success

1. **Run the Code**: Each tutorial has complete, executable examples
2. **Experiment**: Modify the examples, add new tools, change prompts
3. **Read Comments**: The code is heavily commented to explain every decision
4. **Check Costs**: We show token usage - be mindful when experimenting
5. **Debug Reasoning**: Use callbacks to understand agent decisions
6. **Start Simple**: Don't skip ahead - foundations matter!

## 🐛 Common Issues

### "API Key not found"

- Ensure your `.env` file exists in the project root
- Check the key is set: `ANTHROPIC_API_KEY=sk-ant-...`
- Make sure you're loading the `.env` file in your PHP script

### "Tool not executing"

- Verify tool name matches exactly
- Check input schema matches the data
- Look at `stop_reason` - should be `tool_use`
- Check callbacks to see what the agent is thinking

### "Agent loops infinitely"

- Set max iterations (we use 5-10 by default)
- Check stop conditions in your loop
- Verify tool results are being returned correctly

### "High token usage"

- Use prompt caching for repeated context
- Limit conversation history length
- Optimize tool descriptions
- Reduce `max_tokens` if responses are verbose

## 📊 After Completing

Once you've finished the Getting Started series, you're ready to:

1. **Explore Specialized Agents**

   - RAG patterns
   - Hierarchical agents
   - Autonomous agents
   - Multi-agent systems

2. **Build Real Applications**

   - Customer support bots
   - Research assistants
   - Data analysis tools
   - Content generation pipelines

3. **Advanced Topics**
   - Tree of Thoughts
   - Multi-agent debate
   - Learning agents
   - Custom agent patterns

See the [main tutorials directory](../) for specialized agent tutorials.

## 🆘 Getting Help

### During Tutorials

- Each tutorial has a **Troubleshooting** section
- Check the **Checkpoint** sections to verify understanding
- Review the complete examples in `/examples`

### Additional Resources

- [Main Documentation](../../../README.md)
- [Agent Selection Guide](../../agent-selection-guide.md)
- [Tools Documentation](../../Tools.md)
- [API Reference](../../contracts.md)
- [Examples Directory](../../../examples/)

### Community

- GitHub Issues for bug reports
- GitHub Discussions for questions
- Check existing examples for patterns

## 📝 Feedback

Found an issue or have suggestions? Please open an issue on GitHub!

---

**Ready to start?** → Begin with [Tutorial 0: Introduction to Agentic AI](./00-Introduction.md)

---

_Last Updated: December 2024_  
_Framework Version: 2.0+_
