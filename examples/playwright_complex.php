<?php
/**
 * Complex PlaywrightTool Example
 *
 * This example demonstrates advanced usage of the PlaywrightTool
 * for multi-step web interactions, including:
 * - Multi-turn conversation with browser control
 * - Form filling and submission
 * - Navigation and content extraction
 * - Combining with other tools (search)
 * - LLM-orchestrated browser automation
 *
 * Usage:
 *   php examples/playwright_complex.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\PlaywrightTool;
use Viceroy\Tools\SearchTool;
use Viceroy\Tools\WebPageToMarkdownTool;

// Configuration
const HACKER_NEWS_URL = 'https://news.ycombinator.com';
const HTTPBIN_URL = 'https://httpbin.org';

// Initialize configuration
$configData = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$config = new ConfigObjects($configData);
$connection = new OpenAICompatibleEndpointConnection($config);

// Set up the conversation with a detailed system prompt
$connection->setSystemMessage(
    "You are an advanced web research assistant with browser automation capabilities. " .
    "You can control a headless browser to:\n" .
    "- Navigate to websites and extract content\n" .
    "- Fill out forms and submit them\n" .
    "- Click elements and interact with pages\n" .
    "- Take screenshots of pages\n" .
    "- Wait for network idle before proceeding\n\n" .
    "When a user asks for information that requires web browsing, use the browser_control tool. " .
    "Break down complex tasks into steps and explain what you're doing at each step.\n\n" .
    "IMPORTANT - Page Content Format:\n" .
    "The navigate action returns an ARIA accessibility snapshot, not raw HTML. This is a structured " .
    "tree that shows:\n" .
    "- Element roles (heading, link, button, paragraph, etc.)\n" .
    "- Accessible names and labels\n" .
    "- ARIA states (checked, disabled, expanded, selected)\n" .
    "- Unique [ref=eX] identifiers for clicking elements\n" .
    "- Level information (for headings)\n\n" .
    "Example ARIA output:\n" .
    "  - generic [ref=e2]:\n" .
    "    - heading \"Welcome\" [level=1] [ref=e3]\n" .
    "    - paragraph [ref=e4]: Some text here\n" .
    "    - link \"Click me\" [ref=e5] [cursor=pointer]\n\n" .
    "To interact with elements, use their CSS selector or the ref if supported.\n\n" .
    "WIKIPEDIA SELECTORS (CRITICAL):\n" .
    "Wikipedia search input has id=\"searchInput\" (NOT \"search\"), name=\"search\", class=\"mw-searchInput\"\n" .
    "- CORRECT: input[name=\"search\"], #searchInput, .mw-searchInput\n" .
    "- WRONG: input#search, #search (do NOT exist!)\n\n" .
    "CRITICAL - FORM SUBMISSION:\n" .
    "When filling search forms, the tool detects submit buttons:\n" .
    "1. If a submit button EXISTS: You MUST click it explicitly after filling\n" .
    "2. If NO submit button exists: The tool auto-presses Enter\n" .
    "3. FALLBACK: If clicking the button fails, use evaluate to dispatch Enter key event\n\n" .
    "Always check the fill response to see if a button was detected!\n\n" .
    "Available browser actions:\n" .
    "- navigate: Go to a URL (returns ARIA snapshot)\n" .
    "- snapshot: Get ARIA or HTML snapshot of current page\n" .
    "- content: Extract plain text content from the current page\n" .
    "- click: Click an element using CSS selector\n" .
    "- fill: Fill an input field with text (response indicates if button detected)\n" .
    "- screenshot: Take a screenshot of the current page\n" .
    "- wait: Wait for network idle (up to specified timeout)"
);

// Enable tool support and add multiple tools
// Note: Set 'headed' => false for headless mode (faster, no visible window)
$connection->enableToolSupport();
$connection->addToolDefinition(new PlaywrightTool(['debug' => true, 'headed' => true, 'screenshot_dir' => 'screenshots']));
$connection->addToolDefinition(new SearchTool());
$connection->addToolDefinition(new WebPageToMarkdownTool());

echo "=== Complex PlaywrightTool Example ===\n";
echo "This example demonstrates advanced browser automation with LLM orchestration.\n\n";

// Example 1: Research Task with Multiple Steps
echo "--- Example 1: Multi-Step Research Task ---\n";
echo "Task: Search for 'PHP latest version', visit the result, and extract all available information\n\n";

$query1 = "Search for the latest PHP version and tell me what's new in the latest release.";
echo "Query: {$query1}\n\n";
echo "Processing...\n\n";

$response1 = $connection->query($query1, function($chunk, $tps) {
    echo $chunk;
    flush();
});

echo "\n\n--- Example 1 Complete ---\n\n";

// Example 2: Direct Tool Usage - Web Scraping Workflow
echo "\n--- Example 2: Direct Web Scraping Workflow ---\n";
echo "Demonstrating manual tool orchestration for precise control\n\n";

// Create tool with headed mode (you'll see the browser window!)
// Set 'headed' => false for headless mode
$tool = new PlaywrightTool([
    'debug' => true,
    'headed' => true,
    'screenshot_dir' => 'screenshots',
    'timeout' => 30000
]);

// Step 1: Navigate to a news website
echo "Step 1: Navigating to Hacker News...\n";
$result = $tool->execute(
    [
        'action' => 'navigate',
        'url' => HACKER_NEWS_URL
    ],
    $config
);

if ($result['isError']) {
    echo "✗ Navigation failed: " . $result['content'][0]['text'] . "\n";
    exit(1);
}
echo "✓ Navigation successful\n";

// Step 2: Extract page content
echo "\nStep 2: Extracting page content...\n";
$result = $tool->execute(['action' => 'content'], $config);

if (!$result['isError']) {
    $data = $result['data'];
    echo "✓ Content extracted\n";
    echo "  Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "  Content: " . strlen($data['content'] ?? '') . " characters\n";

    // Store content for LLM processing
    $pageContent = $data['content'] ?? '';
    $pageTitle = $data['title'] ?? '';

    echo "\n  (Headlines will be extracted by LLM in Example 3)\n";
}

// Step 3: Wait for network idle before taking screenshot
echo "\nStep 3: Waiting for network idle...\n";
$result = $tool->execute(
    [
        'action' => 'wait',
        'timeout' => 10000
    ],
    $config
);

if (!$result['isError']) {
    echo "✓ Wait complete\n";
}

// Step 4: Take a screenshot
echo "\nStep 4: Taking screenshot...\n";
$result = $tool->execute(
    [
        'action' => 'screenshot',
        'screenshot_filename' => 'hacker_news_' . time()
    ],
    $config
);

if (!$result['isError']) {
    $data = $result['data'];
    echo "✓ Screenshot saved: " . ($data['screenshot_path'] ?? 'Unknown') . "\n";
}

echo "\n--- Example 2 Complete (4 steps) ---\n\n";

// Example 3: Multi-Turn Conversation with Context
echo "\n--- Example 3: Multi-Turn Conversation with Context ---\n";
echo "Demonstrating conversation memory and follow-up queries\n\n";

// First, use LLM to extract headlines from the content we just retrieved
echo "Part A: LLM-Driven Content Extraction\n";
echo "Task: Extract top 5 headlines from Hacker News page content\n\n";

$connection->clear();
$connection->setSystemMessage(
    "You are a content analysis assistant. You will be given page content (either plain text " .
    "or ARIA accessibility snapshots) and should extract and list information naturally.\n\n" .
    "When given ARIA format:\n" .
    "- Look for roles like 'heading', 'link', 'paragraph', 'listitem'\n" .
    "- Extract accessible names (text in quotes)\n" .
    "- Pay attention to [level=X] for heading hierarchy\n" .
    "- Ignore [ref=eX] identifiers as they're just for element interaction\n\n" .
    "When given plain text:\n" .
    "- Parse the content intelligently and present the results clearly."
);

$query2a = "Here is the content from a Hacker News page:\n\n" .
           substr($pageContent, 0, 3000) . "\n\n" .
           "Extract and list the top 5 article headlines from this content.";

echo "Query: Extracting headlines...\n\n";
echo "AI: ";

try {
    $response2a = $connection->query($query2a, function($chunk, $tps) {
        echo $chunk;
        flush();
    });

    echo "\n\n";
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

// Part B: Browser navigation with context
echo "Part B: Browser Navigation with Context\n";
echo "Task: Navigate to Wikipedia and extract information\n\n";

$connection->clear();
$connection->setSystemMessage(
    "You are a web automation expert. Use the browser_control tool to help users " .
    "interact with websites. Explain each step clearly\n\n" .
    "IMPORTANT: The navigate action returns ARIA accessibility snapshots, not HTML. " .
    "Use the content action to get plain text if you need readable content.\n\n" .
    "WIKIPEDIA SELECTORS (CRITICAL - MUST USE CORRECT SELECTORS):\n" .
    "Wikipedia search input has id=\"searchInput\" (NOT \"search\"), name=\"search\", class=\"mw-searchInput\"\n" .
    "- CORRECT: input[name=\"search\"], #searchInput, .mw-searchInput\n" .
    "- WRONG: input#search, #search (these do NOT exist!)\n" .
    "- Search button: button:has-text(\"Search\"), .cdx-search-input__end-button\n\n" .
    "CRITICAL - FORM SUBMISSION:\n" .
    "When filling forms, the tool smartly detects submit buttons:\n" .
    "- If submit button EXISTS: You MUST click it explicitly (check the fill response!)\n" .
    "- If NO submit button: Tool auto-presses Enter to submit\n" .
    "- FALLBACK: If click fails, use evaluate to dispatch Enter key event on the input\n\n" .
    "Example search workflow:\n" .
    "1. Navigate to site\n" .
    "2. Fill search input: {action: fill, selector: 'input[name=search]', text: 'query'}\n" .
    "3. Check response - if it says 'submit button exists', click it\n" .
    "4. If click fails, fallback: {action: evaluate, code: 'document.querySelector(\\'input[name=\\\\\"search\\\\\"]\\').dispatchEvent(new KeyboardEvent(\\'keydown\\', {key: \\'Enter\\'}));'}\n" .
    "5. Use content action to read results"
);

echo "User: Can you help me find information about a specific topic?\n\n";
$query2 = "Navigate to Wikipedia and search for information about 'Artificial Intelligence'. " .
          "Tell me the first paragraph of the article.";

echo "AI: ";
try {
    $response2 = $connection->query($query2, function($chunk, $tps) {
        echo $chunk;
        flush();
    });

    // Add context and follow up
    if (is_object($response2) && method_exists($response2, 'getLlmResponse')) {
        $connection->addAssistantMessage($response2->getLlmResponse());
    }
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\nUser: That's interesting. Now can you extract the main headings from that page?\n\n";
echo "AI: ";

$query3 = "From the Wikipedia page we just visited, extract and list all the main section headings.";

try {
    $response3 = $connection->query($query3, function($chunk, $tps) {
        echo $chunk;
        flush();
    });
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\n--- Example 3 Complete ---\n\n";

// Example 4: Error Handling and Recovery
echo "\n--- Example 4: Error Handling and Recovery ---\n";
echo "Demonstrating robust error handling in browser automation\n\n";

$errorDemoScenarios = [
    [
        'name' => 'Invalid URL',
        'action' => 'navigate',
        'params' => ['url' => 'not-a-valid-url'],
        'expected_error' => true
    ],
    [
        'name' => 'Non-existent element',
        'action' => 'click',
        'params' => ['selector' => '#non-existent-element-12345'],
        'expected_error' => true
    ],
    [
        'name' => 'Valid navigation',
        'action' => 'navigate',
        'params' => ['url' => HTTPBIN_URL],
        'expected_error' => false
    ]
];

foreach ($errorDemoScenarios as $scenario) {
    echo "Testing: {$scenario['name']}\n";
    echo "  Action: {$scenario['action']}\n";

    $params = array_merge(['action' => $scenario['action']], $scenario['params']);
    $result = $tool->execute($params, $config);

    if ($scenario['expected_error']) {
        if ($result['isError']) {
            echo "  ✓ Error correctly handled: " . substr($result['content'][0]['text'], 0, 80) . "...\n";
        } else {
            echo "  ✗ Expected an error but got success\n";
        }
    } else {
        if (!$result['isError']) {
            echo "  ✓ Action completed successfully\n";
        } else {
            echo "  ✗ Unexpected error: " . $result['content'][0]['text'] . "\n";
        }
    }
    echo "\n";
}

echo "--- Example 4 Complete ---\n\n";

// Example 5: Combining Tools
echo "\n--- Example 5: Combining Multiple Tools ---\n";
echo "Demonstrating tool composition for complex workflows\n\n";

$connection->clear();
$connection->setSystemMessage(
    "You have access to multiple tools: browser_control for web automation, " .
    "visit_webpage for fetching page content, and search for web search. " .
    "Use them together to accomplish complex tasks efficiently.\n\n" .
    "NOTE: browser_control navigate returns ARIA accessibility snapshots. " .
    "Use content action for plain text extraction.\n\n" .
    "CRITICAL - FORM SUBMISSION with browser_control:\n" .
    "When filling search forms, check the fill response:\n" .
    "- If 'submit button exists': Click the button explicitly\n" .
    "- If no mention of button: Form was auto-submitted with Enter\n" .
    "- FALLBACK: If click fails, use evaluate to dispatch Enter key event on the input field"
);

$query5 = "Search for 'best PHP frameworks 2025', then use browser_control to visit " .
          "the first result and extract the top 5 frameworks mentioned.";

echo "Query: {$query5}\n\n";
echo "Processing...\n\n";

$response5 = $connection->query($query5, function($chunk, $tps) {
    echo $chunk;
    flush();
});

echo "\n\n--- Example 5 Complete ---\n\n";

// Summary
echo "=== All Examples Complete ===\n\n";
echo "Key takeaways:\n";
echo "1. PlaywrightTool can be used directly or via LLM orchestration\n";
echo "2. Multi-step workflows are possible through conversation context\n";
echo "3. Error handling is built into the tool execution\n";
echo "4. Tools can be combined for complex automation tasks\n";
echo "5. The LLM can intelligently decide when and how to use browser automation\n\n";

echo "Screenshots saved to: " . realpath('screenshots') . "\n";
