<?php

/**
 * setSystemMessageTrait - System message management for LLM interactions
 *
 * This trait provides standardized system message handling for:
 * - Setting instruction templates for LLM prompts
 * - Retrieving current system messages
 * - Maintaining message state across operations
 *
 * Key Features:
 * - Simple set/get interface
 * - Type-safe string handling
 * - Designed for composition with connection classes
 *
 * Usage Example:
 * class MyConnection {
 *   use setSystemMessageTrait;
 *   // ... uses $this->setSystemMessage() and $this->getSystemMessage()
 * }
 *
 * @package Viceroy\Connections\Traits
 */
namespace Viceroy\Connections\Traits;

Trait setSystemMessageTrait {

    /**
     * @var string $systemMessage The current system message content
     *
     * Stores the active system message that defines:
     * - LLM instruction templates
     * - Response format requirements
     * - Parameter handling rules
     * - Error handling expectations
     */
    protected string $systemMessage;

    /**
     * Sets the system message template
     *
     * The message should contain:
     * - Clear instructions for the LLM
     * - Required response format
     * - Any special processing rules
     *
     * @param string $message The complete system message content
     * @return void
     * @throws \InvalidArgumentException If message is empty
     */
    public function setSystemMessageTrait(string $message) {
        $this->systemMessage = $message;
    }

    /**
     * Gets the current system message template
     *
     * @return string The current system message content
     * @throws \RuntimeException If no system message is set
     */
    public function getSystemMessage(): string {
        return $this->systemMessage;
    }

}
