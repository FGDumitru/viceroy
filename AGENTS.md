# Viceroy LLM Library - Comprehensive Agents Documentation

## Table of Contents
1. [Project Overview and Purpose](#project-overview-and-purpose)
2. [Architecture Overview](#architecture-overview)
3. [Core Components Analysis](#core-components-analysis)
4. [Dependencies and Requirements](#dependencies-and-requirements)
5. [Configuration System](#configuration-system)
6. [Tool System](#tool-system)
7. [Plugin System](#plugin-system)
8. [MCP Implementation](#mcp-implementation)
9. [Usage Patterns and Examples](#usage-patterns-and-examples)
10. [Performance and Optimization](#performance-and-optimization)
11. [Development Guidelines](#development-guidelines)
12. [API Reference Summary](#api-reference-summary)

---

## Project Overview and Purpose

### What the Library Does and Its Main Purpose

The Viceroy LLM Library is a comprehensive PHP library designed for seamless interaction with Large Language Models (LLMs) through OpenAI-compatible APIs. It provides a unified interface for connecting to various LLM providers including OpenAI, local LLaMA.cpp instances, and other compatible endpoints.

### Key Features and Capabilities

- **Multi-Provider Support**: Connect to OpenAI, OpenRouter, local LLaMA.cpp, and any OpenAI-compatible endpoint
- **Tool System**: Extensible tool framework for function calling and custom operations
- **Plugin Architecture**: Modular plugin system for extending functionality
- **MCP Protocol Support**: Full Model Context Protocol implementation for tool discovery and execution
- **Streaming Support**: Real-time response streaming with performance metrics
- **Vision Capabilities**: Multimodal support for image processing and analysis
- **Conversation Management**: Role-based conversation history and context management
- **Configuration Management**: Flexible configuration system with environment-specific settings
- **Performance Monitoring**: Built-in benchmarking and token-per-second metrics

### Target Audience and Use Cases

**Target Audience:**
- PHP developers building LLM-powered applications
- Enterprise developers integrating AI capabilities into existing systems
- Researchers and prototypers working with language models
- Developers requiring local LLM integration

**Primary Use Cases:**
- Chatbots and conversational AI interfaces
- Content generation and summarization tools
- Data analysis and research automation
- Code generation and documentation tools
- Multimodal applications with vision capabilities
- Custom tool integration and function calling

---

## Architecture Overview

### Core Components and Their Relationships

```
┌─────────────────────────────────────────────────────────────┐
│                    Viceroy LLM Library                     │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │   Connections   │  │    Core         │  │  Plugins     │ │
│  │                 │  │                 │  │              │ │
│  │ OpenAICompat    │  │ Request         │  │ MCPClient    │ │
│  │ TraitableConn   │  │ Response        │  │ MCPServer    │ │
│  │ LLMParamsTrait  │  │ RolesManager    │  │ SelfDefFunc  │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │ Configuration   │  │    Tools        │  │   Examples   │ │
│  │                 │  │                 │  │              │ │
│  │ ConfigManager   │  │ ToolManager     │  │ Chat Samples │ │
│  │ ConfigObjects   │  │ ToolRegistry    │  │ MCP Examples │ │
│  │                 │  │ ToolInterface    │  │ Benchmarks   │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Design Patterns and Principles Used

**Design Patterns:**
- **Strategy Pattern**: Connection management with different endpoint strategies
- **Observer Pattern**: Streaming callbacks and event handling
- **Factory Pattern**: Tool and plugin instantiation
- **Proxy Pattern**: TraitableConnectionAbstractClass for method forwarding
- **Registry Pattern**: ToolRegistry and PluginManager for component discovery
- **Command Pattern**: Tool execution and MCP method calls

**Architectural Principles:**
- **Separation of Concerns**: Clear boundaries between connections, tools, and plugins
- **Dependency Injection**: Configuration and dependencies injected through constructors
- **Interface Segregation**: Specific interfaces for tools, plugins, and connections
- **Open/Closed Principle**: Extensible through plugins and tools without modifying core
- **Single Responsibility**: Each class has a focused, well-defined purpose

### System Architecture and Data Flow

**Request Flow:**
1. User initiates query through OpenAICompatibleEndpointConnection
2. Request is formatted by RolesManager with conversation history
3. Configuration parameters are applied via ConfigManager
4. HTTP request sent through Guzzle client
5. Response processed by Response class with think-tag extraction
6. Tool calls processed if present in response
7. Final response returned with streaming support if enabled

**Tool Execution Flow:**
1. Tool definitions registered with ToolManager
2. MCP server plugin exposes tools via MCP protocol
3. LLM requests tool execution through function calling
4. ToolManager validates arguments and executes tool
5. Results returned to LLM for response generation

---

## Core Components Analysis

### Connection Management System

**OpenAICompatibleEndpointConnection**
The primary connection class implementing OpenAI-compatible API communication:

```php
// Core connection initialization
$connection = new OpenAICompatibleEndpointConnection($config);
$connection->setSystemMessage("You are a helpful assistant.")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9);

// Execute query with optional streaming
$response = $connection->query("Explain quantum physics", function($chunk, $tps) {
    echo $chunk; // Real-time streaming
});
```

**Key Features:**
- HTTP request/response handling via Guzzle
- Parameter management with LLMDefaultParametersTrait
- Streaming support with performance metrics
- Tool integration and function calling
- Multi-turn conversation support through RolesManager
- Error handling and retry logic

**TraitableConnectionAbstractClass**
Provides proxy pattern implementation for method forwarding and trait composition:

```php
// Dynamic method forwarding through proxy
$connection = new TraitableConnectionAbstractClass('MyConnection');
$result = $connection->anyMethod($args); // Forwards to wrapped connection
```

### Configuration System

**ConfigManager**
Central configuration management with prompt processing:

```php
$config = new ConfigObjects('config.json');
$configManager = new ConfigManager($config);
$jsonPrompt = $configManager->getJsonPrompt('chat');
```

**ConfigObjects**
Configuration data container with JSON file support:

```php
$config = new ConfigObjects([
    'host' => 'https://api.openai.com',
    'bearer' => 'your-api-key',
    'preferredModel' => 'gpt-4o'
]);
```

### Tool System Architecture

**ToolManager**
Central tool management with discovery and execution:

```php
$toolManager = new ToolManager();
$toolManager->discoverTools(); // Auto-discover tools
$toolManager->registerTool($customTool); // Manual registration
$result = $toolManager->executeTool('tool_name', $arguments, $config);
```

**ToolRegistry**
Tool storage and management with enable/disable functionality:

```php
$registry = new ToolRegistry();
$registry->registerTool($tool);
$registry->enableTool('tool_name');
$definitions = $registry->getToolDefinitions();
```

**ToolInterface**
Standard interface for all tool implementations:

```php
interface ToolInterface
{
    public function getName(): string;
    public function getDefinition(): array;
    public function execute(array $arguments, $configuration): array;
    public function validateArguments(array $arguments): bool;
}
```

### Plugin System

**PluginManager**
Plugin registration and management:

```php
$pluginManager = new PluginManager();
$pluginManager->add($mcpPlugin);
$plugin = $pluginManager->get('mcp_client');
```

**PluginInterface**
Standard interface for all plugins:

```php
interface PluginInterface
{
    public function getName(): string;
    public function getType(): PluginType;
    public function initialize(OpenAICompatibleEndpointConnection $connection): void;
    public function canHandle(string $method): bool;
    public function handleMethodCall(string $method, array $args): mixed;
}
```

### MCP Implementation

**MCPClientPlugin**
Client-side MCP protocol implementation:

```php
$mcpClient = new MCPClientPlugin('http://localhost:8111');
$connection->registerPlugin($mcpClient);
$tools = $connection->{'tools/list'}([]);
$result = $connection->{'tools/call'}(['name' => 'tool_name', 'arguments' => []]);
```

**MCPServerPlugin**
Server-side MCP protocol implementation:

```php
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);
// Now handles tools/list and tools/call methods
```

### Response Handling

**Response Class**
Comprehensive response processing with think-tag extraction:

```php
$response = $connection->query("Your question here");

// Get processed content
$content = $response->getLlmResponse();
$thinkContent = $response->getThinkContent();

// Check if streamed
if ($response->wasStreamed()) {
    echo "Response was streamed";
}

// Get raw response
$raw = $response->getRawResponse();
```

**Key Features:**
- Dual-mode handling for streamed/complete responses
- Think-tag extraction for internal metadata
- Caching of processed content for performance
- PSR-7 response integration
- Content validation and sanitization

---

## Dependencies and Requirements

### PHP Version Requirements

- **Minimum PHP Version**: 8.1+
- **Recommended PHP Version**: 8.2+ for optimal performance
- **Required Extensions**: 
  - `ext-dom` for HTML processing
  - `ext-json` for JSON handling
  - `ext-curl` for HTTP requests (via Guzzle)

### Complete Dependency Analysis

**Production Dependencies:**
```json
{
    "guzzlehttp/guzzle": "^7.10",
    "league/html-to-markdown": "^5.1",
    "pixel418/markdownify": "^2.3",
    "league/commonmark": "^2.7",
    "php": ">=8.1",
    "ext-dom": "*"
}
```

**Development Dependencies:**
```json
{
    "phpunit/phpunit": "^10.5"
}
```

### Why Each Dependency is Needed

**guzzlehttp/guzzle (^7.10)**
- HTTP client for API communication
- Handles requests to OpenAI-compatible endpoints
- Provides streaming support and error handling
- Essential for all HTTP-based operations

**league/html-to-markdown (^5.1)**
- Converts HTML content to Markdown format
- Used by WebPageToMarkdownTool for content processing
- Enables clean text extraction from web pages

**pixel418/markdownify (^2.3)**
- Alternative Markdown conversion library
- Provides additional formatting options
- Used in content processing pipelines

**league/commonmark (^2.7)**
- Markdown parser and renderer
- Handles Markdown-to-HTML conversion when needed
- Supports extended Markdown syntax

**ext-dom (*)**
- PHP DOM extension for HTML parsing
- Required for web content extraction
- Used by tools that process HTML content

---

## Configuration System

### Configuration File Structure

**Basic Configuration (config.json):**
```json
{
  "apiEndpoint": "https://api.openai.com",
  "preferredModel": "gpt-4o",
  "bearer": "your-api-key-here",
  "timeout": 30,
  "vision_support": true,
  "model_mappings": {
    "gpt-4": "company-llm-v4"
  }
}
```

**OpenAI Configuration (config.openai.json):**
```json
{
  "apiEndpoint": "https://api.openai.com",
  "preferredModel": "gpt-4o",
  "bearer": ""
}
```

**OpenRouter Configuration (config.openrouter.json):**
```json
{
  "apiEndpoint": "https://openrouter.ai/api",
  "preferredModel": "gpt-4o",
  "bearer": ""
}
```

### Environment-Specific Configurations

**Development Environment:**
```php
$config = new ConfigObjects([
    'host' => 'http://localhost:5000',
    'bearer' => 'dev-key',
    'preferredModel' => 'llama-2-7b-chat',
    'debug' => true
]);
```

**Production Environment:**
```php
$config = new ConfigObjects([
    'host' => 'https://api.openai.com',
    'bearer' => getenv('OPENAI_API_KEY'),
    'preferredModel' => 'gpt-4',
    'timeout' => 60,
    'retry_attempts' => 3
]);
```

### Configuration Best Practices

**1. Environment Variables:**
```php
$config = new ConfigObjects([
    'bearer' => getenv('OPENAI_API_KEY') ?: '',
    'host' => getenv('LLM_ENDPOINT') ?: 'https://api.openai.com',
    'preferredModel' => getenv('LLM_MODEL') ?: 'gpt-4o'
]);
```

**2. Configuration Inheritance:**
```php
$baseConfig = new ConfigObjects('config.base.json');
$envConfig = new ConfigObjects('config.production.json');
$mergedConfig = array_merge($baseConfig->getFullConfigData(), $envConfig->getFullConfigData());
```

**3. Validation:**
```php
$config = new ConfigObjects($configData);
if (!$config->configKeyExists('bearer') || empty($config->getConfigKey('bearer'))) {
    throw new InvalidArgumentException('API bearer token is required');
}
```

### Security Considerations

**1. API Key Management:**
- Never commit API keys to version control
- Use environment variables for sensitive data
- Implement key rotation strategies
- Consider using key management services

**2. Endpoint Security:**
- Validate SSL certificates in production
- Use HTTPS endpoints exclusively
- Implement request timeout limits
- Consider IP whitelisting for local endpoints

**3. Configuration Access:**
- Restrict file permissions on config files
- Use encrypted configuration for sensitive data
- Implement audit logging for configuration changes
- Consider configuration signing for integrity

---

## Tool System

### Tool Interface and Implementation

**Standard Tool Structure:**
```php
class MyTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_tool';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'my_tool',
                'description' => 'Description of what this tool does',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param1' => [
                            'type' => 'string',
                            'description' => 'Description of parameter 1'
                        ]
                    ],
                    'required' => ['param1']
                ]
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        return isset($arguments['param1']) && is_string($arguments['param1']);
    }

    public function execute(array $arguments, $configuration): array
    {
        // Tool implementation logic
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Tool execution result'
                ]
            ],
            'isError' => false
        ];
    }
}
```

### Built-in Tools Analysis

**AdvanceSearchTool**
- **Purpose**: Advanced web search with content summarization
- **Features**: SearXNG integration, content fetching, LLM summarization
- **Dependencies**: Guzzle HTTP client, external search endpoint
- **Use Case**: Research automation and content aggregation

**WebPageToMarkdownTool**
- **Purpose**: Convert web pages to Markdown format
- **Features**: HTML cleaning, Markdown conversion, content extraction
- **Dependencies**: League HTML to Markdown library
- **Use Case**: Web content processing and documentation generation

**GetCurrentDateTimeTool**
- **Purpose**: Get current date and time information
- **Features**: Timezone support, formatted output
- **Dependencies**: PHP datetime functions
- **Use Case**: Time-sensitive operations and scheduling

**GetRedditHot**
- **Purpose**: Fetch hot posts from Reddit
- **Features**: Reddit API integration, JSON parsing
- **Dependencies**: WebPageToMarkdownTool for content fetching
- **Use Case**: Social media monitoring and content aggregation

**SearchTool**
- **Purpose**: Basic web search functionality
- **Features**: Simple search interface, result processing
- **Dependencies**: External search service
- **Use Case**: Quick information lookup and fact checking

### Custom Tool Development Guide

**Step 1: Create Tool Class**
```php
<?php
namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;

class CustomTool implements ToolInterface
{
    // Implement required methods
}
```

**Step 2: Define Tool Schema**
```php
public function getDefinition(): array
{
    return [
        'type' => 'function',
        'function' => [
            'name' => 'custom_tool',
            'description' => 'Detailed description for LLM',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'Input parameter description'
                    ]
                ],
                'required' => ['input']
            ]
        ]
    ];
}
```

**Step 3: Implement Validation**
```php
public function validateArguments(array $arguments): bool
{
    // Validate required parameters
    if (!isset($arguments['input'])) {
        return false;
    }
    
    // Validate parameter types and values
    if (!is_string($arguments['input']) || strlen($arguments['input']) > 1000) {
        return false;
    }
    
    return true;
}
```

**Step 4: Execute Tool Logic**
```php
public function execute(array $arguments, $configuration): array
{
    try {
        // Perform tool operation
        $result = $this->performOperation($arguments['input']);
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result
                ]
            ],
            'isError' => false
        ];
    } catch (Exception $e) {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Error: " . $e->getMessage()
                ]
            ],
            'isError' => true
        ];
    }
}
```

### Tool Discovery and Management

**Automatic Discovery:**
```php
$toolManager = new ToolManager();
$toolManager->discoverTools(); // Discovers from src/Tools directory
$toolManager->discoverTools('/custom/tools/path'); // Custom directory
```

**Manual Registration:**
```php
$toolManager = new ToolManager();
$toolManager->registerTool(new CustomTool());
```

**Tool Management:**
```php
// List all tools
$tools = $toolManager->listTools();

