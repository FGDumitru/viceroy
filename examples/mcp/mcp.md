# Model Context Protocol (MCP) Implementation in Viceroy

## Overview

The Model Context Protocol (MCP) implementation in Viceroy provides a robust framework for tool discovery, execution, and communication between client and server components. This implementation follows the MCP 2025-06-18 specification and enables LLMs to interact with external tools and services through a standardized protocol.

The MCP system allows for dynamic tool discovery, execution, and management, making it possible to extend the capabilities of LLMs with external functionality without requiring retraining or model modifications.

## Architecture

### Core Components

#### 1. MCPServerPlugin (`src/Plugins/MCPServerPlugin.php`)

The `MCPServerPlugin` is the server-side implementation of the MCP protocol. It provides the core functionality for:

- Handling MCP method calls (`tools/list`, `tools/call`)
- Managing tool discovery and registration
- Communicating with clients through JSON-RPC 2.0
- Supporting both modular and legacy tools
- Providing a plugin interface for integration with the Viceroy framework

Key features:
- Implements the `PluginInterface` for seamless integration with Viceroy's plugin system
- Supports multiple tools directories for flexible tool management
- Provides tool discovery through directory scanning
- Handles JSON-RPC 2.0 protocol compliance
- Manages tool execution through the `ToolManager`

#### 2. MCPClientPlugin (`src/Plugins/MCPClientPlugin.php`)

The `MCPClientPlugin` provides the client-side implementation for interacting with MCP servers. It enables:

- Communication with MCP servers via HTTP/HTTPS
- Tool discovery and listing
- Tool execution with proper argument validation
- Error handling and response parsing
- JSON-RPC 2.0 client implementation
- Integration with Viceroy's connection system

Key features:
- Implements the `PluginInterface` for Viceroy integration
- Uses GuzzleHttp for HTTP communication
- Supports configurable server base URLs
- Provides convenience methods for common MCP operations
- Handles request/response lifecycle with proper error handling

#### 3. ToolManager (`src/ToolManager.php`)

The `ToolManager` serves as the central hub for tool management, providing:

- Tool discovery and registration mechanisms
- Support for both modular and legacy tools
- Unified interface for tool execution
- Tool status management (enabled/disabled)
- Tool listing and information retrieval
- Migration support for legacy tools

Key features:
- Integrates with `ToolRegistry` for modular tool management
- Supports backward compatibility with legacy tools
- Provides comprehensive tool lifecycle management
- Offers methods for enabling/disabling tools
- Handles tool validation and execution

#### 4. ToolRegistry (`src/ToolRegistry.php`)

The `ToolRegistry` is responsible for maintaining the registry of available tools:

- Stores tool instances with their names as keys
- Manages tool enable/disable status
- Provides methods for tool retrieval and listing
- Supports tool validation and filtering
- Maintains tool definitions for MCP protocol compliance

Key features:
- Fast lookup of tools by name
- Status tracking for each tool
- Support for enabled/disabled tool filtering
- Clear and efficient data structures

#### 5. ToolInterface (`src/Tools/Interfaces/ToolInterface.php`)

The `ToolInterface` defines the contract that all tools must implement:

- `getName()`: Returns the unique name of the tool
- `getDefinition()`: Returns the tool definition in MCP format
- `execute()`: Executes the tool with provided arguments
- `validateArguments()`: Validates arguments before execution

This interface ensures consistency across all tools in the system.

### Example Implementations

#### 1. Simple Server (`examples/mcp/mcp_simple_server.php`)

The `mcp_simple_server.php` demonstrates a basic MCP server implementation that:

- Auto-discovers tools from a specified directory
- Implements the MCP 2025-06-18 specification
- Provides basic tool discovery and execution
- Uses PHP's built-in web server capabilities
- Shows how to handle JSON-RPC 2.0 requests

#### 2. Client Example (`examples/mcp/mcp_client_plugin_example.php`)

The `mcp_client_plugin_example.php` demonstrates client-side usage by:

- Creating and configuring an MCP client plugin
- Fetching server capabilities
- Listing available tools
- Executing tools with various arguments
- Handling responses and errors appropriately

## Detailed Component Descriptions

### MCPServerPlugin Architecture

The `MCPServerPlugin` is designed to be a plugin that can be registered with Viceroy's connection system. It implements the `PluginInterface` and provides:

1. **Initialization**: The `initialize()` method sets up the connection and discovers tools from configured directories
2. **Method Handling**: The `handleMethodCall()` method routes incoming MCP method calls to appropriate handlers
3. **Tool Discovery**: The `discoverTools()` method scans directories for PHP files that implement the `ToolInterface`
4. **MCP Protocol Compliance**: The plugin handles the following methods:
   - `tools/list`: Returns tool definitions in MCP format
   - `tools/call`: Executes tools with provided arguments

### MCPClientPlugin Architecture

The `MCPClientPlugin` provides a client-side interface for communicating with MCP servers:

1. **HTTP Communication**: Uses GuzzleHttp for reliable HTTP communication
2. **Method Registration**: Pre-registers supported MCP methods for proper handling
3. **Request/Response Handling**: Manages JSON-RPC 2.0 request/response lifecycle
4. **Convenience Methods**: Provides high-level methods for common operations:
   - `getServerCapabilities()`: Fetches server capabilities
   - `listTools()`: Lists available tools
   - `executeTool()`: Executes a specific tool

### Tool Management System

The tool management system is designed to support both modular and legacy tools:

1. **Modular Tools**: Tools that implement the `ToolInterface` and are registered through the `ToolRegistry`
2. **Legacy Tools**: Backward compatibility support for older tool implementations
3. **Tool Discovery**: Automatic discovery of tools from configured directories
4. **Tool Lifecycle**: Support for enabling/disabling tools and managing their status

