<?php

namespace Viceroy;

use Viceroy\Tools\Interfaces\ToolInterface;

class ToolManager
{
    private ToolRegistry $registry;
    private array $legacyTools = [];

    public function __construct()
    {
        $this->registry = new ToolRegistry();
    }

    /**
     * Discover and register tools from the Tools directory
     */
    public function discoverTools(?string $toolsDirectory = null): void
    {
        $directory = $toolsDirectory ?? __DIR__ . '/Tools';
        
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'Interfaces') {
                continue;
            }

            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = pathinfo($file, PATHINFO_FILENAME);
                $fullClassName = "Viceroy\\Tools\\{$className}";
                
                if (class_exists($fullClassName) && is_subclass_of($fullClassName, ToolInterface::class)) {
                    $tool = new $fullClassName();
                    $this->registry->registerTool($tool);
                }
            }
        }
    }

    /**
     * Register a tool instance
     */
    public function registerTool(ToolInterface $tool): void
    {
        $this->registry->registerTool($tool);
    }

    /**
     * Register a legacy tool (for backward compatibility)
     */
    public function registerLegacyTool(string $name, array $definition, callable $executor): void
    {
        $this->legacyTools[$name] = [
            'definition' => $definition,
            'executor' => $executor
        ];
    }

    /**
     * Get all tool definitions (both modular and legacy)
     */
    public function getToolDefinitions(): array
    {
        $modularDefinitions = $this->registry->getToolDefinitions();
        $legacyDefinitions = array_column($this->legacyTools, 'definition');
        
        return array_merge($modularDefinitions, $legacyDefinitions);
    }

    /**
     * Execute a tool by name
     */
    public function executeTool(string $name,  $arguments, $configuration): array {
        // DEBUG: Log incoming arguments to ToolManager
        error_log("DEBUG: ToolManager.executeTool() received - Name: '$name', Arguments: '$arguments', Arguments type: " . gettype($arguments));
        
        // First try modular tools
        $tool = $this->registry->getTool($name);
        if ($tool !== null) {
            if (!$this->registry->isToolEnabled($name)) {
                throw new \RuntimeException("Tool '{$name}' is disabled");
            }

            // DEBUG: Log before JSON decoding
            error_log("DEBUG: About to JSON decode arguments: '$arguments'");
            
            $argumentsDecoded = json_decode($arguments, true);
            
            // DEBUG: Log after JSON decoding
            error_log("DEBUG: JSON decode result: " . json_encode($argumentsDecoded, JSON_PRETTY_PRINT) . " (JSON error: " . json_last_error_msg() . ")");

            if (!$tool->validateArguments($argumentsDecoded)) {
                throw new \InvalidArgumentException("Invalid arguments for tool '{$name}'");
            }
            
            // DEBUG: Log before tool execution
            error_log("DEBUG: About to execute tool '$name' with decoded arguments: " . json_encode($argumentsDecoded, JSON_PRETTY_PRINT));
            
            $result = $tool->execute($argumentsDecoded, $configuration);
            
            // DEBUG: Log tool execution result
            error_log("DEBUG: Tool execution result: " . json_encode($result, JSON_PRETTY_PRINT));
            
            return $result;
        }

        // Then try legacy tools
        if (isset($this->legacyTools[$name])) {
            $executor = $this->legacyTools[$name]['executor'];
            return $executor($arguments);
        }

        throw new \RuntimeException("Tool '{$name}' not found");
    }
    

    /**
     * Check if a tool exists
     */
    public function hasTool(string $name): bool
    {
        return $this->registry->hasTool($name) || isset($this->legacyTools[$name]);
    }

    /**
     * Get all registered tool names
     */
    public function getToolNames(): array
    {
        $modularNames = array_keys($this->registry->getAllTools());
        $legacyNames = array_keys($this->legacyTools);
        
        return array_merge($modularNames, $legacyNames);
    }

    /**
     * Migrate legacy tools to modular format
     */
    public function migrateLegacyTools(): array
    {
        $migrationResults = [];
        
        foreach ($this->legacyTools as $name => $legacyTool) {
            $migrationResults[$name] = [
                'success' => false,
                'message' => 'Manual migration required - legacy tools need to be converted to ToolInterface implementations'
            ];
        }
        
        return $migrationResults;
    }

    /**
     * List all tools with their status (enabled/disabled)
     */
    public function listTools(): array
    {
        $tools = [];
        
        // Get modular tools
        foreach ($this->registry->getAllTools() as $name => $tool) {
            $tools[$name] = [
                'name' => $name,
                'description' => $tool->getDefinition()['description'] ?? '',
                'enabled' => $this->registry->isToolEnabled($name),
                'type' => 'modular'
            ];
        }
        
        // Get legacy tools (always enabled for backward compatibility)
        foreach ($this->legacyTools as $name => $legacyTool) {
            $tools[$name] = [
                'name' => $name,
                'description' => $legacyTool['definition']['description'] ?? '',
                'enabled' => true, // Legacy tools are always enabled
                'type' => 'legacy'
            ];
        }
        
        return $tools;
    }

    /**
     * Enable a tool by name
     */
    public function enableTool(string $name): void
    {
        if ($this->registry->hasTool($name)) {
            $this->registry->enableTool($name);
        } elseif (isset($this->legacyTools[$name])) {
            // Legacy tools are always enabled, no action needed
        } else {
            throw new \RuntimeException("Tool '{$name}' not found");
        }
    }

    /**
     * Disable a tool by name
     */
    public function disableTool(string $name): void
    {
        if ($this->registry->hasTool($name)) {
            $this->registry->disableTool($name);
        } elseif (isset($this->legacyTools[$name])) {
            // Legacy tools cannot be disabled for backward compatibility
            throw new \RuntimeException("Legacy tool '{$name}' cannot be disabled");
        } else {
            throw new \RuntimeException("Tool '{$name}' not found");
        }
    }

    /**
     * Check if a tool is enabled
     */
    public function isToolEnabled(string $name): bool
    {
        if ($this->registry->hasTool($name)) {
            return $this->registry->isToolEnabled($name);
        } elseif (isset($this->legacyTools[$name])) {
            return true; // Legacy tools are always enabled
        }
        
        return false;
    }
}
