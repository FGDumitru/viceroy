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
// The AdvanceSearchTool is configured with improved functionality:
// - Enhanced result filtering
// - Better content summarization
// - Structured JSON output
$connection->addToolDefinition(new AdvanceSearchTool(null, false));
$connection->addToolDefinition(new SearchTool());
$connection->addToolDefinition(new GetCurrentDateTimeTool());
$connection->addToolDefinition(new WebPageToMarkdownTool());
$connection->addToolDefinition(new GetRedditHot());
$connection->addToolDefinition(new RandomNumber());

$connection->setConnectionTimeout(3600);

// =================================================================
// DEMONSTRATION OF IMPROVED ADVANCE SEARCH FUNCTIONALITY
// =================================================================

echo "=== ADVANCE SEARCH TOOL DEMONSTRATION ===\n";
echo "Demonstrating improved search functionality with multiple results\n";
echo "Expected output: JSON array with 5 paleontology news items\n";
echo "Each item contains: title, summary (3-4 paragraphs), and url\n\n";

// Primary demonstration prompt - keeping the paleontology news example as requested
// This prompt showcases:
// 1. Requesting multiple results (5)
// 2. Specific JSON array format with required keys
// 3. Comprehensive summaries (the tool now generates 3-4 paragraph summaries)
// 4. Proper filtering of low-quality results
$prompt = "Use only the advance_search tool to find the latest paleontology news from today. Request 5 results and present ALL of them in a JSON array format, with each result having keys title, summary, and url. Do not output anything else than the JSON array (no ```). Start directly with \"[{\".";

// Alternative example prompts (commented out for reference):
// $prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, mid-day or evening.  My location is Bucharest, Romania. Then search for the latest news in Romania related to technology and AI, get the url contents and summarize each page for me. Make sure to tell me the urls as well that you visit";
// $prompt = "Based on the current date and time, tell me in which yearly quarter are we right now and if right now it's night, morning, midday or evening.  My location is Bucharest, Romania.";
// $prompt = "What is the today's crypto fear and greed index, bitcoin dominance and btc price in USD? For fear and greed index use https://api.alternative.me/fng/ (API json format) to get the latest crypto values for fear and greed.You may get the bitcoin dominance from https://coincodex.com/bitcoin-dominance/ For bitcoin price use  the url https://coinmarketcap.com/ (html). If you have any issues getting those values then search for them (html). Use the available tools to fetch the data. The final response should be a JSON object with the keys: 'btc_usd', 'btc_d', and 'fg' (for fear and greed). The JSON object should be encompassed between XML type 'response' tags. The output should be something like <response>(JSON object here)</response>.";
$prompt = 'Get the all the latest Reddit posts.';
//  $prompt = 'Please extract 20 newest news by visiting the "https://www.hotnews.ro/rss" rss feed. Then visit all links from RSS feed and get a summary of 2-3 paragraphs. Present it in a JSON object tyle (using keys title, summary and url). Extract 10 top news. I have ample time to wait, so no hurry. You may get the RSS file with';

// Set parameters for consistent results
$connection->setParameter('temp', 0.8);
$connection->setParameter('seed', 0);
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

echo "\n\n=== DEMONSTRATION COMPLETED ===\n";
echo "The above output demonstrates the improved AdvanceSearchTool functionality:\n";
echo "- Multiple comprehensive results returned\n";
echo "- Detailed 3-4 paragraph summaries\n";
echo "- Proper JSON array structure\n";
echo "- Filtering of low-quality content\n";
echo "- Consistent format with title, summary, and url keys\n";

// Uncomment to see query statistics
//var_dump($connection->getQueryStats());
