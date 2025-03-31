<?php

/**
 * TraitableConnectionAbstractClass - Base implementation for traitable connections
 *
 * This abstract class provides:
 * - Proxy pattern implementation for method pass-through
 * - Base functionality for OpenAI-compatible connections
 * - Common methods required by OpenAICompatibleEndpointInterface
 * - Dynamic method handling via __call
 *
 * Architecture Role:
 * - Serves as base class for all connection implementations
 * - Implements proxy pattern to wrap concrete connections
 * - Provides common functionality to child classes
 * - Enables trait composition via method pass-through
 *
 * @package Viceroy\Connections\Definitions
 */
namespace Viceroy\Connections\Definitions;

class TraitableConnectionAbstractClass
{
    /**
     * @var object $connectionOriginal The original unwrapped connection instance
     *
     * This holds the concrete connection implementation before
     * being wrapped by the proxy object.
     */
    protected $connectionOriginal;

    /**
     * @var object $connection The proxy connection instance
     *
     * This anonymous class instance wraps the original connection
     * to enable method pass-through and trait composition.
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
     * This method:
     * 1. Creates an anonymous proxy class
     * 2. Wraps the original connection instance
     * 3. Enables method forwarding via __call
     *
     * The proxy pattern is used to:
     * - Maintain separation between base and concrete implementations
     * - Enable trait composition without direct inheritance
     * - Provide flexibility for dynamic method handling
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
     * Magic method for dynamic method handling
     *
     * This method:
     * 1. Attempts to call method on proxied connection
     * 2. Falls back to dynamic LLM function calling
     * 3. Throws exception for undefined methods
     *
     * Key Features:
     * - Enables transparent method forwarding
     * - Supports dynamic function calling
     * - Maintains strict method existence checking
     *
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed Method return value
     * @throws \BadMethodCallException If method doesn't exist on proxied object
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