// Enable/disable tools
$toolManager->enableTool('tool_name');
$toolManager->disableTool('tool_name');

// Check tool status
if ($toolManager->hasTool('tool_name')) {
    $definitions = $toolManager->getToolDefinitions();
}
```

---

## Plugin System

### Plugin Architecture

**Plugin Types:**
```php
enum PluginType: string {
    case GENERAL = 'General';
    case BEFORE_API_CALL = 'BeforeApiCall';
}
```

**Plugin Lifecycle:**
1. **Initialization**: Plugin registered with PluginManager
2. **Setup**: `initialize()` method called with connection instance
3. **Method Registration**: Plugin registers methods it can handle
4. **Execution**: `handleMethodCall()` invoked for registered methods
5. **Cleanup**: Plugin cleanup on connection destruction

### Built-in Plugins

**MCPClientPlugin**
- **Purpose**: Client-side MCP protocol implementation
- **Features**: JSON-RPC 2.0 communication, tool discovery, method calling
- **Methods**: `initialize`, `tools/list`, `tools/call`
- **Use Case**: Connecting to MCP servers and consuming tools

**MCPServerPlugin**
- **Purpose**: Server-side MCP protocol implementation
- **Features**: Tool exposure, method handling, protocol compliance
- **Methods**: `tools/list`, `tools/call`
- **Use Case**: Exposing local tools via MCP protocol

**SelfDefiningFunctionsPlugin**
- **Purpose**: Dynamic function definition and registration
- **Features**: Runtime function creation, flexible tool registration
- **Use Case**: Dynamic tool generation and runtime customization

### Custom Plugin Development

**Step 1: Implement PluginInterface**
```php
class CustomPlugin implements PluginInterface
{
    private ?OpenAICompatibleEndpointConnection $connection = null;
    
