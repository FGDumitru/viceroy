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
 * Expected configuration structure:
 * {
 *   "host": "http://127.0.0.1",
 *   "port": "8855",
 *   "completions": "/v1/chat/completions",
 *   "health": "/v1/health",
 *   "models": "/v1/models",
 *   "server_type": "llamacpp",
 *   "type": "OpenAiCompatibleConnection",
 *   "preferredModel": "gpt-4o",
 *   "debug": boolean,
 *   ...other application-specific settings
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
     * - 'host' key for API endpoint host
     * - 'port' key for API endpoint port
     * - 'completions' key for completions endpoint
     * - 'health' key for health endpoint
     * - 'models' key for models endpoint
     * - 'server_type' key for server type
     * - 'type' key for connection type
     * - 'preferredModel' key for preferred model
     * - 'debug' key for debug mode
     * - Other application-specific keys
     */
    private $config = [
        "host" => "https://api.openai.com",
        "port" => "443",
        "preferredModel" => "gpt-4o"
    ];

    /**
     * Constructor
     *
     * @param string $configFile Path to configuration file (default: 'config.json')
     */
    public function __construct(string $configFile = 'config.json')
    {
        $currentDir = getcwd();
        $configFilename = $currentDir . DIRECTORY_SEPARATOR . $configFile;
        $this->readConfigFile($configFilename);
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
    public function emptyConfigData(): ConfigObjects
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
     * Common configuration keys include:
     * - 'host': API endpoint host
     * - 'port': Connection port
     * - 'completions': Completions endpoint
     * - 'health': Health endpoint
     * - 'models': Models endpoint
     * - 'server_type': Server type
     * - 'type': Connection type
     * - 'preferredModel': Preferred model
     *
     * @param string $key Configuration key
     * @return mixed The configuration value or null if key doesn't exist
     * @throws \InvalidArgumentException If config section doesn't exist
     */
    public function getConfigKey(string $key)
    {
        if (!isset($this->config[$key])) {
            throw new \InvalidArgumentException("Config section doesn't exist");
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
