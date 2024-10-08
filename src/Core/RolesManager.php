<?php

namespace Viceroy\Core;

use Exception;

class RolesManager {

  private array $roles = [];
  private $userRoleLable = 'User';
  private $assistantRoleLable = 'Assistant';

  /**
   * @throws \Exception
   */
  public function __construct(string $system = NULL) {
    if (!is_null($system)) {
      $this->systemMessage = $system;
      $this->addMessage('system', $system);
    }
  }

  public function addUserMessage($string) {
    $this->addMessage($this->userRoleLable, $string);
  }

  public function addAssistantMessage($string) {
    $this->addMessage($this->assistantRoleLable, $string);
  }

  public function addMessage(string $role, string $message): static {
    if ('system' == $role && !empty($this->roles)) {
      throw new Exception("The system message MUST be the first message!");
    }

    $this->roles[] = ['role' => $role, 'content' => $message];

    return $this;
  }

  public function getMessages(): array {
    return $this->roles;
  }

  public function setMessages(array $messages): static {
    $this->roles = $messages;
    return $this;
  }

  public function setSystemMessage(string $system): static {
    if (empty($this->roles)) {
      $this->roles[] = [];
    }

    $this->roles[0] = ['role' => 'system', 'content' => $system];

    return $this;
  }

  public function clearMessages(): static {
    $this->roles = [];
    return $this;
  }

}