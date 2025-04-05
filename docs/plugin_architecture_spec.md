# Plugin Architecture Specification for OpenAICompatibleEndpointConnection

## Overview
This document outlines a plugin-based architecture to extend the functionality of `OpenAICompatibleEndpointConnection` while maintaining a clean core implementation.

## Core Components

### 1. Plugin Interface
```php
interface PluginInterface {
    /**
     * Returns the plugin's unique identifier
     */
    public function getName(): string;

    /**
     * Initializes the plugin with the connection instance
     */
    public function initialize(OpenAICompatibleEndpointConnection $connection): void;

    /**
     * Checks if plugin can handle the method call
     */
    public function canHandle(string $method): bool;

    /**
     * Handles the method call
     */
    public function handleMethodCall(string $method, array $args): mixed;
}
```

### 2. Plugin Manager
```php
class PluginManager {
    private array $plugins = [];

    public function add(PluginInterface $plugin): void {
        $this->plugins[$plugin->getName()] = $plugin;
    }

    public function get(string $name): ?PluginInterface {
        return $this->plugins[$name] ?? null;
    }

    public function getAll(): array {
        return $this->plugins;
    }
}
```

### 3. Modified Connection Class
```php
class OpenAICompatibleEndpointConnection {
    private PluginManager $pluginManager;

    public function __construct() {
        $this->pluginManager = new PluginManager();
    }

    public function addPlugin(PluginInterface $plugin): self {
        $this->pluginManager->add($plugin);
        $plugin->initialize($this);
        return $this;
    }

    public function __call(string $method, array $args) {
        foreach ($this->pluginManager->getAll() as $plugin) {
            if ($plugin->canHandle($method)) {
                return $plugin->handleMethodCall($method, $args);
            }
        }
        throw new \BadMethodCallException("Method $method not found");
    }
}
```

## Example Plugin: Self-Defining Functions

```php
class SelfDefiningFunctionsPlugin implements PluginInterface {
    private array $definedFunctions = [];
    private ?OpenAICompatibleEndpointConnection $connection = null;

    public function getName(): string {
        return 'self_defined_functions';
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void {
        $this->connection = $connection;
    }

    public function canHandle(string $method): bool {
        return array_key_exists($method, $this->definedFunctions) || $method === 'addNewFunction';
    }

    public function handleMethodCall(string $method, array $args): mixed {
        if ($method === 'addNewFunction') {
            $this->definedFunctions[$args[0]] = $args[1];
            return $this;
        }
        
        // Handle defined function execution
        $definition = $this->definedFunctions[$method];
        return $this->executeFunction($definition, $args);
    }

    private function executeFunction(string $definition, array $args): mixed {
        // Implementation similar to current SelfDynamicParametersConnection
    }
}
```

## Migration Path

1. **Phase 1**: Implement plugin system alongside existing functionality
2. **Phase 2**: Move existing features to plugins
3. **Phase 3**: Deprecate old implementation

## Benefits

- **Extensibility**: New features can be added without modifying core
- **Maintainability**: Each plugin has single responsibility
- **Testability**: Plugins can be tested in isolation
- **Flexibility**: Plugins can be mixed and matched as needed