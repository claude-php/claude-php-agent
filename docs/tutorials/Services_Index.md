# Service Layer Tutorials - Complete Index

A comprehensive guide to all Service Layer tutorials for the Claude PHP Agent framework.

## 📚 Tutorial Series

### Getting Started
1. **[Getting Started with Services](Services_GettingStarted.md)** ⭐ START HERE
   - ServiceManager basics
   - Service registration and usage
   - Dependency injection
   - Configuration management
   - Testing with mocks
   - **Time:** 30 minutes
   - **Level:** Beginner

### Core Services

2. **[CacheService Tutorial](Services_Cache.md)**
   - Multiple backends (Array, File, Redis)
   - Cache patterns (Remember, Cache-Aside, Write-Through)
   - Namespacing
   - TTL management
   - Multi-level caching
   - **Time:** 45 minutes
   - **Level:** Beginner

3. **[StorageService Tutorial](Services_Storage.md)**
   - File operations (save, get, delete)
   - User/flow scoping
   - Subdirectories
   - Atomic writes
   - Common patterns
   - **Time:** 35 minutes
   - **Level:** Beginner

4. **[VariableService Tutorial](Services_Variables.md)**
   - Variable types (Generic vs Credential)
   - Encryption (AES-256-GCM)
   - API key management
   - User scoping
   - Security best practices
   - **Time:** 40 minutes
   - **Level:** Intermediate

5. **[SessionService Tutorial](Services_Sessions.md)**
   - Session creation and management
   - Expiration handling
   - Session extension
   - Common patterns (conversations, shopping carts)
   - Session security
   - **Time:** 35 minutes
   - **Level:** Beginner

### Observability Services

6. **[TracingService Tutorial](Services_Tracing.md)**
   - Distributed tracing
   - LangSmith, LangFuse, Arize Phoenix
   - Spans and metrics
   - Agent integration
   - Debugging with traces
   - **Time:** 50 minutes
   - **Level:** Intermediate

7. **[TelemetryService Tutorial](Services_Telemetry.md)**
   - Metric types (Counters, Gauges, Histograms)
   - Agent performance tracking
   - Custom metrics
   - Real-time monitoring
   - Alerting
   - **Time:** 40 minutes
   - **Level:** Intermediate

### Advanced Topics

8. **[Service Best Practices](Services_BestPractices.md)**
   - Architecture principles
   - Dependency management
   - Configuration strategies
   - Testing patterns
   - Performance optimization
   - Security guidelines
   - **Time:** 45 minutes
   - **Level:** Advanced

## 🗺️ Learning Paths

### Path 1: Quick Start (Beginners)
**Goal:** Get up and running quickly

1. [Getting Started](Services_GettingStarted.md) - 30 min
2. [CacheService](Services_Cache.md) - 45 min
3. [StorageService](Services_Storage.md) - 35 min
4. [Best Practices](Services_BestPractices.md) - 45 min

**Total Time:** ~2.5 hours  
**You'll learn:** Basic service usage, common patterns, production tips

### Path 2: Production Ready (Intermediate)
**Goal:** Build production-grade applications

1. [Getting Started](Services_GettingStarted.md) - 30 min
2. [CacheService](Services_Cache.md) - 45 min
3. [StorageService](Services_Storage.md) - 35 min
4. [VariableService](Services_Variables.md) - 40 min
5. [SessionService](Services_Sessions.md) - 35 min
6. [TelemetryService](Services_Telemetry.md) - 40 min
7. [Best Practices](Services_BestPractices.md) - 45 min

**Total Time:** ~4.5 hours  
**You'll learn:** Complete service stack, security, monitoring

### Path 3: Observability Expert (Advanced)
**Goal:** Master monitoring and debugging

1. [Getting Started](Services_GettingStarted.md) - 30 min
2. [TracingService](Services_Tracing.md) - 50 min
3. [TelemetryService](Services_Telemetry.md) - 40 min
4. [Best Practices](Services_BestPractices.md) - 45 min

