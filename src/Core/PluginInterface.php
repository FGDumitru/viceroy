<?php

namespace Viceroy\Core;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

enum PluginType: string {
    case GENERAL = 'General';
    case BEFORE_API_CALL = 'BeforeApiCall';
}

interface PluginInterface {
    public function getName(): string;
    public function getType(): PluginType;
    public function initialize(OpenAICompatibleEndpointConnection $connection): void;
    public function canHandle(string $method): bool;
    public function handleMethodCall(string $method, array $args): mixed;
}