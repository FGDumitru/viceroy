<?php

namespace Viceroy\Plugins;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Core\PluginInterface;

class SelfDefiningFunctionsPlugin implements PluginInterface
{
    private array $definedFunctions = [];
    private ?OpenAICompatibleEndpointConnection $connection = null;

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

    private $lastResponse = null;
    private bool $chainMode = false;

    public function getName(): string
    {
        return 'self_defining_functions';
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function canHandle(string $method): bool
    {
        return array_key_exists($method, $this->definedFunctions);
    }

    /**
     * @throws \Exception
     */
    public function handleMethodCall(string $method, array $args): mixed
    {
        if (!isset($this->definedFunctions[$method])) {
            throw new \BadMethodCallException("Method $method not defined");
        }

        $this->connection->getRolesManager()->clearMessages();
        $this->connection->getRolesManager()
            ->setSystemMessage($this->systemMessageTemplate);

        $prompt = $this->definedFunctions[$method] . "\n\n";
        $indexOffset = 1;
        if ($this->isInChainMode() && !is_null($this->lastResponse)) {
            $lastResponse = $this->getLastResponse();
            $prompt .= "[Parameter " . ($indexOffset) . " (" . gettype($this->getLastResponse()) . ")]\n" . $this->getLastResponse() . "\n\n";
            $indexOffset++;
        }

        foreach ($args as $i => $arg) {
            $prompt .= "[Parameter " . ($i + $indexOffset) . " (" . gettype($arg) . ")]\n" . $arg . "\n\n";
        }

        $rawResult = $this->extractJsonWithRegex($this->connection->query($prompt));

        // We are expecting a JSON object.

        $decodedResult = json_decode($rawResult, true);

        $response = $decodedResult['response'] ?? null;
        $this->lastResponse = $response;

        if ($this->chainMode) {
            return $this->connection;
        }

        return $response;
    }

    /**
     * Extracts a JSON object from a string.
     *
     * @param $input
     * @return string|null
     */
    private function extractJsonWithRegex($input): ?string
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/', $input, $matches)) {
            $json = $matches[0];
            json_decode($json);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return null;
    }

    public function addNewFunction(string $name, string $definition): void
    {
        $this->definedFunctions[$name] = $definition;
    }

    public function setChainMode(bool $enabled = TRUE): self
    {
        $this->chainMode = $enabled;
        return $this;
    }

    public function isInChainMode(): bool
    {
        return $this->chainMode;
    }

    /**
     * @return null
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

}