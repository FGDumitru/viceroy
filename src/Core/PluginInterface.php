<?php

namespace Viceroy\Core;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

interface PluginInterface {
    public function getName(): string;
    public function initialize(OpenAICompatibleEndpointConnection $connection): void;
    public function canHandle(string $method): bool;
    public function handleMethodCall(string $method, array $args): mixed;
}