<?php

namespace Viceroy\Plugins;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Core\PluginInterface;
use Viceroy\Core\PluginType;
use Viceroy\ToolManager;
use Viceroy\Tools\Interfaces\ToolInterface;

/**
 * MCPServerPlugin - Plugin for Model Context Protocol server functionality
 * Transforms the mcp_simple_server.php example into a Viceroy library plugin
 */
class MCPServerPlugin implements PluginInterface
{
    private ?OpenAICompatibleEndpointConnection $connection = null;
    private ToolManager $toolManager;
    private array $toolsDirectories;
    private array $methods = [];

    /**
     * Constructor accepts a tools directory path for tool discovery
     */
    public function __construct($toolsDirectories)
    {
        // Handle backward compatibility: allow single string or array of strings
        if (is_string($toolsDirectories)) {
            $this->toolsDirectories = [$toolsDirectories];
        } elseif (is_array($toolsDirectories)) {
            $this->toolsDirectories = $toolsDirectories;
        } else {
            throw new \InvalidArgumentException('Tools directories must be a string or an array of strings');
        }
        
        $this->toolManager = new ToolManager();

        // Register MCP methods
        $this->registerMethod('tools/list');
        $this->registerMethod('tools/call');
    }

    public function getName(): string
    {
        return 'mcp_server';
    }

    public function getType(): PluginType
    {
        return PluginType::GENERAL;
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void
    {
        $this->connection = $connection;
        $this->discoverTools();
    }

    public function canHandle(string $method): bool
    {
        return in_array($method, $this->methods);
    }

    public function handleMethodCall(string $method, array $args): mixed
    {
        if (!$this->canHandle($method)) {
            throw new \BadMethodCallException("Method $method is not registered");
        }

        $params = $args[0] ?? [];

        switch ($method) {
            case 'tools/list':
                return $this->handleToolsList($params);
            case 'tools/call':
                return $this->handleToolsCall($params);
            default:
                throw new \BadMethodCallException("Method $method is not implemented");
        }
    }

    /**
     * Register a new MCP method
     */
    private function registerMethod(string $method): void
    {
        if (!in_array($method, $this->methods)) {
            $this->methods[] = $method;
        }
    }

    /**
     * Discover and register tools from the specified tools directory
     * Similar to ToolManager::discoverTools() but adapted for plugin context
     */
    private function discoverTools(): void
    {
        foreach ($this->toolsDirectories as $directory) {
            if (!is_dir($directory)) {
                // Log warning but continue with other directories
                error_log("Warning: Tools directory does not exist: {$directory}");
                continue;
            }

            $files = scandir($directory);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'Interfaces') {
                    continue;
                }

                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $className = pathinfo($file, PATHINFO_FILENAME);
                    $fullClassName = $this->getFullClassName($className, $directory);
                    
                    if (class_exists($fullClassName) && is_subclass_of($fullClassName, ToolInterface::class)) {
                        $tool = new $fullClassName();
                        $this->toolManager->registerTool($tool);
                    }
                }
            }
        }
    }

    /**
     * Determine the full class name based on file structure
     */
    private function getFullClassName(string $className, string $directory): string
    {
        // Check if the tools directory is under the Viceroy namespace structure
        $realPath = realpath($directory);
        $srcPath = realpath(__DIR__ . '/../');
        
        if ($realPath && strpos($realPath, $srcPath) === 0) {
            // Tools are in the Viceroy\Tools namespace
            return "Viceroy\\Tools\\{$className}";
        }
        
        // Fallback: assume global namespace or custom namespace
        return $className;
    }

    /**
     * Handle tools/list method - return tool definitions in MCP format
     */
    private function handleToolsList(array $params): array
    {
        $cursor = $params['cursor'] ?? null;
        
        // For simplicity, we return all tools at once without pagination
        // In a real implementation, you might handle cursor-based pagination
        $tools = $this->toolManager->getToolDefinitions();
        
        return [
            'tools' => array_values($tools),
            'nextCursor' => null
        ];
    }

    /**
     * Handle tools/call method - execute tools using ToolManager
     */
    private function handleToolsCall(array $params): array
    {
        if (!isset($params['name']) || !is_string($params['name'])) {
            throw new \InvalidArgumentException("Tool name is required and must be a string");
        }

        if (!isset($params['arguments']) || !is_array($params['arguments'])) {
            throw new \InvalidArgumentException("Tool arguments are required and must be an array");
        }

        $toolName = $params['name'];
        $arguments = $params['arguments'];

        try {
            $result = $this->toolManager->executeTool($toolName, $arguments);
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error' => [
                    'code' => -32603,
                    'message' => 'Tool execution failed: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get the ToolManager instance for testing and management purposes
     */
    public function getToolManager(): ToolManager
    {
        return $this->toolManager;
    }
}