    public function getName(): string
    {
        return 'custom_plugin';
    }
    
    public function getType(): PluginType
    {
        return PluginType::GENERAL;
    }
    
    public function initialize(OpenAICompatibleEndpointConnection $connection): void
    {
        $this->connection = $connection;
        // Plugin initialization logic
    }
    
    public function canHandle(string $method): bool
    {
        return in_array($method, ['custom_method1', 'custom_method2']);
    }
    
    public function handleMethodCall(string $method, array $args): mixed
    {
        switch ($method) {
            case 'custom_method1':
                return $this->handleMethod1($args);
            case 'custom_method2':
                return $this->handleMethod2($args);
            default:
                throw new BadMethodCallException("Method $method not supported");
        }
    }
}
```

**Step 2: Register Plugin**
```php
$plugin = new CustomPlugin();
$connection->registerPlugin($plugin);
```

**Step 3: Use Plugin Methods**
```php
$result = $connection->{'custom_method1'}($args);
```

### Integration Patterns

**1. Plugin Chaining:**
```php
// Multiple plugins can handle different aspects
$connection->registerPlugin(new MCPClientPlugin());
$connection->registerPlugin(new CustomPlugin());
$connection->registerPlugin(new AnalyticsPlugin());
```

**2. Plugin Communication:**
```php
class PluginA implements PluginInterface
{
    public function handleMethodCall(string $method, array $args): mixed
    {
        // Can communicate with other plugins through connection
        $otherPlugin = $this->connection->getPluginManager()->get('plugin_b');
        return $otherPlugin->handleMethodCall('shared_method', $args);
    }
}
```

**3. Conditional Plugin Loading:**
```php
if ($configuration->getConfigKey('enable_mcp')) {
    $connection->registerPlugin(new MCPServerPlugin($toolsPath));
}

