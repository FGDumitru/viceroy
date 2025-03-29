<?php

/**
 * SelfDynamicParametersConnection - A connection class that allows dynamic parameter handling
 * and function definitions for OpenAI-compatible endpoints.
 * 
 * This class extends TraitableConnectionAbstractClass and implements OpenAICompatibleEndpointInterface,
 * providing functionality to define and execute custom functions with dynamic parameters.
 */
namespace Viceroy\Connections;

use Viceroy\Connections\Definitions\TraitableConnectionAbstractClass;
use Viceroy\Connections\Traits\setSystemMessageTrait;

class SelfDynamicParametersConnection extends TraitableConnectionAbstractClass implements \Viceroy\Connections\Definitions\OpenAICompatibleEndpointInterface {
    use setSystemMessageTrait;

    /**
     * @var string $systemMessageTemplate The system message template used for function execution
     * Contains instructions for processing parameters and formatting responses
     */
    private string $systemMessageTemplate = <<<SYS
Your task:

    You will receive a set of user instructions along with one or more parameters.
    Your role is to process these parameters exactly as instructed and return the result in JSON format.

Output Requirements:

    Always respond with a JSON object containing a "response" key. DO NOT OUTPUT anything else after the JSON object has finished printing.
    The "response" key should contain the result based on the user's instructions.
        If the result cannot be computed, set "response" to NIL and include a reason in the "error" key explaining why it could not be computed.
        The output can be a single number, a string, or an array of numbers and/or strings.

Output Format:

    Start your response directly with — do not include any additional text, explanations, or delimiters.

Examples

    User Input: "Capitalize all letters from the following string: [Parameter 1] This is a test string"
    Expected Output: {"response":"THIS IS A TEST STRING"}

    User Input: "Sum the following numbers: [Parameter 1] 5, [Parameter 2] 10, [Parameter 3] 15"
    Expected Output: {"response":30}

    User Input: "Calculate the factorial of the following number: [Parameter 1] 5"
    Expected Output: {"response":120}

    User Input: "Extract the domain from the following email: [Parameter 1] user@example.com"
    Expected Output: {"response":"example.com"}

    User Input: "Divide [Parameter 1] by [Parameter 2]: [Parameter 1] 10, [Parameter 2] 0"
    Expected Output: {"response":NIL,"error":"Division by zero is not allowed"}<end of output>

    User Input: "Reverse the following string: [Parameter 1] OpenAI"
    Expected Output: {"response":"IAnepO"}

    User Input: "Convert the following Celsius temperature to Fahrenheit: [Parameter 1] 25"
    Expected Output: {"response":77}

    User Input: "Find the length of the following array: [Parameter 1] [2, 4, 6, 8, 10]"
    Expected Output: {"response":5}

    User Input: "Check if the following word is a palindrome: [Parameter 1] radar"
    Expected Output: {"response":true}

    User Input: "Find the square root of [Parameter 1]: [Parameter 1] 49"
    Expected Output: {"response":7}

    User Input: "Concatenate the following strings with a space in between: [Parameter 1] Hello, [Parameter 2] World"
    Expected Output: {"response":"Hello World"}

    User Input: "Sort the following numbers in ascending order: [Parameter 1] [9, 3, 5, 1, 4]"
    Expected Output: {"response":[1,3,4,5,9]}

    User Input: "Convert the following time from hours to seconds: [Parameter 1] 2"
    Expected Output: {"response":7200}

    User Input: "Extract the file extension from the following filename: [Parameter 1] document.pdf"
    Expected Output: {"response":"pdf"}

    User Input: "Replace all spaces with underscores in the following string: [Parameter 1] Hello World Example"
    Expected Output: {"response":"Hello_World_Example"}

    User Input: "Check if the following number is even: [Parameter 1] 11"
    Expected Output: {"response":false}

Important: Always respond in JSON format only, beginning with {, ending with } and following the specified format exactly. Do not output anything else after you finish outputting the JSON object (IMPORTANT!).
SYS;
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
    public function __construct(string $connectionType = 'Definitions\\OpenAICompatibleEndpointConnection')
    {
        parent::__construct($connectionType);
        $this->setSystem($this->systemMessageTemplate);
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
        $this->connection->setGuzzleConnectionTimeout($timeout);
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
    public function queryPost(array $promptJson = []): \Viceroy\Core\Response|bool
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
     * @param string $method The method name being called
     * @param array $arguments The arguments passed to the method
     * @return mixed The result of the function call
     * @throws \BadMethodCallException If method doesn't exist
     * @throws \JsonException If JSON parsing fails
     * @throws \LogicException If response parsing fails
     */
    public function __call($method,  $arguments) {
        try {
            parent::__call($method, $arguments);
        } catch (\BadMethodCallException $e) {
            if (isset($this->definedFunctions[$method])) {

                if (!$this->useLastResponse) {
                    $this->connection->getRolesManager()->clearMessages();
                }

                $this->connection->getRolesManager()->setSystemMessage($this->systemMessageTemplate);

                $functionCommands = $this->definedFunctions[$method] . "\n\n";

                if ($this->useLastResponse && !is_null($this->lastResponse)) {
                    $arguments = array_merge([$this->lastResponse], $arguments);
                }

                foreach ($arguments as $index => $argument) {
                    $argumentType = get_debug_type($argument);
                    $functionCommands .= "[PARAMETER $index of type $argumentType]\n $argument\n\n";
                }

                if ($this->debugMode) {
                    echo "\n\n\tREQUEST: $functionCommands\n\n";
                }
                $resultRaw = $this->connection->query($functionCommands);

                return $this->parseAndHandleResponse($resultRaw, $functionCommands);
            }
        }

    }

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
     * @param string $resultRaw The raw response string
     * @param string $functionCommands The function commands that were sent
     * @return mixed The parsed response
     * @throws \JsonException If JSON parsing fails
     * @throws \LogicException If response is invalid
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
