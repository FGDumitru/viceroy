<?php

/**
 * TraitableConnectionAbstractClass - Abstract base class for traitable connections
 * 
 * This class provides proxy functionality and method pass-through
 * for connection implementations.
 */
namespace Viceroy\Connections\Definitions;

class TraitableConnectionAbstractClass
{
    /**
     * @var object $connectionOriginal The original connection instance
     */
    protected $connectionOriginal;

    /**
     * @var object $connection The proxy connection instance
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param string $connectionType Fully qualified connection class name
     */
    public function __construct(string $connectionType)
    {
        $fullyQualifiedClass = 'Viceroy\\Connections\\' . $connectionType;
        $this->connectionOriginal = new $fullyQualifiedClass();
        $this->createProxyObject($fullyQualifiedClass);
    }

    /**
     * Creates a proxy object for method pass-through
     *
     * @param string $fullyQualifiedClass The class name to proxy
     * @return void
     */
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

    /**
     * Gets think content from the connection
     *
     * @return string The think content
     */
    public function getThinkContent(): string {
        return $this->connection->getThinkContent();
    }

    /**
     * Gets available models from the connection
     *
     * @return mixed Available models
     */
    public function getModels() {
        return $this->connection->getAvailableModels();
    }

    /**
     * Magic method for method pass-through
     *
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed Method return value
     * @throws \BadMethodCallException If method doesn't exist
     */
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