if ($configuration->getConfigKey('enable_analytics')) {
    $connection->registerPlugin(new AnalyticsPlugin());
}
```

---

## MCP Implementation

### Protocol Compliance Details

**MCP Version Support:**
- **Supported Version**: 2025-06-18
- **Protocol**: JSON-RPC 2.0
- **Transport**: HTTP/HTTPS
- **Content-Type**: application/json

**Compliance Features:**
- Standard method naming conventions
- Proper error code handling
- Capability negotiation
- Tool discovery and execution
- Streaming support where applicable

### Server and Client Implementations

**MCP Server Implementation:**
```php
// Create MCP server plugin
$mcpServer = new MCPServerPlugin(['/path/to/tools']);

// Register with connection
$connection->registerPlugin($mcpServer);

// Server automatically handles:
// - tools/list: Returns available tool definitions
// - tools/call: Executes tools with provided arguments
```

**MCP Client Implementation:**
```php
// Create MCP client plugin
$mcpClient = new MCPClientPlugin('http://localhost:8111');

// Register with connection
$connection->registerPlugin($mcpClient);

// Use client methods
$tools = $connection->{'tools/list'}([]);
$result = $connection->{'tools/call'}([
    'name' => 'tool_name',
    'arguments' => ['param' => 'value']
]);
```

### Tool Exposure and Consumption

**Tool Exposure (Server):**
```php
// Tools are automatically discovered and exposed
// Tool definitions follow MCP schema:
{
    "name": "tool_name",
    "description": "Tool description",
    "inputSchema": {
        "type": "object",
        "properties": {
            "param": {
                "type": "string",
                "description": "Parameter description"
            }
        },
        "required": ["param"]
    }
}
```

**Tool Consumption (Client):**
```php
// Client discovers available tools
$toolsList = $connection->{'tools/list'}([]);
foreach ($toolsList['tools'] as $tool) {
    echo "Available tool: " . $tool['name'];
}

