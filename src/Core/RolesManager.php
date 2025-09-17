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
  public function addMessage(string $role, string $message, array|string $images = null): static {

    if (is_string($images)) {
        $images = [$images];
    }

    $haveImages = isset($images) && !empty($images);

    if ('user' == $role && empty($this->roles)) {
        $this->addMessage('system', 'You are a helpful, knowledgeable, and safe generative AI assistant.');
    } elseif ('system' == $role && !empty($this->roles)) {
        throw new Exception("System message must be the first message");
    }

    if (empty($this->roles) && 'system' !== $role) {
        throw new Exception("The first message MUST be a system message");
    }

    if (isset($images) && 'user' !== $role) {
        throw new Exception("Only user messages can contain images");
    }

    // Check for invalid role sequence
    $lastRole = end($this->roles)['role'] ?? null;
    if ($lastRole === $role && $role !== 'system') {
        throw new Exception("Cannot add consecutive $role messages");
    }

    if (!$haveImages) {
        $this->roles[] = ['role' => $role, 'content' => $message];
    } else {
        $content = [['type' => 'text', 'text' => $message]];
        
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $this->convertImageToBase64($image)
                ]
            ];
        }

        $this->roles[] = ['role' => $role, 'content' => $content];
    }

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

  // Make sure your convertImageToBase64 function returns the proper format:
private function convertImageToBase64(string $imagePath): string {
    // Read image file and convert to base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Determine MIME type (simplified)
    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];
    
    $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
    
    return "data:$mimeType;base64,$imageData";
}

}
