<?php

/**
 * ConfigManager - Handles configuration management for the Viceroy system
 * 
 * This class manages configuration objects and prompt processing,
 * providing methods to retrieve and process JSON prompts.
 */
namespace Viceroy\Configuration;

use Viceroy\Connections\Traits\LLMDefaultParametersTrait;

class ConfigManager {
  use LLMDefaultParametersTrait;

  /**
   * @var array $guzzleOptions Guzzle HTTP client options
   */
  private $guzzleOptions = [];

  /**
   * @var ConfigObjects $configObjects Configuration objects container
   */
  private ConfigObjects $configObjects;

  /**
   * @var array $promptContent Processed prompt content
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
   * Processes JSON prompt blueprint
   *
   * @param string $promptType Type of prompt to process
   * @return void
   */
  private function processJsonPromptBlueprint($promptType) {
    $this->promptContent = $this->readParameters($promptType);
    $this->promptContent['messages'] = [];
  }

}
