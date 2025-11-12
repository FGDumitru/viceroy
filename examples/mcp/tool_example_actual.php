<?php

/**
 * AdvanceSearchTool Demonstration Script
 * 
 * This script demonstrates the improved functionality of the AdvanceSearchTool,
 * showcasing its ability to return multiple comprehensive search results in a structured format.
 * 
 * Key features demonstrated:
 * - Multiple results returned (not just one)
 * - Comprehensive 3-4 paragraph summaries
 * - Filtering out of "No significant content found" results
 * - Proper JSON array structure with title, summary, and url keys
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/tools/Custom/RandomNumber.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\AdvanceSearchTool;
use Viceroy\Tools\GetCurrentDateTimeTool;
use Viceroy\Tools\GetRedditHot;
use Viceroy\Tools\RandomNumber;
use Viceroy\Tools\SearchTool;
use Viceroy\Tools\SendTelegramMessageTool;
use Viceroy\Tools\WebPageToMarkdownTool;

// Create the main connection
$connection = new OpenAICompatibleEndpointConnection('./config.json');

//$connection->setDebugMode(true);

// Add a tool definition to the connection
// The AdvanceSearchTool is configured with improved functionality:
// - Enhanced result filtering
// - Better content summarization
// - Structured JSON output
$connection->addToolDefinition(new AdvanceSearchTool(null, true));
$connection->addToolDefinition(new SearchTool());
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new WebPageToMarkdownTool(null,null,true));
$connection->addToolDefinition(new GetRedditHot());
$connection->addToolDefinition(new RandomNumber());
$connection->addToolDefinition(new SendTelegramMessageTool());

$connection->setConnectionTimeout(3600);



// $prompt = "Use only the advance_search tool to find the latest paleontology news from today. Request 5 results and present ALL of them in a JSON array format, with each result having keys title, summary, and url. Do not output anything else than the JSON array (no ```). Start directly with \"[{\".";

// Alternative example prompts (commented out for reference):
// $prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI, get the url contents and summarize each page for me. Make sure to tell me the urls as well that you visit";
// $prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, midday or evening.  My location is Bucharest, Romania.";
// $prompt = "What is the today's crypto fear and greed index, bitcoin dominance and btc price in USD? For fear and greed index use https://api.alternative.me/fng/ (API json format) to get the latest crypto values for fear and greed.You may get the bitcoin dominance from https://coincodex.com/bitcoin-dominance/ For bitcoin price use  the url https://coinmarketcap.com/ (html). If you have any issues getting those values then search for them (html). Use the available tools to fetch the data. The final response should be a JSON object with the keys: 'btc_usd', 'btc_d', and 'fear_greed' (for fear and greed). The JSON object should be encompassed between XML type 'response' tags. The output should be something like <response>(JSON object here)</response>.";
$prompt = 'Get the latest hot Reddit posts. Present all the available information in a JSON array object.  Do not output any other addition explication pre or post preamble. Prefix the json object with triple ticks followed immediately by the "json" string and suffix it by another triple ticks.';
//  $prompt = 'Please extract 20 newest news by visiting the "https://www.hotnews.ro/rss" rss feed. Then visit all links from RSS feed and get a summary of 2-3 paragraphs. Present it in a JSON object tyle (using keys title, summary and url). Extract 10 top news. I have ample time to wait, so no hurry. You may get the RSS file with';
$prompt = 'Get the newest 20 localllama subreddit Reddit posts. order by the newest. If the post is a question then try to infer and select the best answer(s) from the comments section. After you finish your work then send all the posts output to the user via telegram.';

// $prompt = 'Generate a random number between 10 and 873.';

 $prompt = 'Visit the Hotnews website (here is the direct RSS feed: "https://www.hotnews.ro/rss") and get the top 20 news titles with their links. Filter out the ads that may appear as news. Create a summary of 2-3 paragraphs for each link/title. Send me the results (title, summary, link)  via telegram message. For each title, add a emoji prefix that is in relation with the news topic (for example a politics related news should have a 🗳️ emoji, a technology related news should have a 🤖 emoji, a health related news should have a 🏥 emoji etc.).';

// $connection->setParameter('temp', 0.8);
// $connection->setParameter('seed', 0);
$useStreaming = true;

try {
    if ($useStreaming) {
        echo "EXECUTING SEARCH QUERY (streaming mode):\n";
        echo "----------------------------------------\n";
        $response = $connection->queryPost($prompt, function ($chunk, $tps) {
            echo $chunk; // Display each chunk as it arrives
            return true; // Continue streaming
        });
    } else {
        echo "EXECUTING SEARCH QUERY (non-streaming mode):\n";
        echo "-------------------------------------------\n";
        $response = $connection->queryPost($prompt);
        echo $response->getThinkContent() . "\n";
        echo $response->getLlmResponse();
    }
} catch (Exception $e) {
    echo "Error occurred during search execution:\n";
    var_dump($e);
}


// Uncomment to see query statistics
// var_dump($connection->getQueryStats());
