# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Viceroy** is a PHP library for interacting with Large Language Models (LLMs) through OpenAI-compatible APIs. It provides a unified interface for connecting to various LLM providers (OpenAI, OpenRouter, local llama.cpp) with support for tool calling, MCP protocol, streaming responses, and vision capabilities.

**PHP Version:** 8.1+ (ext-dom, ext-json, ext-curl required)

## Common Commands

```bash
# Install dependencies
composer install

# Run tests (PHPUnit)
phpunit tests/

# Run specific test file
phpunit tests/Connections/SelfDynamicParametersConnectionTest.php

# No build step required - PHP library with PSR-4 autoloading
```

## Configuration

Configuration is JSON-based and loaded via `ConfigObjects`:

```php
$config = new ConfigObjects('config.json');
$connection = new OpenAICompatibleEndpointConnection($config);
```

**Required config keys:** `apiEndpoint` (or `host`), `preferredModel`, `bearer`

**Template configs:** `config.localhost.json`, `config.openai.json`, `config.openrouter.json`

**Never commit `config.json`** - it contains API keys. Use environment variables instead:

```php
$config = new ConfigObjects([
    'bearer' => getenv('OPENAI_API_KEY'),
    'apiEndpoint' => getenv('LLM_ENDPOINT') ?: 'https://api.openai.com',
    'preferredModel' => getenv('LLM_MODEL') ?: 'gpt-4o'
]);
```

## Architecture Overview

The codebase follows a **layered architecture** with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────┐
│  Connection Layer    │  Core Layer      │  Extension Layer │
│  - OpenAICompat      │  - Request       │  - Tools         │
│  - Traits            │  - Response      │  - Plugins       │
│                      │  - RolesManager  │  - MCP Protocol  │
├─────────────────────────────────────────────────────────────┤
│              Configuration & Management Layer               │
│  - ConfigObjects    │  - ConfigManager │  - ToolManager   │
└─────────────────────────────────────────────────────────────┘
```

**Key Design Patterns:**
- **Strategy Pattern**: Different endpoint connection strategies
- **Observer Pattern**: Streaming callbacks and event handling
- **Factory Pattern**: Tool and plugin instantiation
- **Registry Pattern**: ToolRegistry and PluginManager for component discovery
- **Plugin Architecture**: Extensible functionality through plugins

### Main Entry Point

`OpenAICompatibleEndpointConnection` (src/Connections/Definitions/) is the primary class coordinating:
- ConfigManager for parameters
- RolesManager for conversation state
- ToolManager for tool execution
- PluginManager for plugin functionality
- Request/Response for HTTP handling

### Component Flow

**Request Flow:**
1. User calls `$connection->query()`
2. RolesManager formats request with conversation history
3. ConfigManager applies configuration parameters
4. Guzzle client sends HTTP request
5. Response class processes output with think-tag extraction
6. Tool calls processed if present
7. Final response returned with streaming support if enabled

**Tool Execution Flow:**
1. Tool definitions registered with ToolManager
2. MCP server plugin exposes tools via MCP protocol
3. LLM requests tool execution through function calling
4. ToolManager validates arguments and executes tool
5. Results returned to LLM for response generation

## Tool System

All tools implement `ToolInterface` with:
- `getName()` - Unique identifier
- `getDefinition()` - OpenAI function definition format
- `validateArguments()` - Input validation
- `execute()` - Tool logic returning `['content' => [...], 'isError' => bool]`

**Tool Discovery:** Tools are auto-discovered from `src/Tools/` via `ToolManager::discoverTools()`

**Built-in Tools:** AdvanceSearchTool, WebPageToMarkdownTool, GetCurrentDateTimeTool, GetRedditHot, SearchTool, SendTelegramMessageTool

## Plugin System

All plugins implement `PluginInterface` with:
- `getName()` - Plugin identifier
- `getType()` - Plugin type enum (GENERAL or BEFORE_API_CALL)
- `initialize()` - Setup with connection instance
- `canHandle()` - Method routing
- `handleMethodCall()` - Method execution

**Plugin registration:**
```php
$connection->registerPlugin(new MCPClientPlugin());
$connection->registerPlugin(new MCPServerPlugin());
```

**Plugin methods** are called via magic method: `$connection->{'tools/list'}([])`

## MCP Protocol

Full MCP 2025-06-18 specification compliance with JSON-RPC 2.0.

**MCP Client:** Connect to external MCP servers and consume tools
```php
$mcpClient = new MCPClientPlugin('http://localhost:8111');
$connection->registerPlugin($mcpClient);
$tools = $connection->{'tools/list'}([]);
```

**MCP Server:** Expose local tools via MCP protocol
```php
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);
```

## Response Handling

The `Response` class handles both streamed and complete responses:

```php
$response = $connection->query("Question");
$content = $response->getLlmResponse();      // Main response
$thinkContent = $response->getThinkContent(); // Extracted <think> tags
$wasStreamed = $response->wasStreamed();     // Check if streamed
```

**Streaming:**
```php
$response = $connection->query("Question", function($chunk, $tps) {
    echo $chunk; // Real-time output with tokens/sec
});
```

## Conversation Management

`RolesManager` handles multi-turn conversations:

```php
$connection->setSystemMessage("You are helpful.");
$connection->addUserMessage("What is PHP?");
$response1 = $connection->query();

$connection->addAssistantMessage($response1->getLlmResponse());
$connection->addUserMessage("Give me an example.");
$response2 = $connection->query();
```

## Important Patterns

**Fluent Interface:**
```php
$connection->setSystemMessage("...")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9);
```

**Tool Return Format:**
```php
return [
    'content' => [
        ['type' => 'text', 'text' => 'Result']
    ],
    'isError' => false
];
```

**Error Handling:**
- `GuzzleException` for HTTP errors
- `RuntimeException` for application errors
- `InvalidArgumentException` for validation errors

## Key File Locations

- Main connection: `src/Connections/Definitions/OpenAICompatibleEndpointConnection.php`
- Configuration: `src/Configuration/ConfigObjects.php`, `ConfigManager.php`
- Tool manager: `src/ToolManager.php`, `src/ToolRegistry.php`
- Plugin manager: `src/Core/PluginManager.php`
- Response handling: `src/Core/Response.php`
- Conversation: `src/Core/RolesManager.php`
- Examples: `examples/chat_sample.php`, `examples/mcp/`, `examples/describe_image.php`

## Documentation

- `README.md` - Basic usage and installation
- `AGENTS.md` - Comprehensive architecture and API reference (15KB)
- `project_description.md` - Architecture diagrams and component relationships
- `benchmark.md` - Performance benchmarking documentation
- `examples/mcp/mcp.md` - MCP protocol implementation details

## Development Notes

- PSR-4 autoloading: `Viceroy\` namespace maps to `src/`
- Classes: PascalCase, Methods: camelCase
- Comprehensive PHPDoc blocks on all classes
- All tools must validate arguments before execution
- Streaming callback should be non-blocking
- Clear conversation history periodically to prevent memory leaks
