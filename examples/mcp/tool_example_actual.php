<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/tools/Custom/RandomNumber.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\GetRedditHot;
use Viceroy\Tools\SearchTool;
use Viceroy\Tools\WebPageToMarkdownTool;

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection('../config.json');

// Add a tool definition to the connection
$connection->addToolDefinition(new SearchTool('https://Mitica:HanSolo1024-@search.wiro.ro', true));
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new WebPageToMarkdownTool());
$connection->addToolDefinition(new GetRedditHot());
$connection->addToolDefinition(new \Viceroy\Tools\RandomNumber());



//$connection->setParameter('n_predict', 32768);

$connection->setConnectionTimeout(3600);

// Example prompt that would trigger the tool usage
//$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI, get the url contents and summarize each page for me. Make sure to tell me the urls as well that you visit";
//$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, midday or evening.  My location is Bucharest, Romania.";
//$prompt = "What is the today's crypto fear and greed index and btc price? You must find out today's date first and get the data from that day";
//$prompt = 'Get the all the latest Reddit posts.';
//
//$prompt = 'Please extract all the news from "https://www.hotnews.ro" frontpage. Present it in a paragraph style, without tables - use bullet points. Do not output the links that are obviously advertisements or link to a different domain.';
//$prompt = "Find the latest paleontology news from today.";
// Execute the query with streaming

$prompt = 'Please give me a number between 50 and 60';
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
