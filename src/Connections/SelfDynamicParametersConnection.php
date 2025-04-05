<?php

namespace Viceroy\Connections;

use Viceroy\Plugins\SelfDefiningFunctionsPlugin;

/**
 * SelfDynamicParametersConnection - Dynamic function execution handler for OpenAI endpoints
 *
 * This class provides a powerful interface for:
 * - Defining custom functions with dynamic parameters
 * - Executing these functions against OpenAI-compatible APIs
 * - Chaining function calls using response data
 * - Handling complex JSON response parsing
 *
 * Key Features:
 * - Dynamic function definition via addNewFunction()
 * - Automatic parameter injection and type handling
 * - Response caching for chained operations
 * - Strict JSON response validation
 *
 * Architecture Role:
 * - Extends TraitableConnectionAbstractClass for base connection functionality
 * - Implements OpenAICompatibleEndpointInterface for API compatibility
 * - Works with ConfigManager for system message templates
 * - Integrates with Core\Response for standardized output
 *
 * @package Viceroy\Connections
 */
namespace Viceroy\Connections;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Plugins\SelfDefiningFunctionsPlugin;
use Viceroy\Plugins\PluginInterface;

class SelfDynamicParametersConnection extends OpenAICompatibleEndpointConnection {

    /**
     * @var string $systemMessageTemplate System message template for function execution
     *
     * This template defines:
     * - Required JSON response format
     * - Parameter processing rules
     * - Error handling expectations
     * - Example input/output patterns
     *
     * The template is injected into every function call to ensure
     * consistent response formatting from the LLM.
     */
    /**
     * @var array $definedFunctions Array of user-defined functions and their definitions
     */
    private array $definedFunctions = [];

    /**
     * @var mixed $lastResponse Stores the last response from a function call
     */
    private $lastResponse = NULL;

    /**
     * @var bool $useLastResponse Flag to determine if last response should be used in next call
     */
    private bool $useLastResponse = FALSE;

    /**
     * @var bool $chainMode Flag indicating if chaining mode is active
     */
    private bool $chainMode = FALSE;

    /**
     * @var bool $debugMode Flag to enable/disable debug output
     */
    private bool $debugMode = FALSE;

    /**
     * Sets the debug mode
     *
     * @param bool $debugMode Whether to enable debug mode
     * @return SelfDynamicParametersConnection Returns self for method chaining
     */
    public function setDebugMode(bool $debugMode): SelfDynamicParametersConnection
    {
        $this->debugMode = $debugMode;
        return $this;
    }

    /**
     * Sets the system message
     *
     * @param string $systemMessage The system message to set
     * @return void
     */
    public function setSystem(string $systemMessage): void
    {
        $this->setSystemMessage($systemMessage);
    }

    /**
     * Gets the current system message
     *
     * @return string The current system message
     */
    public function getSystem(): string
    {
        return $this->getSystemMessage();
    }

    /**
     * Constructor
     *
     * @param string $connectionType The connection type to use (default: OpenAICompatibleEndpointConnection)
     */
    public function __construct($config = null)
    {
        parent::__construct($config);
        $this->addPlugin(new \Viceroy\Plugins\SelfDefiningFunctionsPlugin());
    }