// Execute tool with arguments
$result = $connection->{'tools/call'}([
    'name' => 'advance_search',
    'arguments' => [
        'query' => 'search term',
        'limit' => 5
    ]
]);
```

### JSON-RPC 2.0 Implementation

**Request Format:**
```json
{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
        "name": "tool_name",
        "arguments": {
            "param": "value"
        }
    },
    "id": 1
}
```

**Response Format:**
```json
{
    "jsonrpc": "2.0",
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Tool execution result"
            }
        ],
        "isError": false
    },
    "id": 1
}
```

**Error Handling:**
```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params"
    },
    "id": 1
}
```

---

## Usage Patterns and Examples

### Basic Usage Patterns

**Simple Query:**
```php
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$config = new ConfigObjects('config.json');
$connection = new OpenAICompatibleEndpointConnection($config);
$response = $connection->query("What is the capital of France?");
echo $response->getLlmResponse();
```

**Streaming Query:**
```php
$response = $connection->query("Explain quantum physics", function($chunk, $tps) {
    echo $chunk; // Real-time output
    echo " ({$tps} tokens/sec)\n";
});
```

### Advanced Integration Patterns

**Multi-turn Conversation:**
```php
$connection->setSystemMessage("You are a helpful assistant.");
$connection->addUserMessage("What is PHP?");
$response1 = $connection->query();

$connection->addAssistantMessage($response1->getLlmResponse());
$connection->addUserMessage("Can you give me an example?");
$response2 = $connection->query();
```

**Tool Integration:**
```php
$connection->enableToolSupport();
$connection->addToolDefinition(new AdvanceSearchTool());
$connection->addToolDefinition(new GetCurrentDateTimeTool());

$response = $connection->query("Search for latest AI news and tell me the current time");
// LLM will automatically call tools as needed
```

### Streaming Implementation

**Basic Streaming:**
```php
$response = $connection->query("Your question here", function($chunk, $tps) {
    echo $chunk;
    flush(); // Ensure immediate output
});
```

**Advanced Streaming with Metrics:**
```php
$startTime = microtime(true);
$tokenCount = 0;

$response = $connection->query("Complex question", function($chunk, $tps) use (&$tokenCount) {
    echo $chunk;
    $tokenCount++;
    
    // Show progress every 100 tokens
    if ($tokenCount % 100 === 0) {
        echo "\n[Progress: {$tokenCount} tokens, {$tps} tokens/sec]\n";
    }
});

