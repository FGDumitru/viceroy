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

$searchTool = new SearchTool('http://Mitica:HanSolo1024-@search.wiro.ro');
$toolDefinition = $searchTool->getDefinition();
$connection->addToolDefinition($toolDefinition);
// Example prompt that would trigger the tool usage
$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania";

//$response = $connection->queryPost($prompt);

// Execute the query with streaming
try {
    $response = $connection->queryPost($prompt, function ($chunk, $tps) {
        echo $chunk;
        return true; // Continue streaming
    });
} catch (Exception $e) {
    var_dump($e);
}

echo $response->getRawResponse()->getBody();
echo "\n\nTool execution completed.\n";
