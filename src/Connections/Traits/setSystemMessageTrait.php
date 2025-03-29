<?php

/**
 * setSystemMessageTrait - Provides system message handling functionality
 * 
 * This trait enables classes to manage system messages for LLM interactions.
 */
namespace Viceroy\Connections\Traits;

Trait setSystemMessageTrait {

    /**
     * @var string $systemMessage The system message content
     */
    protected string $systemMessage;

    /**
     * Sets the system message
     *
     * @param string $message The system message content
     * @return void
     */
    public function setSystemMessageTrait(string $message) {
        $this->systemMessage = $message;
    }

    /**
     * Gets the current system message
     *
     * @return string The current system message content
     */
    public function getSystemMessage(): string {
        return $this->systemMessage;
    }

}