**Total Time:** ~3 hours  
**You'll learn:** Distributed tracing, metrics, debugging production issues

### Path 4: Security Focused
**Goal:** Secure applications with encrypted secrets

1. [Getting Started](Services_GettingStarted.md) - 30 min
2. [VariableService](Services_Variables.md) - 40 min
3. [SessionService](Services_Sessions.md) - 35 min
4. [Best Practices](Services_BestPractices.md) - 45 min

**Total Time:** ~2.5 hours  
**You'll learn:** Encryption, secure storage, session security

## 📖 Tutorial Structure

Each tutorial includes:

- **Overview** - What the service does and why you need it
- **Basic Usage** - Quick start with code examples
- **Core Features** - Detailed feature explanations
- **Common Patterns** - Real-world usage patterns
- **Best Practices** - Production tips and guidelines
- **Summary** - Key takeaways and next steps

## 💡 Tutorial Format

### Code Examples
All examples are:
- ✅ Production-ready
- ✅ Fully working code
- ✅ Well-commented
- ✅ Following best practices

### Prerequisites
- PHP 8.1 or higher
- Composer installed
- Claude PHP Agent framework installed
- Basic PHP knowledge

### Running Examples

```bash
# Clone the repository
git clone https://github.com/claude-php/agent.git
cd agent

# Install dependencies
composer install

# Run service examples
php examples/Services/basic-usage.php
php examples/Services/agent-integration.php
```

## 🎯 Quick Reference

| Service | Primary Use Case | Key Feature |
|---------|-----------------|-------------|
| **Settings** | Configuration | Environment overrides |
| **Cache** | Performance | Multiple backends |
| **Storage** | Persistence | User scoping |
| **Variable** | Secrets | Encryption |
| **Session** | User state | Auto-expiration |
| **Tracing** | Debugging | Distributed traces |
| **Telemetry** | Monitoring | Metrics collection |
| **Transaction** | Data integrity | Rollback support |

## 🔗 Related Documentation

- [Service Layer Overview](../services/README.md) - Complete documentation
- [Migration Guide](../services/MIGRATION.md) - Migrate existing code
- [API Reference](../../README.md) - Framework documentation
- [Examples](../../examples/Services/) - Working code examples

## 📊 By Difficulty

### Beginner
- Getting Started
- CacheService
- StorageService
- SessionService

### Intermediate
- VariableService
- TracingService
- TelemetryService

### Advanced
- Best Practices
- Custom service creation
- Production optimization

## 🎓 Certification Checklist

Master the Service Layer by completing:

- [ ] Read all 8 tutorials
- [ ] Complete all code examples
- [ ] Run all example scripts
- [ ] Write your own service
- [ ] Integrate services in an agent
- [ ] Write tests using service mocks
- [ ] Deploy a production app with services

## 🆘 Need Help?

- **Documentation Issues:** [GitHub Issues](https://github.com/claude-php/agent/issues)
- **Questions:** Check [Migration Guide](../services/MIGRATION.md)
- **Examples:** See [examples/Services/](../../examples/Services/)
- **API Docs:** See [services/README.md](../services/README.md)

## 📅 Learning Schedule

### Week 1: Foundations
- Day 1: Getting Started + CacheService
- Day 2: StorageService + hands-on practice
- Day 3: VariableService + SessionService
- Day 4: Build a small project using services
- Day 5: Review and practice

### Week 2: Production
- Day 1: TracingService
- Day 2: TelemetryService
- Day 3: Best Practices
- Day 4: Refactor project with best practices
- Day 5: Testing and deployment

## 🚀 Ready to Start?

Begin with **[Getting Started](Services_GettingStarted.md)** and work through the tutorials in order.

Each tutorial builds on the previous one, so following the sequence is recommended.

**Happy learning!** 🎉

---

*Last Updated: February 2026*  
*Service Layer Version: 1.0*
