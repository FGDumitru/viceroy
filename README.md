# Viceroy LLM Library

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue) ![License](https://img.shields.io/badge/License-MIT-green) ![GitHub](https://img.shields.io/badge/GitHub-FGDumitru%2Fviceroy-blue)

**Viceroy** is a comprehensive PHP library for interacting with Large Language Models through OpenAI-compatible APIs. Connect to OpenAI, OpenRouter, local llama.cpp instances, or any compatible endpoint with a unified interface.

## Features

- **Multi-Provider Support** - OpenAI, OpenRouter, local LLaMA.cpp, and any OpenAI-compatible endpoint
- **Tool/Function Calling** - Extensible tool system with MCP (Model Context Protocol) support
- **Streaming Responses** - Real-time streaming with performance metrics (tokens/sec)
- **Vision Capabilities** - Multimodal support for image processing and analysis
- **Conversation Management** - Multi-turn conversations with role-based message history
- **Plugin Architecture** - Extend functionality through a modular plugin system
- **Think-Tag Processing** - Extract reasoning content from `</think>` tags
- **Configuration Management** - Flexible JSON-based configuration with environment overrides

## Requirements

- PHP 8.1 or higher
- Extensions: `ext-dom`, `ext-json`, `ext-curl`

## Installation

```bash
composer require viceroy/llm-library
```

## Quick Start

### 1. Create Configuration

Create a `config.json` file:

```json
{
  "apiEndpoint": "https://api.openai.com",
  "preferredModel": "gpt-4o",
  "bearer": "your-api-key-here",
  "timeout": 30
}
```

Or use environment variables:

```php
$config = new ConfigObjects([
    'bearer' => getenv('OPENAI_API_KEY'),
    'apiEndpoint' => getenv('LLM_ENDPOINT') ?: 'https://api.openai.com',
    'preferredModel' => getenv('LLM_MODEL') ?: 'gpt-4o'
]);
```

### 2. Basic Query

```php
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$config = new ConfigObjects('config.json');
$connection = new OpenAICompatibleEndpointConnection($config);

$response = $connection->query("Explain quantum physics in simple terms");
echo $response->getLlmResponse();
```

## Table of Contents

- [Streaming Responses](#streaming-responses)
- [Multi-Turn Conversations](#multi-turn-conversations)
- [Tool System](#tool-system)
- [Image/Vision Support](#imagevision-support)
- [MCP Protocol](#mcp-protocol)
- [Plugin System](#plugin-system)
- [Configuration](#configuration)
- [Examples](#examples)
- [Documentation](#documentation)
- [License](#license)

---

## Streaming Responses

Stream responses in real-time with token-per-second metrics:

```php
$response = $connection->query("Write a short story about AI", function($chunk, $tps) {
    echo $chunk;                     // Real-time output
    echo " ({$tps} tokens/sec)\n";   // Performance metric
});

if ($response->wasStreamed()) {
    echo "\nStreamed response received";
}
```

## Multi-Turn Conversations

Maintain conversation context across multiple exchanges:

```php
$connection->setSystemMessage("You are a helpful PHP developer assistant.");

// First exchange
$connection->addUserMessage("What is dependency injection?");
$response1 = $connection->query();
echo $response1->getLlmResponse();

// Second exchange (context is maintained)
$connection->addAssistantMessage($response1->getLlmResponse());
$connection->addUserMessage("Can you show me an example?");
$response2 = $connection->query();
echo $response2->getLlmResponse();

// Clear conversation when needed
$connection->clear();
```

## Tool System

Enable LLM to call external functions and tools:

```php
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\SearchTool;

// Enable tool support
$connection->enableToolSupport();

// Add tools
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new SearchTool());

// LLM will automatically call tools as needed
$response = $connection->query("Search for latest PHP news and tell me the current time");
```

### Creating Custom Tools

```php
use Viceroy\Tools\Interfaces\ToolInterface;

class MyCustomTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'my_custom_tool',
                'description' => 'Does something useful',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string', 'description' => 'Input data']
                    ],
                    'required' => ['input']
                ]
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        return isset($arguments['input']) && is_string($arguments['input']);
    }

    public function execute(array $arguments, $configuration): array
    {
        // Tool logic here
        return [
            'content' => [['type' => 'text', 'text' => 'Result']],
            'isError' => false
        ];
    }
}

// Register your tool
$connection->addToolDefinition(new MyCustomTool());
```

## Image/Vision Support

Process and analyze images with multimodal models:

```php
$imagePath = 'path/to/image.jpg';
$imageData = base64_encode(file_get_contents($imagePath));

$response = $connection->query("Describe this image in detail", [
    'vision' => true,
    'image' => $imageData,
    'max_tokens' => 500
]);

echo $response->getLlmResponse();
```

**Configuration for vision support:**
```json
{
  "vision_support": true,
  "preferredModel": "gpt-4o"
}
```

## MCP Protocol

Viceroy supports the Model Context Protocol for tool discovery and execution.

### MCP Server - Expose Local Tools

```php
use Viceroy\Plugins\MCPServerPlugin;

// Create MCP server that exposes local tools
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// Tools are now available via MCP protocol
$tools = $connection->{'tools/list'}([]);
```

### MCP Client - Consume Remote Tools

```php
use Viceroy\Plugins\MCPClientPlugin;

// Connect to remote MCP server
$mcpClient = new MCPClientPlugin('http://localhost:8111');
$connection->registerPlugin($mcpClient);

// List available tools
$tools = $connection->{'tools/list'}([]);

// Call a tool
$result = $connection->{'tools/call'}([
    'name' => 'remote_tool_name',
    'arguments' => ['param' => 'value']
]);
```

## Plugin System

Extend functionality with custom plugins:

```php
use Viceroy\Core\PluginInterface;
use Viceroy\Core\PluginType;

class MyPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'my_plugin';
    }

    public function getType(): PluginType
    {
        return PluginType::GENERAL;
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void
    {
        // Plugin initialization
    }

    public function canHandle(string $method): bool
    {
        return $method === 'my_custom_method';
    }

    public function handleMethodCall(string $method, array $args): mixed
    {
        // Handle method call
        return 'result';
    }
}

// Register plugin
$connection->registerPlugin(new MyPlugin());

// Use plugin method via magic call
$result = $connection->{'my_custom_method'}($args);
```

## Configuration

### Configuration File Structure

```json
{
  "apiEndpoint": "https://api.openai.com",
  "preferredModel": "gpt-4o",
  "bearer": "your-api-key",
  "timeout": 30,
  "vision_support": true,
  "model_mappings": {
    "gpt-4": "company-llm-v4"
  }
}
```

### Environment-Specific Configurations

```bash
# Development
config.localhost.json  # For local llama.cpp

# Production providers
config.openai.json      # OpenAI API
config.openrouter.json  # OpenRouter API
```

### Programmatic Configuration

```php
$config = new ConfigObjects([
    'apiEndpoint' => 'https://api.openai.com',
    'bearer' => getenv('OPENAI_API_KEY'),
    'preferredModel' => 'gpt-4o',
    'timeout' => 30,
    'vision_support' => true
]);

$connection = new OpenAICompatibleEndpointConnection($config);

// Fluent interface for parameters
$connection->setSystemMessage("You are helpful.")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9)
    ->setParameter('max_tokens', 2000);
```

## Examples

Check the `examples/` directory for complete working examples:

- `chat_sample.php` - Interactive chat with streaming
- `describe_image.php` - Image analysis with vision
- `stream_realtime_example.php` - Real-time streaming demo
- `benchmark_extra.php` - Performance benchmarking
- `query_llamacpp.php` - Local llama.cpp example
- `mcp/mcp_simple_server.php` - MCP server setup
- `mcp/mcp_client_plugin_example.php` - MCP client usage

Run examples:

```bash
php examples/chat_sample.php
php examples/describe_image.php
```

## Documentation

- **[AGENTS.md](AGENTS.md)** - Comprehensive architecture and API reference
- **[CLAUDE.md](CLAUDE.md)** - Development guide for contributors
- **[project_description.md](project_description.md)** - Architecture diagrams
- **[benchmark.md](benchmark.md)** - Performance benchmarking guide
- **[examples/mcp/mcp.md](examples/mcp/mcp.md)** - MCP protocol details

## License

MIT License

Copyright (c) 2024 Viceroy Project

---

**GitHub:** [https://github.com/FGDumitru/viceroy](https://github.com/FGDumitru/viceroy)