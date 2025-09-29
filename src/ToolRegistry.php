<?php

namespace Viceroy;

use Viceroy\Tools\Interfaces\ToolInterface;

class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];
    
    /**
     * @var array<string, bool>
     */
    private array $enabledStatus = [];

    /**
     * Register a tool with the registry
     */
    public function registerTool(ToolInterface $tool): void
    {
        $toolName = $tool->getName();
        $this->tools[$toolName] = $tool;
        $this->enabledStatus[$toolName] = true; // Default to enabled
    }

    /**
     * Get a tool by name
     */
    public function getTool(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get all registered tools
     *
     * @return array<string, ToolInterface>
     */
    public function getAllTools(): array
    {
        return $this->tools;
    }

    /**
     * Get tool definitions for MCP protocol
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];
        foreach ($this->tools as $name => $tool) {
            if ($this->isToolEnabled($name)) {
                $definitions[] = $tool->getDefinition();
            }
        }
        return $definitions;
    }

    /**
     * Check if a tool exists
     */
    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Remove a tool from the registry
     */
    public function removeTool(string $name): void
    {
        unset($this->tools[$name]);
        unset($this->enabledStatus[$name]);
    }

    /**
     * Clear all tools from the registry
     */
    public function clear(): void
    {
        $this->tools = [];
        $this->enabledStatus = [];
    }

    /**
     * Enable a tool by name
     */
    public function enableTool(string $name): void
    {
        if ($this->hasTool($name)) {
            $this->enabledStatus[$name] = true;
        }
    }

    /**
     * Disable a tool by name
     */
    public function disableTool(string $name): void
    {
        if ($this->hasTool($name)) {
            $this->enabledStatus[$name] = false;
        }
    }

    /**
     * Check if a tool is enabled
     */
    public function isToolEnabled(string $name): bool
    {
        return $this->enabledStatus[$name] ?? false;
    }

    /**
     * Get all enabled tools
     *
     * @return array<string, ToolInterface>
     */
    public function getEnabledTools(): array
    {
        $enabledTools = [];
        foreach ($this->tools as $name => $tool) {
            if ($this->isToolEnabled($name)) {
                $enabledTools[$name] = $tool;
            }
        }
        return $enabledTools;
    }
}
