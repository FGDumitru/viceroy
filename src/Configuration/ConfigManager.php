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

  public function getJsonPrompt() {
    if (empty($this->promptContent)) {
      $this->processJsonPromptBlueprint();
    }

    return $this->promptContent;
  }

  private function processJsonPromptBlueprint() {
    $this->promptContent = $this->readParameters();
    $this->promptContent['messages'] = [];
  }

}