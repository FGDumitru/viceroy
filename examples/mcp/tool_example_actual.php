<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\SearchTool;

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection('../config.json');

// Register the MCP client plugin (connecting to the running server at localhost:8111)
// $connection->registerMCP('http://localhost:8111');

// Enable tool support
$connection->enableToolSupport();

// Add a tool definition to the connection
$timeTool = new GetCurrentDateTimeTool();
$toolDefinition = $timeTool->getDefinition();
$connection->addToolDefinition($toolDefinition);

$searchTool = new SearchTool();
$toolDefinition = $searchTool->getDefinition();
$connection->addToolDefinition($toolDefinition);

$connection->setConnectionTimeout(864000);

// Example prompt that would trigger the tool usage
$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI and summarize them for me.";

// Execute the query with streaming
$useStreaming = true;

try {
    if ($useStreaming) {
        $response = $connection->queryPost($prompt, function ($chunk, $tps) {
            echo $chunk; // Display each chunk as it arrives
            return true; // Continue streaming
        });
    } else {
        $response = $connection->queryPost($prompt);
        echo $response->getThinkContent() . "\n";
        echo $response->getLlmResponse();
    }
} catch (Exception $e) {
    var_dump($e);
}

echo "\n\nTool execution completed.\n";
