#!/bin/bash

# MCP Integration Test Runner
# Runs comprehensive integration tests with real API calls

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  MCP Server Integration Test Runner${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Check for .env file
if [ ! -f .env ]; then
    echo -e "${RED}Error: .env file not found${NC}"
    echo "Please create a .env file with your ANTHROPIC_API_KEY"
    echo "Example: echo 'ANTHROPIC_API_KEY=your_key' > .env"
    exit 1
fi

# Check if API key is set
if ! grep -q "ANTHROPIC_API_KEY" .env; then
    echo -e "${RED}Error: ANTHROPIC_API_KEY not found in .env${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Environment configured${NC}"
echo ""

# Parse command line arguments
RUN_ALL=true
RUN_FAST_ONLY=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --fast)
            RUN_FAST_ONLY=true
            RUN_ALL=false
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            echo "Usage: ./run-integration-tests.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --fast      Run only fast tests (exclude slow API tests)"
            echo "  --verbose   Show detailed test output"
            echo "  --help      Show this help message"
            echo ""
            echo "Test Suites:"
            echo "  1. MCP Server Integration (fast)"
            echo "  2. Agent Execution (slow - real API calls)"
            echo "  3. Visualization (medium)"
            echo "  4. End-to-End Features (slow - real API calls)"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Run with --help for usage information"
            exit 1
            ;;
    esac
done

# Build PHPUnit command
PHPUNIT_CMD="./vendor/bin/phpunit"
PHPUNIT_ARGS=""

if [ "$VERBOSE" = true ]; then
    PHPUNIT_ARGS="$PHPUNIT_ARGS --testdox"
fi

# Run tests
if [ "$RUN_FAST_ONLY" = true ]; then
    echo -e "${YELLOW}Running fast tests only (excluding slow API tests)...${NC}"
    echo ""
    $PHPUNIT_CMD tests/Integration/MCP/MCPServerIntegrationTest.php $PHPUNIT_ARGS
    $PHPUNIT_CMD tests/Integration/MCP/VisualizationIntegrationTest.php $PHPUNIT_ARGS --exclude-group slow
else
    echo -e "${YELLOW}Running all integration tests (this may take 2-4 minutes)...${NC}"
    echo ""
    
    echo -e "${BLUE}[1/4] MCP Server Integration Tests${NC}"
    $PHPUNIT_CMD tests/Integration/MCP/MCPServerIntegrationTest.php $PHPUNIT_ARGS
    echo ""
    
    echo -e "${BLUE}[2/4] Agent Execution Tests (with real API calls)${NC}"
    $PHPUNIT_CMD tests/Integration/MCP/AgentExecutionIntegrationTest.php $PHPUNIT_ARGS
    echo ""
    
    echo -e "${BLUE}[3/4] Visualization Tests${NC}"
    $PHPUNIT_CMD tests/Integration/MCP/VisualizationIntegrationTest.php $PHPUNIT_ARGS
    echo ""
    
    echo -e "${BLUE}[4/4] End-to-End Feature Tests (with real API calls)${NC}"
    $PHPUNIT_CMD tests/Integration/MCP/EndToEndFeatureTest.php $PHPUNIT_ARGS
    echo ""
fi

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}  All tests passed! ✓${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo "MCP Server is production ready!"
echo ""
