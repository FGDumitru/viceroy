<?php

namespace Viceroy\Core;

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