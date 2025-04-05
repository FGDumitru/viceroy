<?php

/**
 * ConfigObjects - Central configuration data container for Viceroy
 *
 * This class serves as the primary data store for all configuration values,
 * typically loaded from JSON files. It provides:
 * - Configuration loading and validation
 * - Type-safe access to configuration values
 * - Methods for bulk configuration management
 * - Integration with ConfigManager for prompt processing
 *
 * Expected configuration array structure:
 * {
 *   "host": "http://127.0.0.1:5000",
 *   "bearer": "optional API authorization key",
 *   "preferredModel": "gpt-4o",
 * }
 *
 * @package Viceroy\Configuration
 */

namespace Viceroy\Configuration;

class ConfigObjects
{

    /**
     * @var array $config Configuration data storage
     *
     * Stores all configuration values in associative array format.
     * Structure should match the JSON configuration file format with:
     * - 'host' key for API endpoint host (includes port if required))
     * - 'bearer' (optional) endpoint API authorization key
     * - 'preferredModel' name of the preferred model
     */
    private $config = [
        "host" => "https://api.openai.com",
        "bearer" => "",
        "preferredModel" => "gpt-4o"
    ];

    /**
     * Constructor
     *
     * @param string|array $configFile Path to configuration file (default: 'config.json') or an array of settings
     */
    public function __construct(string|array $configFile = 'config.json')
    {
        if (is_string($configFile)) {
            $currentDir = getcwd();
            $configFilename = $currentDir . DIRECTORY_SEPARATOR . $configFile;
            $this->readConfigFile($configFilename);
        } elseif (is_array($configFile)) {
            $this->config = $configFile;
        }
    }

    /**
     * Reads and validates configuration from JSON file
     *
     * Loads configuration from specified JSON file, validating that:
     * - File exists and is readable
     * - Contains valid JSON
     * - Has required structure
     *
     * @param string $configFile Path to configuration file
     * @return bool True if file was successfully read and parsed, false otherwise
     * @throws \RuntimeException If JSON is malformed
     */
    public function readConfigFile($configFile): bool
    {
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile),
                TRUE);
        } else {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Gets all configuration data
     *
     * @return array Complete configuration data
     */
    public function getFullConfigData(): array
    {
        return $this->config;
    }

    /**
     * Sets all configuration data
     *
     * @param array $data Configuration data to set
     * @return ConfigObjects Returns self for method chaining
     */
    public function setFullConfigData($data)
    {
        $this->config = $data;
        return $this;
    }

    /**
     * Checks if configuration data exists
     *
     * @return bool True if configuration data exists, false otherwise
     */
    public function hasValidConfigData(): bool
    {
        return !empty($this->config);
    }

    /**
     * Clears all configuration data
     *
     * @return ConfigObjects Returns self for method chaining
     */
    public function clearConfigData(): ConfigObjects
    {
        $this->config = [];
        return $this;
    }

    /**
     * Checks if a configuration key exists
     *
     * @param string $key Configuration key to check
     * @return bool True if key exists, false otherwise
     */
    public function configKeyExists(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Gets a configuration value
     *
     * Retrieves a value from the configuration.
     *
     * @param string $key Configuration key
     * @return string|null The configuration value or null if key doesn't exist
     * @throws \InvalidArgumentException If config section doesn't exist
     */
    public function getConfigKey(string $key): string|null
    {
        if (!isset($this->config[$key])) {
            return null;
        }
        return $this->config[$key];
    }

    /**
     * Checks if debug mode is enabled
     *
     * @return bool True if debug mode is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->config['debug'] ?? FALSE;
    }

}
