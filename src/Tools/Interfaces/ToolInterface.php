<?php

namespace Viceroy\Tools\Interfaces;

interface ToolInterface
{
    /**
     * Get the unique name of the tool
     */
    public function getName(): string;

    /**
     * Get the tool definition for MCP protocol
     */
    public function getDefinition(): array;

    /**
     * Execute the tool with provided arguments
     */
    public function execute(array $arguments, $configuration): array;

    /**
     * Validate the arguments before execution
     */
    public function validateArguments(array $arguments): bool;
}
