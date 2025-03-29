<?php

/**
 * RolesManager - Manages conversation roles and messages
 * 
 * This class handles the organization of messages by role (system, user, assistant)
 * and provides methods to manage conversation flow.
 */
namespace Viceroy\Core;

use Exception;
use stdClass;

class RolesManager {

  /**
   * @var array $roles Array of message objects with role and content
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
  public function addMessage(string $role, string $message): static {
    if ('system' == $role && !empty($this->roles)) {
      throw new Exception("The system message MUST be the first message!");
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
   * Clears all messages
   *
   * @return static Returns self for method chaining
   */
  public function clearMessages() {
    $this->roles = [];
    return $this;
  }

}
