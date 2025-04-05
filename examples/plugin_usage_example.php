<?php
declare(strict_types=1);

// Plugin Interface
interface PluginInterface {
    public function getName(): string;
    public function initialize(object $connection): void;
    public function canHandle(string $method): bool;
    public function handleMethodCall(string $method, array $args): mixed;
}

// Plugin Manager
class PluginManager {
    private array $plugins = [];

    public function add(PluginInterface $plugin): void {
        $this->plugins[$plugin->getName()] = $plugin;
    }

    public function getAll(): array {
        return $this->plugins;
    }
}

// Example Logger Plugin
class LoggerPlugin implements PluginInterface {
    private $connection;

    public function getName(): string {
        return 'logger';
    }

    public function initialize(object $connection): void {
        $this->connection = $connection;
    }

    public function canHandle(string $method): bool {
        return $method === 'log';
    }

    public function handleMethodCall(string $method, array $args): mixed {
        echo "LOG: " . implode(' ', $args) . PHP_EOL;
        return true;
    }
}

// Modified Connection Class
class EnhancedConnection {
    private PluginManager $plugins;

    public function __construct() {
        $this->plugins = new PluginManager();
    }

    public function addPlugin(PluginInterface $plugin): void {
        $plugin->initialize($this);
        $this->plugins->add($plugin);
    }

    public function __call(string $method, array $args) {
        foreach ($this->plugins->getAll() as $plugin) {
            if ($plugin->canHandle($method)) {
                return $plugin->handleMethodCall($method, $args);
            }
        }
        throw new \Exception("Method $method not found");
    }
}

// Usage Example
$connection = new EnhancedConnection();
$connection->addPlugin(new LoggerPlugin());

// Call plugin method
$connection->log('This is a test message');

// Output:
// LOG: This is a test message