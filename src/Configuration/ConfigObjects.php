<?php

namespace Viceroy\Configuration;

class ConfigObjects {

  private $config = [];

  function __construct($configFile = 'config.json') {
    $currentDir = getcwd();
    $configFilename = $currentDir . DIRECTORY_SEPARATOR . $configFile;
    $this->readConfigFile($configFilename);
  }

  public function readConfigFile($configFile): bool {
    if (file_exists($configFile)) {
      $this->config = json_decode(file_get_contents($configFile),
        TRUE);
    }
    else {
      return FALSE;
    }

    return TRUE;
  }

  public function getFullConfigData(): array {
    return $this->config;
  }

  public function setFullConfigData($data) {
    $this->config = $data;
    return $this;
  }

  public function hasValidConfigData(): bool {
    return !empty($this->config);
  }

  public function emptyConfigData(): ConfigObjects {
    $this->config = [];
    return $this;
  }

  public function configKeyExists(string $key): bool {
    return array_key_exists($key, $this->config);
  }

  public function getServerConfigKey(string $key) {
    return $this->config['server'][$key];
  }

  public function isDebug() {
    return $this->config['debug'] ?? FALSE;
  }

}