$endTime = microtime(true);
$duration = $endTime - $startTime;
echo "\nTotal: {$tokenCount} tokens in {$duration} seconds\n";
```

### Vision/Multimodal Usage

**Image Analysis:**
```php
$imagePath = 'path/to/image.jpg';
$imageData = base64_encode(file_get_contents($imagePath));

$response = $connection->query("Describe this image", [
    'vision' => true,
    'image' => $imageData,
    'max_tokens' => 500
]);

echo $response->getLlmResponse();
```

**Multiple Images:**
```php
$images = [
    base64_encode(file_get_contents('image1.jpg')),
    base64_encode(file_get_contents('image2.jpg'))
];

$response = $connection->query("Compare these images", [
    'vision' => true,
    'images' => $images,
    'max_tokens' => 800
]);
```

### Configuration Patterns

**Environment-based Configuration:**
```php
$env = getenv('APP_ENV') ?: 'development';
$configFile = "config.{$env}.json";
$config = new ConfigObjects($configFile);
```

**Dynamic Configuration:**
```php
$config = new ConfigObjects([
    'host' => getenv('LLM_ENDPOINT'),
    'bearer' => getenv('LLM_API_KEY'),
    'preferredModel' => getenv('LLM_MODEL') ?: 'gpt-4o',
    'timeout' => (int)(getenv('LLM_TIMEOUT') ?: 30)
]);
```

**Configuration Validation:**
```php
$config = new ConfigObjects($configFile);

$required = ['host', 'preferredModel'];
foreach ($required as $key) {
    if (!$config->configKeyExists($key)) {
        throw new InvalidArgumentException("Missing required config: {$key}");
    }
}
```

---

## Performance and Optimization

### Benchmarking Capabilities

**Built-in Benchmarking:**
```php
// Enable query statistics
$connection->enableStats();

// Execute query
$response = $connection->query("Your question here");

// Get performance metrics
$stats = $connection->getQueryStats();
echo "Tokens per second: " . $connection->getCurrentTokensPerSecond();
echo "Query time: " . $stats['query_time'] . " seconds";
```

**Custom Benchmarking:**
```php
$startTime = microtime(true);
$startMemory = memory_get_usage();

$response = $connection->query($query);

$endTime = microtime(true);
$endMemory = memory_get_usage();

$queryTime = $endTime - $startTime;
memoryUsed = $endMemory - $startMemory;

echo "Query time: {$queryTime}s\n";
echo "Memory used: " . (memoryUsed / 1024 / 1024) . "MB\n";
```

### Performance Optimization Techniques

**1. Connection Pooling:**
```php
class ConnectionPool
{
    private static $connections = [];
    
    public static function getConnection(string $configKey): OpenAICompatibleEndpointConnection
    {
        if (!isset(self::$connections[$configKey])) {
            $config = new ConfigObjects("config.{$configKey}.json");
            self::$connections[$configKey] = new OpenAICompatibleEndpointConnection($config);
        }
        
        return self::$connections[$configKey];
    }
}
```

**2. Response Caching:**
```php
class ResponseCache
{
    private static $cache = [];
    
    public static function get(string $key): ?string
    {
        return self::$cache[$key] ?? null;
    }
    
    public static function set(string $key, string $value, int $ttl = 3600): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
}
```

**3. Batch Processing:**
```php
$queries = ["Question 1", "Question 2", "Question 3"];
$responses = [];

foreach ($queries as $query) {
    $response = $connection->query($query);
    $responses[] = $response->getLlmResponse();
    
    // Add delay to prevent rate limiting
    usleep(100000); // 0.1 second
}
```

### Best Practices for Production

**1. Error Handling:**
```php
try {
    $response = $connection->query($query);
} catch (GuzzleException $e) {
    // Handle network errors
    error_log("Network error: " . $e->getMessage());
    $response = null;
} catch (Exception $e) {
    // Handle other errors
    error_log("General error: " . $e->getMessage());
    $response = null;
}
```

**2. Rate Limiting:**
```php
class RateLimiter
{
    private $requests = [];
    private $maxRequests = 60;
    private $timeWindow = 60;
    
    public function canMakeRequest(): bool
    {
        $now = time();
        $this->requests = array_filter($this->requests, function($time) use ($now) {
            return $now - $time < $this->timeWindow;
        });
        
        if (count($this->requests) >= $this->maxRequests) {
            return false;
        }
        
        $this->requests[] = $now;
        return true;
    }
}
```

**3. Resource Management:**
```php
// Set appropriate timeouts
$connection->setConnectionTimeout(30);
$connection->setStreamReadTimeout(300);

// Monitor memory usage
if (memory_get_usage() > 256 * 1024 * 1024) { // 256MB
    // Clear conversation history
    $connection->clear();
}
```

---

## Development Guidelines

### Best Practices for Development

**1. Code Organization:**
```
src/
├── Configuration/
│   ├── ConfigManager.php
│   └── ConfigObjects.php
├── Connections/
│   ├── Definitions/
│   └── Traits/
├── Core/
│   ├── PluginInterface.php
│   ├── PluginManager.php
│   ├── Request.php
│   ├── Response.php
│   └── RolesManager.php
├── Plugins/
│   ├── MCPClientPlugin.php
│   ├── MCPServerPlugin.php
│   └── SelfDefiningFunctionsPlugin.php
└── Tools/
    ├── Interfaces/
    ├── Tool1.php
    ├── Tool2.php
    └── Tool3.php
