# MCP Integration Tests

End-to-end integration tests for the MCP Server that use real API calls.

## Requirements

- `ANTHROPIC_API_KEY` must be set in `.env` file or environment
- Active internet connection for API calls
- PHPUnit installed

## Test Suites

### 1. MCPServerIntegrationTest
**Purpose:** Tests core MCP protocol functionality  
**Coverage:**
- Protocol initialization
- Tool listing and registration
- Request/response handling
- Error handling
- Session persistence

**Run:** `./vendor/bin/phpunit tests/Integration/MCP/MCPServerIntegrationTest.php`

### 2. AgentExecutionIntegrationTest
**Purpose:** Tests real agent execution through MCP  
**Coverage:**
- Running various agent types (React, ChainOfThought, Dialog)
- Execution status tracking
- Multiple concurrent executions
- Custom agent options
- Error scenarios

**Run:** `./vendor/bin/phpunit tests/Integration/MCP/AgentExecutionIntegrationTest.php`

**Note:** This suite makes real API calls and may take longer to run.

### 3. VisualizationIntegrationTest
**Purpose:** Tests workflow visualization features  
**Coverage:**
- ASCII diagram generation for all agent types
- Graph representation
- Configuration export
- Visualization with tools
- Quality checks

**Run:** `./vendor/bin/phpunit tests/Integration/MCP/VisualizationIntegrationTest.php`

### 4. EndToEndFeatureTest
**Purpose:** Complete user workflow scenarios  
**Coverage:**
- Agent discovery → execution workflow
- Workflow visualization workflow
- Multi-agent comparison
- Configuration management
- Tool discovery
- Error recovery

**Run:** `./vendor/bin/phpunit tests/Integration/MCP/EndToEndFeatureTest.php`

**Note:** Most comprehensive suite, tests complete features end-to-end.

## Running Tests

### All MCP Integration Tests
```bash
./vendor/bin/phpunit tests/Integration/MCP
```

### Specific Test Groups
```bash
# Integration tests only
./vendor/bin/phpunit --group integration

# MCP tests only
./vendor/bin/phpunit --group mcp

# E2E feature tests
./vendor/bin/phpunit --group e2e

# Visualization tests
./vendor/bin/phpunit --group visualization

# Slow tests (with API calls)
./vendor/bin/phpunit --group slow
```

### Exclude Slow Tests
```bash
./vendor/bin/phpunit tests/Integration/MCP --exclude-group slow
```

## Test Data

Tests use real API calls with:
- Simple prompts (e.g., "What is 15 + 27?")
- Minimal token usage
- Various agent types
- Multiple scenarios

## Expected Results

**Successful Run:**
```
PHPUnit 10.x

Integration\MCP\MCPServerIntegrationTest
 ✔ Server initialization
 ✔ Initialize protocol
 ✔ List tools endpoint
 ✔ Search agents tool
 ... (and more)

Integration\MCP\AgentExecutionIntegrationTest
 ✔ Run simple agent
 ✔ Run chain of thought agent
 ✔ Execution status tracking
 ... (and more)

Integration\MCP\VisualizationIntegrationTest
 ✔ Complete workflow visualization
 ✔ Visualization with tools
 ... (and more)

Integration\MCP\EndToEndFeatureTest
 ✔ Complete agent discovery and execution workflow
 ✔ Complete visualization workflow
 ... (and more)

Time: XX.XX seconds, Memory: XX.XX MB

OK (50+ tests)
```

## Troubleshooting

### "ANTHROPIC_API_KEY not set"
**Solution:** Create `.env` file in project root:
```env
ANTHROPIC_API_KEY=your_api_key_here
```

### "Connection timeout"
**Solution:** Check internet connection or increase timeout:
```php
$this->client = new ClaudePhp(
    apiKey: $apiKey,
    timeout: 60
);
```

### "Rate limit exceeded"
**Solution:** Add delays between tests or run specific test suites:
```bash
./vendor/bin/phpunit tests/Integration/MCP/MCPServerIntegrationTest.php
# Wait a few seconds
./vendor/bin/phpunit tests/Integration/MCP/AgentExecutionIntegrationTest.php
```

### Test Failures

If tests fail:
1. Check API key is valid
2. Verify internet connection
3. Check Claude API status
4. Review error messages for specific issues

## CI/CD Integration

For CI environments, set API key as secret:
```yaml
env:
  ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}

script:
  - ./vendor/bin/phpunit tests/Integration/MCP
```

**Note:** Consider running integration tests separately from unit tests in CI to avoid API costs.

## Test Coverage

The integration test suite covers:
- ✅ 15 MCP tools
- ✅ 16+ agent types
- ✅ Both success and error scenarios
- ✅ Session management
- ✅ Configuration validation
- ✅ Workflow visualization
- ✅ Real API execution
- ✅ Complete user workflows

## Performance

**Typical test run times:**
- MCPServerIntegrationTest: ~10-15 seconds
- AgentExecutionIntegrationTest: ~30-60 seconds (slow)
- VisualizationIntegrationTest: ~20-30 seconds
- EndToEndFeatureTest: ~60-120 seconds (slow)

**Total:** ~2-4 minutes for complete suite

## Best Practices

1. **Run locally before committing** - Ensure all tests pass
2. **Use test groups** - Run relevant tests for your changes
3. **Monitor API usage** - Integration tests make real API calls
4. **Check .env file** - Never commit API keys
5. **Review failures carefully** - May indicate real issues

## Contributing

When adding integration tests:
1. Use `@group integration` annotation
2. Add appropriate group tags (`@group mcp`, `@group slow`, etc.)
3. Include clear test descriptions
4. Test both success and failure scenarios
5. Document any special requirements
