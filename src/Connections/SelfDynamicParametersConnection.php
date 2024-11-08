<?php

namespace Viceroy\Connections;

use Viceroy\Connections\Definitions\TraitableConnectionAbstractClass;
use Viceroy\Connections\Traits\setSystemMessageTrait;

class SelfDynamicParametersConnection extends TraitableConnectionAbstractClass {
    use setSystemMessageTrait;

    private $systemMessageTemplate = <<<SYS
You are a helpful assistant with extensive PHP programming experience.
You will receive from the user a set of instructions and a list of one or more parameters.
Your role is to process those parameters as per the user's request and return a JSON object with the "response" key containing the parameters processed as per user instructions.
The result can be:
- NIL if the result cannot be computed. In this case you will reason on why it cannot be computed in the "error" key.
- A number, a string or an array of number and/or strings.

Example 1:
USER INPUT
Capitalize all letter from the following string:
[Parameter 1]
This is a test string
OUTPUT
{"response":"THIS IS A TEST STRING"}
END OF OUTPUT

IT'S VERY IMPORTANT TO RESPOND ONLY IN JSON FORMAT!
SYS;
    private $definedFunctions = [];

    private $lastResponse = NULL;
    private $useLastResponse = FALSE;

    private $chainMode = FALSE;

    public function setSystem(string $systemMessage): void
    {
        $this->setSystemMessage($this->systemMessageTemplate);
        $this->setSystemMessageTrait($this->systemMessageTemplate);
    }

    public function getSystem() {
        return $this->getSystemMessage();
    }

    public function __construct(string $connectionType = 'llamacppOAICompatibleConnection')
    {
        parent::__construct($connectionType);
        $this->setSystem($this->systemMessageTemplate);
    }

    public function addNewFunction(string $functionName, string $definition): void {
        $this->definedFunctions[$functionName] = $definition;
    }

    public function andThen() {
        $this->useLastResponse = TRUE;
    }

    public function __call($method,  $arguments) {
        try {
            parent::__call($method, $arguments);
        } catch (\BadMethodCallException $e) {
            if (isset($this->definedFunctions[$method])) {
                $this->connection->getRolesManager()->setSystemMessage($this->systemMessageTemplate);

                $functionCommands = $this->definedFunctions[$method] . '\n\n';

                if ($this->useLastResponse && !is_null($this->lastResponse)) {
                    $arguments = array_merge([$this->lastResponse], $arguments);
                }

                foreach ($arguments as $index => $argument) {
                    $encodedArgument = json_encode($argument);
                    $argumentType = get_debug_type($argument);
                    $functionCommands .= "[PARAMETER $index of type $argumentType]\n $encodedArgument\n\n";
                }

//                echo "\n\n\tREQUEST: $functionCommands\n\n";
                $resultRaw = $this->connection->query($functionCommands);

//                echo "RESPONSE: $resultRaw\n\n";

                $jsonParsedResult = json_decode($resultRaw, true);

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
                var_dump($resultRaw);
                throw new \LogicException($jsonParsedResult['error'] ?? 'Unknown error');
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


}