```

**2. Naming Conventions:**
- Classes: PascalCase (e.g., `OpenAICompatibleEndpointConnection`)
- Methods: camelCase (e.g., `getQueryStats`)
- Constants: UPPER_SNAKE_CASE (e.g., `DEFAULT_TIMEOUT`)
- Files: PascalCase.php (e.g., `ConfigManager.php`)

**3. Documentation Standards:**
```php
/**
 * Brief description of the class
 *
 * Detailed description explaining the purpose and usage
 *
 * @package Viceroy\Package
 * @author Your Name
 * @since 1.0.0
 *
 * @method ReturnType methodName($param1, $param2) Description of method
 * @property Type $propertyName Description of property
 */
class ClassName
{
    /**
     * Method description
     *
     * @param Type $param1 Parameter description
     * @param Type $param2 Parameter description
     * @return ReturnType Return value description
     * @throws ExceptionType Exception description
     *
     * @example
     * $result = $obj->methodName($value1, $value2);
     */
    public function methodName($param1, $param2): ReturnType
    {
        // Implementation
    }
}
```

### Common Pitfalls and How to Avoid Them

**1. Memory Leaks:**
```php
// BAD: Accumulating conversation history indefinitely
foreach ($queries as $query) {
    $connection->addUserMessage($query);
    $response = $connection->query();
    $connection->addAssistantMessage($response->getLlmResponse());
}

// GOOD: Clear history periodically
$counter = 0;
foreach ($queries as $query) {
    $connection->addUserMessage($query);
    $response = $connection->query();
    $connection->addAssistantMessage($response->getLlmResponse());
    
    if (++$counter % 10 === 0) {
        $connection->clear();
    }
}
```

**2. Blocking Operations:**
```php
// BAD: Synchronous operations in streaming context
$connection->query("Long query", function($chunk, $tps) {
    file_get_contents('http://slow-api.com'); // Blocks streaming
    echo $chunk;
});

// GOOD: Asynchronous or non-blocking operations
$connection->query("Long query", function($chunk, $tps) {
    // Use non-blocking operations or queue for later processing
    $this->processChunkAsync($chunk);
    echo $chunk;
});
```

**3. Configuration Errors:**
```php
// BAD: No validation
$config = new ConfigObjects($configFile);
$connection = new OpenAICompatibleEndpointConnection($config);

// GOOD: Validate configuration
$config = new ConfigObjects($configFile);
if (!$config->configKeyExists('bearer') || empty($config->getConfigKey('bearer'))) {
    throw new InvalidArgumentException('API bearer token is required');
}
$connection = new OpenAICompatibleEndpointConnection($config);
```

### Testing Strategies

**1. Unit Testing:**
```php
class ToolManagerTest extends PHPUnit\Framework\TestCase
{
    private ToolManager $toolManager;
    
    protected function setUp(): void
    {
        $this->toolManager = new ToolManager();
    }
    
    public function testToolRegistration(): void
    {
        $tool = $this->createMock(ToolInterface::class);
        $tool->method('getName')->willReturn('test_tool');
        
        $this->toolManager->registerTool($tool);
        
        $this->assertTrue($this->toolManager->hasTool('test_tool'));
    }
}
```

**2. Integration Testing:**
```php
class ConnectionIntegrationTest extends PHPUnit\Framework\TestCase
{
    public function testQueryWithRealAPI(): void
    {
        $config = new ConfigObjects(['host' => 'http://test-api', 'bearer' => 'test-key']);
        $connection = new OpenAICompatibleEndpointConnection($config);
        
        $response = $connection->query('Test question');
        
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->getLlmResponse());
    }
}
```

**3. Performance Testing:**
```php
class PerformanceTest extends PHPUnit\Framework\TestCase
{
    public function testQueryPerformance(): void
    {
        $connection = new OpenAICompatibleEndpointConnection($config);
        $connection->enableStats();
        
        $startTime = microtime(true);
        $response = $connection->query('Performance test query');
        $endTime = microtime(true);
        
        $queryTime = $endTime - $startTime;
        $this->assertLessThan(5.0, $queryTime, 'Query should complete within 5 seconds');
    }
}
```

### Code Organization

**1. Separation of Concerns:**
- Keep configuration logic separate from business logic
- Separate HTTP handling from LLM-specific functionality
- Isolate tool execution from connection management

**2. Dependency Injection:**
```php
class MyClass
{
    private ToolManager $toolManager;
    private ConfigObjects $config;
    
