<?php

/**
 * ConfigManager - Central configuration management for the Viceroy system
 *
 * This class serves as the primary interface for managing all configuration
 * objects and prompt processing. It handles:
 * - Configuration object storage and retrieval
 * - JSON prompt processing and caching
 * - Integration with LLM default parameters via trait
 * - HTTP client configuration for API calls
 *
 * @package Viceroy\Configuration
 */
namespace Viceroy\Configuration;

use Viceroy\Connections\Traits\LLMDefaultParametersTrait;

class ConfigManager {
  use LLMDefaultParametersTrait;

  /**
   * @var array $guzzleOptions Guzzle HTTP client options
   *
   * Configuration options for the Guzzle HTTP client used for API requests.
   * Common options include:
   * - timeout: Request timeout in seconds
   * - headers: Default request headers
   * - verify: SSL certificate verification
   */
  private $guzzleOptions = [];

  /**
   * @var ConfigObjects $configObjects Configuration objects container
   */
  private ConfigObjects $configObjects;

  /**
   * @var array $promptContent Processed prompt content cache
   *
   * Stores processed prompt content in memory to avoid repeated processing.
   * Structure:
   * [
   *   'messages' => array of message objects,
   *   ...other prompt parameters
   * ]
   */
  private $promptContent = [];

  /**
   * Constructor
   *
   * @param ConfigObjects $configObjects Configuration objects container
   */
  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

  /**
   * Gets JSON prompt content
   *
   * @param string $promptType Type of prompt to retrieve
   * @return array Processed prompt content
   */
  public function getJsonPrompt($promptType) {
    if (empty($this->promptContent)) {
      $this->processJsonPromptBlueprint($promptType);
    }

    return $this->promptContent;
  }

  /**
   * Processes JSON prompt blueprint into executable format
   *
   * This method:
   * 1. Reads the raw prompt parameters using readParameters()
   * 2. Initializes the messages array
   * 3. Caches the processed content in $promptContent
   *
   * @param string $promptType Type of prompt to process (e.g. 'chat', 'query')
   * @return void
   * @throws \RuntimeException If prompt parameters cannot be read
   */
  private function processJsonPromptBlueprint($promptType) {
    $this->promptContent = $this->readParameters($promptType);
    $this->promptContent['messages'] = [];
  }

}
