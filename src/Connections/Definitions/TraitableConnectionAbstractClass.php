<?php

namespace Viceroy\Connections\Definitions;

class TraitableConnectionAbstractClass
{
    protected $connectionOriginal;
    protected $connection;

    function __construct(string $connectionType)
    {
        $fullyQualifiedClass = 'Viceroy\\Connections\\' . $connectionType;
        $this->connectionOriginal = new $fullyQualifiedClass();
        $this->createProxyObject($fullyQualifiedClass);
    }

    private function createProxyObject($fullyQualifiedClass): void
    {
        // Let's use a proxy anonymous class for pass through methods.
        $this->connection = new class($this->connectionOriginal) {
            private $baseInstance;

            public function __construct($baseInstance)
            {
                $this->baseInstance = $baseInstance;
            }

            public function getBaseInstance() {
                return $this->baseInstance;
            }

            public function __call($method, $arguments)
            {
                if (method_exists($this->baseInstance, $method)) {
                    return $this->baseInstance->$method(...$arguments);
                } else {
                    $offendingClass = get_class($this->baseInstance);
                    throw new \BadMethodCallException("Method ($method) does not exist on the original object ($offendingClass)");
                }
            }

        };
    }

    public function getThinkContent(): string {
        return $this->connection->getThinkContent();
    }

    public function getModels() {
        return $this->connection->getAvailableModels();
    }

    public function __call($method, $arguments)
    {
        if (method_exists($this->connection->getBaseInstance(),$method)) {
            return $this->connection->getBaseInstance()->$method(...$arguments);
        } else {
            // Dynamic LLM function calling
        }

        throw new \BadMethodCallException("Method ($method) does not exist on the original object");
    }

}
