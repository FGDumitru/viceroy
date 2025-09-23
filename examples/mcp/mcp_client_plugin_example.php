<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Plugins\MCPClientPlugin;

/**
 * MCP Client Plugin Example
 * 
 * This example demonstrates how to use the MCPClientPlugin to interact with an MCP server.
 */

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection();

// Create and configure the MCP client plugin
$mcpClient = new MCPClientPlugin('http://localhost:8111');
$connection->registerPlugin($mcpClient);
try {
    // Get server capabilities
    echo "Fetching server capabilities...\n";
    $capabilities = $mcpClient->getServerCapabilities();
    echo "Server capabilities: " . json_encode($capabilities, JSON_PRETTY_PRINT) . "\n\n";

    // List available tools
    echo "Getting available tools...\n";
    $toolsResponse = $mcpClient->listTools();
    $tools = $toolsResponse['tools'] ?? [];
    echo "Available tools: " . json_encode($tools, JSON_PRETTY_PRINT) . "\n\n";

    // Test with example tool first
    echo "Testing example tool...\n";
    $exampleResults = $mcpClient->executeTool('Example', ['message' => 'Test message from client']);
    if (isset($exampleResults['error'])) {
        throw new Exception($exampleResults['error']['message']);
    }
    echo "Example tool results: " . json_encode($exampleResults, JSON_PRETTY_PRINT) . "\n\n";

    // Perform a search using the search tool
    echo "Searching for 'How old is the universe?'...\n";
    $searchResults = $mcpClient->executeTool('Search', [
        'query' => "How old is the universe?",
        'limit' => 5
    ]);
    if (isset($searchResults['error'])) {
        echo "Search tool error: " . $searchResults['error']['message'] . "\n";
    } else {
        echo "Search results:\n";
        if (isset($searchResults['result']['content'])) {
            foreach ($searchResults['result']['content'] as $content) {
                if ($content['type'] === 'text') {
                    echo $content['text'] . "\n";
                }
            }
        } else {
            echo "No content in search results: " . json_encode($searchResults, JSON_PRETTY_PRINT) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
