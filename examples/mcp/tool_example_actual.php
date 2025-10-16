<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/tools/Custom/RandomNumber.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\GetRedditHot;
use Viceroy\Tools\AdvanceSearchTool;
use Viceroy\Tools\RandomNumber;
use Viceroy\Tools\SearchTool;
use Viceroy\Tools\WebPageToMarkdownTool;

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection('./config.json');

//$connection->setDebugMode(true);

// Add a tool definition to the connection
$connection->addToolDefinition(new AdvanceSearchTool(null, true));
$connection->addToolDefinition(new SearchTool());
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new WebPageToMarkdownTool());
//$connection->addToolDefinition(new GetRedditHot());
//$connection->addToolDefinition(new RandomNumber());



//$connection->setParameter('n_predict', 32768);

$connection->setConnectionTimeout(3600);

// Example prompt that would trigger the tool usage
//$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI, get the url contents and summarize each page for me. Make sure to tell me the urls as well that you visit";
//$prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, midday or evening.  My location is Bucharest, Romania.";
$prompt = "What is the today's crypto fear and greed index, bitcoin dominance and btc price in USD? For fear and greed index use https://api.alternative.me/fng/ (API json format) to get the latest crypto values for fear and greed.You may get the bitcoin dominance from https://coincodex.com/bitcoin-dominance/ For bitcoin price use  the url https://coinmarketcap.com/ (html). If you have any issues getting those values then search for them (html). Use the available tools to fetch the data. The final response should be a JSON object with the keys: 'btc_usd', 'btc_d', and 'fg' (for fear and greed). The JSON object should be encompassed between XML type 'response' tags. The output should be something like <response>(JSON object here)</response>.";
//$prompt = 'Get the all the latest Reddit posts.';
//
//$prompt = 'Please extract all the news from "https://www.hotnews.ro" frontpage. Present it in a paragraph style, without tables - use bullet points. Do not output the links that are obviously advertisements or link to a different domain.';
//$prompt = "Use only the advance_search tool to find the latest paleontology news from today (in detail). Do not use any other tools.";
// Execute the query with streaming

//$prompt = 'Please give me a number between 50 and 60';
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
