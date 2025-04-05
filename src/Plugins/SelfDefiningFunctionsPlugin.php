<?php

namespace Viceroy\Plugins;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class SelfDefiningFunctionsPlugin implements PluginInterface {
    private array $definedFunctions = [];
    private ?OpenAICompatibleEndpointConnection $connection = null;
    
    private string $systemMessageTemplate = <<<SYS
Your task:
    Process parameters exactly as instructed and return the direct result
Output Requirements:
    Return only the requested value, no JSON formatting
Examples:
    User Input: "Capitalize string: [Parameter 1] test"
    Expected Output: "TEST"
SYS;

    public function getName(): string {
        return 'self_defining_functions';
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void {
        $this->connection = $connection;
    }

    public function canHandle(string $method): bool {
        return array_key_exists($method, $this->definedFunctions);
    }

    public function handleMethodCall(string $method, array $args): mixed {
        if (!isset($this->definedFunctions[$method])) {
            throw new \BadMethodCallException("Method $method not defined");
        }

        $this->connection->getRolesManager()
            ->setSystemMessage($this->systemMessageTemplate);

        $prompt = $this->definedFunctions[$method] . "\n\n";
        foreach ($args as $i => $arg) {
            $prompt .= "[Parameter ".($i+1)." (".gettype($arg).")]\n".$arg."\n\n";
        }

        return $this->connection->query($prompt);
    }

    public function addNewFunction(string $name, string $definition): void {
        $this->definedFunctions[$name] = $definition;
    }
}