<?php

namespace Viceroy\Connections;

use Viceroy\Connections\Definitions\TraitableConnectionAbstractClass;
use Viceroy\Connections\Traits\setSystemMessageTrait;

class SelfDynamicParametersConnection extends TraitableConnectionAbstractClass implements \Viceroy\Connections\Definitions\OpenAICompatibleEndpointInterface {
    use setSystemMessageTrait;

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
    private array $definedFunctions = [];

    private $lastResponse = NULL;
    private bool $useLastResponse = FALSE;

    private bool $chainMode = FALSE;
    private bool $debugMode = FALSE;

    public function setDebugMode(bool $debugMode): SelfDynamicParametersConnection
    {
        $this->debugMode = $debugMode;
        return $this;
    }

    public function setSystem(string $systemMessage): void
    {
        $this->setSystemMessage($systemMessage);
    }

    public function getSystem(): string
    {
        return $this->getSystemMessage();
    }

    public function __construct(string $connectionType = 'Definitions\\OpenAICompatibleEndpointConnection')
    {
        parent::__construct($connectionType);
        $this->setSystem($this->systemMessageTemplate);
    }

    public function setConnection($connection): void
    {
        $this->connection = $connection;
    }

    public function setConnectionTimeout(int $timeout) {
        $this->connection->setGuzzleConnectionTimeout($timeout);
    }

    public function addNewFunction(string $functionName, string $definition): SelfDynamicParametersConnection
    {
        $this->definedFunctions[$functionName] = $definition;
        return $this;
    }


    public function tokenize(string $sentence): array|bool
    {
        return $this->connection->tokenize($sentence);
    }

    public function detokenize(array $promptJson): string|bool
    {
        return $this->connection->detokenize($promptJson);
    }

    public function queryPost(array $promptJson = []): \Viceroy\Core\Response|bool
    {
        return $this->connection->queryPost($promptJson);
    }

    public function getThinkContent(): string
    {
        return $this->connection->getThinkContent();
    }

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

    public function isChainMode(): bool
    {
        return $this->chainMode;
    }

    public function setChainMode(bool $chainMode = TRUE): SelfDynamicParametersConnection
    {
        $this->lastResponse = NULL;
        $this->useLastResponse = $chainMode;
        return $this;
    }

    public function getLastResponse() {
        return $this->lastResponse;
    }

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
