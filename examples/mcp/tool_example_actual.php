<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\SearchTool;
use Viceroy\Tools\WebPageToMarkdownTool;

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection('../config.json');

// Add a tool definition to the connection
$connection->addToolDefinition(new SearchTool());
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new WebPageToMarkdownTool());

$connection->setConnectionTimeout(864000);

// Example prompt that would trigger the tool usage
$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI, get the url contents and summarize each page for me. Make sure to tell me the urls as well that you visit";
//$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania.";
//$prompt = "What is the today's crypto fear and greed index and btc price? You must find out today's date first and get the data from that day";
$prompt = 'Get the all Reddit posts titles and their comments links from https://old.reddit.com/hot .  Order the post by the newest first. Important: Show me ALL the links, and their post time , don\'t skip any link! You may extract the links manually.';
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

//var_dump($connection->getQueryStats());