### Tool Interface Implementation

All tools must implement the `ToolInterface` which defines four essential methods:

1. **getName()**: Returns a unique string identifier for the tool
2. **getDefinition()**: Returns the tool definition in MCP protocol format, including:
   - Tool name and title
   - Description
   - Input schema (JSON Schema for arguments)
   - Output schema (JSON Schema for results)
3. **validateArguments()**: Validates that provided arguments conform to the tool's input schema
4. **execute()**: Executes the tool's functionality with the provided arguments

## Usage Patterns

### Server Setup

```php
// Create and register the MCP server plugin
$mcpServer = new MCPServerPlugin('/path/to/tools/directory');
$connection->registerPlugin($mcpServer);
```

### Client Usage

```php
// Create and configure the MCP client plugin
$mcpClient = new MCPClientPlugin('http://localhost:8111');
$connection->registerPlugin($mcpClient);

// Get server capabilities
$capabilities = $mcpClient->getServerCapabilities();

// List available tools
$tools = $mcpClient->listTools();

// Execute a tool
$results = $mcpClient->executeTool('Example', ['message' => 'Test message']);
```

### Tool Development

To create a new tool, implement the `ToolInterface`:

```php
class MyCustomTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'my_custom_tool',
            'title' => 'My Custom Tool',
            'description' => 'A custom tool for specific functionality',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'parameter1' => [
                        'type' => 'string',
                        'description' => 'First parameter'
                    ]
                ],
                'required' => ['parameter1']
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'result' => [
                        'type' => 'string'
                    ]
                ],
                'required' => ['result']
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        return isset($arguments['parameter1']) && is_string($arguments['parameter1']);
    }

    public function execute(array $arguments): array
    {
        $result = "Processed: " . $arguments['parameter1'];
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result
                ]
            ],
            'isError' => false
        ];
    }
}
```

## Communication Protocol

The MCP implementation uses JSON-RPC 2.0 for communication between client and server:

### Request Format

```json
{
  "jsonrpc": "2.0",
  "method": "tools/list",
  "params": {},
  "id": 1
}
```

### Response Format

```json
{
  "jsonrpc": "2.0",
  "result": {
    "tools": [
      {
        "name": "example",
        "title": "Example Tool",
        "description": "A simple example tool",
        "inputSchema": {},
        "outputSchema": {}
      }
    ],
    "nextCursor": null
  },
  "id": 1
}
```

### Error Handling

The implementation follows JSON-RPC 2.0 error codes:
- `-32600`: Invalid Request
- `-32601`: Method not found
- `-32602`: Invalid parameters
- `-32603`: Internal error

## Key Features

1. **Dynamic Tool Discovery**: Tools are automatically discovered from configured directories
2. **JSON-RPC 2.0 Compliance**: Full compliance with the JSON-RPC 2.0 specification
3. **Support for Both Modular and Legacy Tools**: Backward compatibility with existing tool implementations
4. **Comprehensive Error Handling**: Proper error handling and reporting for all operations
5. **Extensible Architecture**: Easy to extend with new tools and methods
6. **Tool Enable/Disable Management**: Control which tools are available for execution
7. **MCP Protocol Compliance**: Adheres to the MCP 2025-06-18 specification
8. **Integration with Viceroy Framework**: Seamless integration with Viceroy's plugin system

## Extensibility

### Adding New Tools

To add a new tool to the system:

1. Create a new PHP class that implements `ToolInterface`
2. Place the file in the tools directory (or a configured tools directory)
3. The `MCPServerPlugin` will automatically discover and register the tool
4. Tools can be enabled/disabled through the `ToolManager`

### Adding New Methods

To add new MCP methods:

1. Extend the `MCPServerPlugin` or `MCPClientPlugin` classes
2. Register the new method using `registerMethod()`
3. Implement the handler logic in `handleMethodCall()` for server-side
4. Add corresponding client-side methods for client-side functionality

### Custom Tool Directories

The MCP server supports multiple tools directories:

```php
$mcpServer = new MCPServerPlugin([
    '/path/to/tools/directory1',
    '/path/to/tools/directory2'
]);
```

## Integration with Viceroy Framework

The MCP implementation integrates seamlessly with Viceroy's plugin system:

1. **Plugin Registration**: MCP plugins can be registered with any Viceroy connection
2. **Connection Management**: Uses Viceroy's connection infrastructure for communication
3. **Configuration**: Leverages Viceroy's configuration system for settings
4. **Error Handling**: Integrates with Viceroy's error handling mechanisms

## Best Practices

1. **Tool Naming**: Use descriptive, unique names for tools
2. **Schema Validation**: Provide comprehensive input and output schemas
3. **Error Handling**: Implement proper error handling in tool execution
4. **Documentation**: Include clear descriptions for tools and parameters
5. **Testing**: Test tools thoroughly before deployment
6. **Security**: Validate all inputs to prevent injection attacks
7. **Performance**: Consider performance implications of tool execution

## Troubleshooting

### Common Issues

1. **Tool Not Found**: Ensure the tool file exists and implements `ToolInterface`
2. **Connection Issues**: Verify the server URL is correct and accessible
3. **Schema Validation Errors**: Check that arguments match the tool's input schema
4. **Permission Issues**: Ensure the application has read access to tool directories

### Debugging

1. Enable logging to see request/response details
2. Use the `getToolManager()` method to inspect registered tools
3. Check server capabilities to verify protocol compliance
4. Validate tool definitions using JSON Schema validators

This comprehensive MCP implementation provides a solid foundation for extending LLM capabilities through external tools while maintaining compatibility with the Model Context Protocol specification.