    public function __construct(ToolManager $toolManager, ConfigObjects $config)
    {
        $this->toolManager = $toolManager;
        $this->config = $config;
    }
}
```

**3. Interface Segregation:**
```php
// Small, focused interfaces
interface ToolInterface
{
    public function getName(): string;
    public function getDefinition(): array;
    public function execute(array $arguments, $configuration): array;
    public function validateArguments(array $arguments): bool;
}

interface PluginInterface
{
    public function getName(): string;
    public function getType(): PluginType;
    public function initialize(OpenAICompatibleEndpointConnection $connection): void;
    public function canHandle(string $method): bool;
    public function handleMethodCall(string $method, array $args): mixed;
}
```

---

## API Reference Summary

### Key Classes and Their Purposes

**OpenAICompatibleEndpointConnection**
- **Purpose**: Main connection class for OpenAI-compatible APIs
- **Key Methods**: `query()`, `queryPost()`, `addToolDefinition()`, `setParameter()`
- **Usage**: Primary interface for LLM interaction

**ConfigManager**
- **Purpose**: Central configuration management
- **Key Methods**: `getJsonPrompt()`, `getConfigKey()`
- **Usage**: Configuration access and prompt processing

**ConfigObjects**
- **Purpose**: Configuration data container
- **Key Methods**: `getConfigKey()`, `getFullConfigData()`, `readConfigFile()`
- **Usage**: Configuration storage and retrieval

**ToolManager**
- **Purpose**: Tool discovery and execution management
- **Key Methods**: `discoverTools()`, `executeTool()`, `registerTool()`
- **Usage**: Tool lifecycle management

**ToolRegistry**
- **Purpose**: Tool storage and management
- **Key Methods**: `registerTool()`, `getToolDefinitions()`, `enableTool()`
- **Usage**: Tool registration and discovery

**PluginManager**
- **Purpose**: Plugin registration and management
- **Key Methods**: `add()`, `get()`, `getAll()`
- **Usage**: Plugin lifecycle management

**Response**
- **Purpose**: Response processing and content extraction
- **Key Methods**: `getLlmResponse()`, `getThinkContent()`, `wasStreamed()`
- **Usage**: Response content access and processing

**RolesManager**
- **Purpose**: Conversation history and role management
- **Key Methods**: `addUserMessage()`, `addAssistantMessage()`, `getMessages()`
- **Usage**: Multi-turn conversation handling

### Important Methods and Their Usage

**Connection Methods:**
```php
// Basic query
$response = $connection->query("Question");

// Streaming query
$response = $connection->query("Question", function($chunk, $tps) {
    echo $chunk;
});

// Tool management
$connection->enableToolSupport();
$connection->addToolDefinition($tool);

// Parameter setting
$connection->setParameter('temperature', 0.7);
$connection->setSystemMessage("System message");

// Configuration
$connection->setConnectionTimeout(30);
$connection->setApiKey($apiKey);
```

**Tool Methods:**
```php
// Tool discovery
$toolManager->discoverTools();

// Tool execution
$result = $toolManager->executeTool('tool_name', $arguments, $config);

// Tool management
$toolManager->registerTool($tool);
$toolManager->enableTool('tool_name');
$definitions = $toolManager->getToolDefinitions();
```

**Configuration Methods:**
```php
// Configuration access
$value = $config->getConfigKey('key');
$allConfig = $config->getFullConfigData();

// Configuration validation
if ($config->configKeyExists('required_key')) {
    // Use configuration
}
```

### Integration Points

**1. Tool Integration:**
- Implement `ToolInterface` for custom tools
- Register tools with `ToolManager`
- Tools automatically discovered by MCP plugins

**2. Plugin Integration:**
- Implement `PluginInterface` for custom plugins
- Register plugins with connection
- Plugins can handle custom methods

**3. Configuration Integration:**
- Extend `ConfigObjects` for custom configuration
- Use `ConfigManager` for prompt processing
- Environment-specific configuration support

**4. Connection Integration:**
- Extend `OpenAICompatibleEndpointConnection` for custom endpoints
- Use traits for shared functionality
- Proxy pattern for method forwarding

**5. MCP Integration:**
- Tools automatically exposed via MCP server
- Client plugin for consuming external MCP services
- JSON-RPC 2.0 compliance for interoperability

---

## Conclusion

The Viceroy LLM Library provides a comprehensive, extensible framework for integrating Large Language Models into PHP applications. With its modular architecture, robust tool system, and MCP protocol support, it offers developers a powerful foundation for building AI-powered applications.

Key strengths include:
- **Flexibility**: Support for multiple LLM providers and endpoints
- **Extensibility**: Plugin and tool systems for custom functionality
- **Standards Compliance**: MCP protocol implementation for interoperability
- **Performance**: Streaming support and optimization features
- **Developer Experience**: Comprehensive documentation and examples

This documentation serves as a complete reference for understanding, implementing, and extending the Viceroy library for various use cases and integration scenarios.
