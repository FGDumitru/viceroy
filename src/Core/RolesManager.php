<?php

namespace Viceroy\Core;

use Exception;
use stdClass;

class RolesManager {

  private array $roles = [];
  private $userRoleLable = 'user';
  private $assistantRoleLable = 'assistant';

  /**
   * @throws \Exception
   */
  public function __construct() {
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

  public function getMessages($promptType = 'llamacpp'): array|null {
    if ($promptType == 'llamacpp') {
      return $this->roles;
    };

    return null;
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

  public function clearMessages() {
    $this->roles = [];
    return $this;
  }

}