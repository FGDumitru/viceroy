<?php

/**
 * ConfigObjects - Handles configuration data storage and retrieval
 * 
 * This class manages configuration data from JSON files and provides
 * methods to access and modify the configuration.
 */
namespace Viceroy\Configuration;

class ConfigObjects {

  /**
   * @var array $config Configuration data storage
   */
  private $config = [];

  /**
   * Constructor
   *
   * @param string $configFile Path to configuration file (default: 'config.json')
   */
  public function __construct($configFile = 'config.json') {
    $currentDir = getcwd();
    $configFilename = $currentDir . DIRECTORY_SEPARATOR . $configFile;
    $this->readConfigFile($configFilename);
  }

  /**
   * Reads configuration from file
   *
   * @param string $configFile Path to configuration file
   * @return bool True if file was successfully read, false otherwise
   */
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

  /**
   * Gets all configuration data
   *
   * @return array Complete configuration data
   */
  public function getFullConfigData(): array {
    return $this->config;
  }

  /**
   * Sets all configuration data
   *
   * @param array $data Configuration data to set
   * @return ConfigObjects Returns self for method chaining
   */
  public function setFullConfigData($data) {
    $this->config = $data;
    return $this;
  }

  /**
   * Checks if configuration data exists
   *
   * @return bool True if configuration data exists, false otherwise
   */
  public function hasValidConfigData(): bool {
    return !empty($this->config);
  }

  /**
   * Clears all configuration data
   *
   * @return ConfigObjects Returns self for method chaining
   */
  public function emptyConfigData(): ConfigObjects {
    $this->config = [];
    return $this;
  }

  /**
   * Checks if a configuration key exists
   *
   * @param string $key Configuration key to check
   * @return bool True if key exists, false otherwise
   */
  public function configKeyExists(string $key): bool {
    return array_key_exists($key, $this->config);
  }

  /**
   * Gets a server configuration value by key
   *
   * @param string $key Server configuration key
   * @return mixed The configuration value or null if not found
   */
  public function getServerConfigKey(string $key) {
    return $this->config['server'][$key];
  }

  /**
   * Checks if debug mode is enabled
   *
   * @return bool True if debug mode is enabled, false otherwise
   */
  public function isDebug() {
    return $this->config['debug'] ?? FALSE;
  }

}
