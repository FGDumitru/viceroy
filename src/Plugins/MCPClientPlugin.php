<?php

namespace Viceroy\Plugins;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Core\PluginInterface;
use Viceroy\Core\PluginType;

/**
 * MCPClientPlugin - Plugin for Model Context Protocol client functionality
 */
class MCPClientPlugin implements PluginInterface
{
    private ?OpenAICompatibleEndpointConnection $connection = null;
    private Client $httpClient;
    private string $baseUrl;
    private array $methods = [];
    private int $requestId = 1;

    public function __construct(string $baseUrl = 'http://localhost:8111') {
        $this->baseUrl = $baseUrl;
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 5.0,
        ]);

        // Register MCP methods
        //$this->registerMethod('workspace/configuration');
        $this->registerMethod('tools/list');
        $this->registerMethod('tools/call');
    }

    public function getName(): string
    {
        return 'mcp_client';
    }

    public function getType(): PluginType
    {
        return PluginType::GENERAL;
    }

    public function initialize(OpenAICompatibleEndpointConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function canHandle(string $method): bool
    {
        return in_array($method, $this->methods);
    }

    public function handleMethodCall(string $method, array $args): mixed
    {
        if (!$this->canHandle($method)) {
            throw new \BadMethodCallException("Method $method is not registered");
        }

        $params = $args[0] ?? [];
        return $this->sendRequest($method, $params);
    }

    /**
     * Register a new MCP method
     */
    public function registerMethod(string $method): void
    {
        if (!in_array($method, $this->methods)) {
            $this->methods[] = $method;
        }
    }

    /**
     * Send a request to the MCP server
     */
    private function sendRequest(string $method, array $params = []): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->requestId++
        ];

        try {
            error_log("Sending request to server: " . json_encode($request));
            $response = $this->httpClient->post('', [
                'json' => $request,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            $body = $response->getBody()->getContents();
            error_log("Received response from server: " . $body);

            $result = json_decode($body, true);
            if ($result === null || !is_array($result)) {
                return [
                    'error' => [
                        'code' => -32603,
                        'message' => 'Invalid response from server: ' . $body
                    ]
                ];
            }
            return $result;
        } catch (GuzzleException $e) {
            return [
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal client error: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Set the base URL for the MCP server
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 5.0,
        ]);
        return $this;
    }

    /**
     * Get server capabilities
     */
    /**
     * Get server capabilities including available tools
     */
    public function getServerCapabilities(): array
    {
        return $this->sendRequest('workspace/configuration');
    }

    /**
     * Get list of available tools
     */
    public function listTools(string $cursor = null): array
    {
        $params = [];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }
        return $this->sendRequest('tools/list', $params);
    }

    /**
     * Call a tool
     */
    private function callTool(string $name, array $arguments): array
    {
        $params = [
            'name' => $name,
            'arguments' => $arguments
        ];
        return $this->sendRequest('tools/call', $params);
    }

    /**
     * Search using SearchNX
     */
    public function search(string $query, int $limit = 5): array
    {
        return $this->callTool('search', [
            'query' => $query,
            'limit' => $limit
        ]);
    }
}