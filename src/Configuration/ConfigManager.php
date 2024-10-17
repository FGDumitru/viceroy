<?php

namespace Viceroy\Configuration;

use Viceroy\Connections\Traits\LLMDefaultParametersTrait;

class ConfigManager {
  use LLMDefaultParametersTrait;

  private $guzzleOptions = [];

  private ConfigObjects $configObjects;

  private $promptContent = [];

  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

  public function getJsonPrompt($promptType) {
    if (empty($this->promptContent)) {
      $this->processJsonPromptBlueprint($promptType);
    }

    return $this->promptContent;
  }

  private function processJsonPromptBlueprint($promptType) {
    $this->promptContent = $this->readParameters($promptType);
    $this->promptContent['messages'] = [];
  }

}