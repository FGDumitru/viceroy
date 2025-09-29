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
    public function executeTool(string $name, array $arguments): array
    {
        // First try modular tools
        $tool = $this->registry->getTool($name);
        if ($tool !== null) {
            if (!$tool->validateArguments($arguments)) {
                throw new \InvalidArgumentException("Invalid arguments for tool '{$name}'");
            }
            return $tool->execute($arguments);
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
}
