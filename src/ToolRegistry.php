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
     * Register a tool with the registry
     */
    public function registerTool(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
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
        foreach ($this->tools as $tool) {
            $definitions[] = $tool->getDefinition();
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
    }

    /**
     * Clear all tools from the registry
     */
    public function clear(): void
    {
        $this->tools = [];
    }
}