    /**
     * Sets the connection object
     *
     * @param mixed $connection The connection object to set
     * @return void
     */
    public function setConnection($connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Sets the connection timeout
     *
     * @param int $timeout Timeout in seconds
     * @return void
     */
    public function setConnectionTimeout(int $timeout) {
        if ($this->connection) {
            $this->connection->setGuzzleConnectionTimeout($timeout);
        }
    }

    /**
     * Adds a new function definition
     *
     * @param string $functionName Name of the function to add
     * @param string $definition Definition/instructions for the function
     * @return SelfDynamicParametersConnection Returns self for method chaining
     */
    public function addNewFunction(string $functionName, string $definition): SelfDynamicParametersConnection
    {
        $this->definedFunctions[$functionName] = $definition;
        return $this;
    }


    /**
     * Tokenizes a sentence
     *
     * @param string $sentence The sentence to tokenize
     * @return array|bool Returns token array on success, false on failure
     */
    public function tokenize(string $sentence): array|bool
    {
        return $this->connection->tokenize($sentence);
    }

    /**
     * Detokenizes a prompt JSON array
     *
     * @param array $promptJson The prompt JSON to detokenize
     * @return string|bool Returns detokenized string on success, false on failure
     */
    public function detokenize(array $promptJson): string|bool
    {
        return $this->connection->detokenize($promptJson);
    }

    /**
     * Executes a POST query
     *
     * @param array $promptJson The prompt data to send
     * @return \Viceroy\Core\Response|bool Returns Response object on success, false on failure
     */
    public function queryPost(string|array $promptJson = [], ?callable $streamCallback = null): \Viceroy\Core\Response
    {
        return $this->connection->queryPost($promptJson);
    }

    /**
     * Gets the think content from the connection
     *
     * @return string The think content
     */
    public function getThinkContent(): string
    {
        return $this->connection->getThinkContent();
    }

    /**
     * Magic method to handle dynamic function calls
     *
     * This method enables calling user-defined functions dynamically.
     * When an undefined method is called, it:
     * 1. Checks if a matching function definition exists
     * 2. Prepares the system message template
     * 3. Formats parameters with type information
     * 4. Executes the query via the connection
     * 5. Parses and returns the response
     *
     * Example Usage:
     * $connection->addNewFunction('capitalize', 'Capitalize the input string');
     * $result = $connection->capitalize('hello world'); // Returns "HELLO WORLD"
     *
     * @param string $method The method name being called
     * @param array $arguments The arguments passed to the method
     * @return mixed The result of the function call
     * @throws \BadMethodCallException If method doesn't exist
     * @throws \JsonException If JSON parsing fails
     * @throws \LogicException If response parsing fails
     */

    /**
     * Checks if chain mode is active
     *
     * @return bool True if chain mode is active, false otherwise
     */
    public function isChainMode(): bool
    {
        return $this->chainMode;
    }

    /**
     * Sets the chain mode
     *
     * @param bool $chainMode Whether to enable chain mode (default: true)
     * @return SelfDynamicParametersConnection Returns self for method chaining
     */
    public function setChainMode(bool $chainMode = TRUE): SelfDynamicParametersConnection
    {
        $this->lastResponse = NULL;
        $this->useLastResponse = $chainMode;
        return $this;
    }

    /**
     * Gets the last response
     *
     * @return mixed The last response received
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Parses and handles the response from a function call
     *
     * This method performs:
     * 1. JSON extraction from raw response
     * 2. Strict JSON validation
     * 3. Response structure verification
     * 4. Error handling and exception throwing
     * 5. Response caching for chained operations
     *
     * Response Requirements:
     * - Must contain valid JSON with 'response' key
     * - Errors must include 'error' key
     * - Must match system message template format
     *
     * @param string $resultRaw The raw response string
     * @param string $functionCommands The function commands that were sent
     * @return mixed The parsed response or self for chaining
     * @throws \JsonException If JSON parsing fails
     * @throws \LogicException If response is invalid or missing required keys
     */
    protected function parseAndHandleResponse(string $resultRaw, string $functionCommands)
    {
        preg_match('/\{.*?\}/s', $resultRaw, $matches);
        $extracted_json = $matches[0] ?? null;

        if ($this->debugMode) {
            echo "RESPONSE: $resultRaw\n\n";
        }

        $jsonParsedResult = json_decode($extracted_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \JsonException($this->connection->error($functionCommands));
        }

        if (isset($jsonParsedResult['response'])) {
            $this->lastResponse = $jsonParsedResult['response'];

            if ($this->useLastResponse) {
                return $this;
            }

            return $this->lastResponse;
        }

        $this->useLastResponse = NULL;
        throw new \LogicException($jsonParsedResult['error'] ?? 'Unknown error');
    }
}
