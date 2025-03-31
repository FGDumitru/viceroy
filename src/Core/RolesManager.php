<?php

/**
 * RolesManager - Conversation role and message management
 *
 * This class provides:
 * - Strict role-based message organization
 * - Conversation history tracking
 * - System/user/assistant message validation
 * - Prompt formatting for LLM consumption
 *
 * Key Features:
 * - Enforces system message as first message
 * - Validates role transitions (system -> user -> assistant)
 * - Supports method chaining for fluent interface
 * - Handles multiple prompt formats
 *
 * Architecture Role:
 * - Works with Response for complete conversation handling
 * - Integrates with Connections for message formatting
 * - Provides standardized role management
 *
 * @package Viceroy\Core
 */
namespace Viceroy\Core;

use Exception;
use stdClass;

class RolesManager {

  /**
   * @var array $roles Array of message objects with role and content
   *
   * Structure:
   * [
   *   ['role' => 'system', 'content' => '...'],
   *   ['role' => 'user', 'content' => '...'],
   *   ['role' => 'assistant', 'content' => '...']
   * ]
   *
   * Rules:
   * - System message must be first
   * - User and assistant messages alternate
   */
  private array $roles = [];

  /**
   * @var string $userRoleLable Label for user role messages
   */
  private $userRoleLable = 'user';

  /**
   * @var string $assistantRoleLable Label for assistant role messages
   */
  private $assistantRoleLable = 'assistant';

  /**
   * @throws \Exception
   */
  /**
   * Constructor
   */
  public function __construct() {
  }

  /**
   * Adds a user role message
   *
   * @param string $string The message content
   * @return static Returns self for method chaining
   */
  public function addUserMessage($string) {
    $this->addMessage($this->userRoleLable, $string);
  }

  /**
   * Adds an assistant role message
   *
   * @param string $string The message content
   * @return static Returns self for method chaining
   */
  public function addAssistantMessage($string) {
    $this->addMessage($this->assistantRoleLable, $string);
  }

  /**
   * Adds a message with specified role
   *
   * @param string $role The role (system/user/assistant)
   * @param string $message The message content
   * @return static Returns self for method chaining
   * @throws Exception If system message is not first
   */
  /**
   * Adds a message with strict role validation
   *
   * Enforces conversation structure rules:
   * 1. System message must be first (if present)
   * 2. User and assistant messages must alternate
   * 3. No duplicate roles in sequence
   *
   * @param string $role The role (system/user/assistant)
   * @param string $message The message content
   * @return static Returns self for method chaining
   * @throws Exception If:
   *   - System message is not first
   *   - Role sequence is invalid
   */
  public function addMessage(string $role, string $message): static {
    if ('system' == $role && !empty($this->roles)) {
      throw new Exception("The system message MUST be the first message!");
    }

    // Check for invalid role sequence
    $lastRole = end($this->roles)['role'] ?? null;
    if ($lastRole === $role && $role !== 'system') {
      throw new Exception("Cannot add consecutive $role messages");
    }

    $this->roles[] = ['role' => $role, 'content' => $message];

    return $this;
  }

  /**
   * Gets all messages
   *
   * @param string $promptType The prompt type (default: 'llamacpp')
   * @return array|null Array of messages or null if prompt type not supported
   */
  public function getMessages($promptType = 'llamacpp'): array|null {
    if ($promptType == 'llamacpp') {
      return $this->roles;
    };

    return null;
  }

  /**
   * Sets all messages
   *
   * @param array $messages Array of message objects
   * @return static Returns self for method chaining
   */
  public function setMessages(array $messages): static {
    $this->roles = $messages;
    return $this;
  }

  /**
   * Sets the system message (must be first message)
   *
   * @param string $system The system message content
   * @return static Returns self for method chaining
   */
  public function setSystemMessage(string $system): static {
    if (empty($this->roles)) {
      $this->roles[] = [];
    }

    $this->roles[0] = ['role' => 'system', 'content' => $system];

    return $this;
  }

  /**
   * Clears all messages and resets conversation state
   *
   * Note: After clearing, the next message can be:
   * - A new system message (starting fresh conversation)
   * - A user message (if system message is handled externally)
   *
   * @return static Returns self for method chaining
   */
  public function clearMessages() {
    $this->roles = [];
    return $this;
  }

}
