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
    $tools = $mcpClient->listTools();
    echo "Available tools: " . json_encode($tools, JSON_PRETTY_PRINT) . "\n\n";

    // Perform a search using the search tool
    echo "Searching for 'How old is the universe?'...\n";
    $searchResults = $mcpClient->search("How old is the universe?", 5);
    if (isset($searchResults['error'])) {
        throw new Exception($searchResults['error']['message']);
    }
    echo "Search results:\n";
    foreach ($searchResults['result']['content'] as $content) {
        if ($content['type'] === 'text') {
            echo $content['text'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}