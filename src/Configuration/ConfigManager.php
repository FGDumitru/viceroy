<?php

namespace Viceroy\Configuration;

class ConfigManager {

  private $guzzleOptions = [];

  private ConfigObjects $configObjects;

  private $promptContent = [];

  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

  private function processJsonPromptBlueprint() {
    $serverType = $this->configObjects->getServerConfigKey('server_type');

    if (!str_contains($serverType, DIRECTORY_SEPARATOR)) {
      $prompt = __DIR__ . "/../../Blueprints/$serverType/prompt.json";
    }

    if (file_exists($prompt)) {
      $this->promptContent = json_decode(file_get_contents($prompt), TRUE);
      if (is_null($this->promptContent)) {
        throw new \Exception("Prompt file {$prompt} is NOT a valid JSON file.");
      }
    }
    else {
      throw new \Exception("Prompt settings not found: $prompt");
    }

    $this->promptContent['messages'] = [];
  }

  public function getJsonPrompt() {
    if (empty($this->promptContent)) {
      $this->processJsonPromptBlueprint();
    }

    return $this->promptContent;
  }
}