<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MCPClient {
    private Client $httpClient;
    private string $baseUrl;
    private int $requestId = 1;

    public function __construct(string $baseUrl) {
        $this->baseUrl = $baseUrl;
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 5.0,
        ]);
    }

    /**
     * Send a request to the MCP server
     */
    private function sendRequest(string $method, array $params = []): array {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->requestId++
        ];

        try {
            $response = $this->httpClient->post('', [
                'json' => $request,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            return json_decode($response->getBody()->getContents(), true);
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
     * Get server capabilities
     */
    public function getServerCapabilities(): array {
        return $this->sendRequest('workspace/configuration');
    }

    /**
     * Get completions from the server
     */
    public function getCompletions(string $documentUri, int $line, int $character): array {
        $params = [
            'textDocument' => [
                'uri' => $documentUri
            ],
            'position' => [
                'line' => $line,
                'character' => $character
            ]
        ];

        return $this->sendRequest('textDocument/completion', $params);
    }
}

// Example usage
try {
    // Create client instance
    $client = new MCPClient('http://localhost:8111'); // Updated port

    // Get server capabilities
    echo "Fetching server capabilities...\n";
    $capabilities = $client->getServerCapabilities();
    echo "Server capabilities: " . json_encode($capabilities, JSON_PRETTY_PRINT) . "\n\n";

    // Request completions
    echo "Requesting completions...\n";
    $completions = $client->getCompletions(
        'file:///test.php', // Example document URI
        0,                  // Line number
        0                   // Character position
    );
    echo "Completion results: " . json_encode($completions, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}