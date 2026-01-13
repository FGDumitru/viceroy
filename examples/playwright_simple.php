<?php
/**
 * Simple PlaywrightTool Example
 *
 * This example demonstrates basic usage of the PlaywrightTool
 * to navigate to a website and extract content.
 *
 * Usage:
 *   php examples/playwright_simple.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\PlaywrightTool;

// Configuration
const TARGET_URL = 'https://www.microsoft.com';

// Initialize configuration
$configData = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$config = new ConfigObjects($configData);
$connection = new OpenAICompatibleEndpointConnection($config);

// Set up the conversation
$connection->setSystemMessage(
    "You are a helpful web research assistant. " .
    "You can use the browser_control tool to navigate websites and extract information."
);

// Enable tool support and add PlaywrightTool
// Note: Set 'headed' => false for headless mode (faster, no visible window)
$connection->enableToolSupport();
$connection->addToolDefinition(new PlaywrightTool(['debug' => false, 'headed' => true]));

// Simple query that will use the PlaywrightTool
$query = "Navigate to " . TARGET_URL . " and tell me what the page title is.";

echo "=== Simple PlaywrightTool Example ===\n\n";
echo "Query: {$query}\n\n";
echo "Processing...\n\n";

// Execute the query with streaming
try {
    $response = $connection->query($query, function($chunk, $tps) {
        echo $chunk;
        flush();
    });

    echo "\n\n=== Response Complete ===\n";
    // Check if response is a Response object before calling methods
    if (is_object($response) && method_exists($response, 'wasStreamed')) {
        echo "Was streamed: " . ($response->wasStreamed() ? 'Yes' : 'No') . "\n";
    } else {
        echo "Response type: " . gettype($response) . "\n";
    }
} catch (\Throwable $e) {
    echo "\n\n=== Query Complete (with exception) ===\n";
    echo "Note: " . $e->getMessage() . "\n";
}

// Alternatively, you can use the tool directly without LLM:
echo "\n\n=== Direct Tool Usage ===\n\n";

// Create tool with headed mode (you'll see the browser window!)
// Set 'headed' => false for headless mode
$tool = new PlaywrightTool(['debug' => false, 'headed' => true]);

// Navigate to a website
echo "Navigating to " . TARGET_URL . "...\n";
$result = $tool->execute(
    [
        'action' => 'navigate',
        'url' => TARGET_URL
    ],
    $config
);

if (!$result['isError']) {
    echo "✓ Navigation successful\n";
    foreach ($result['content'] as $content) {
        echo $content['text'] . "\n";
    }
} else {
    echo "✗ Error: " . $result['content'][0]['text'] . "\n";
}

// Get page content
echo "\nExtracting page content...\n";
$result = $tool->execute(['action' => 'content'], $config);

if (!$result['isError']) {
    $data = $result['data'] ?? [];
    echo "Title: " . ($data['title'] ?? 'Unknown') . "\n";
    echo "URL: " . ($data['url'] ?? 'Unknown') . "\n";
    echo "Content length: " . strlen($data['content'] ?? '') . " characters\n";

    // Show first 200 characters of content
    $content = $data['content'] ?? '';
    echo "\nContent preview:\n";
    echo substr($content, 0, 200) . "...\n";
} else {
    echo "✗ Error: " . $result['content'][0]['text'] . "\n";
}

// Wait for network to be idle before taking screenshot (max 10 seconds)
echo "\nWaiting for network idle...\n";
$result = $tool->execute(
    [
        'action' => 'wait',
        'timeout' => 10000  // Max 10 seconds
    ],
    $config
);

if (!$result['isError']) {
    echo "✓ Wait complete\n";
} else {
    echo "✗ Error: " . $result['content'][0]['text'] . "\n";
}

// Take a screenshot
echo "\nTaking screenshot...\n";
$result = $tool->execute(
    [
        'action' => 'screenshot',
        'screenshot_filename' => 'example_com'
    ],
    $config
);

if (!$result['isError']) {
    echo "✓ Screenshot saved\n";
    $data = $result['data'] ?? [];
    echo "Path: " . ($data['screenshot_path'] ?? 'Unknown') . "\n";
} else {
    echo "✗ Error: " . $result['content'][0]['text'] . "\n";
}

// Clean up - explicitly close the browser session
echo "\nClosing browser session...\n";
$tool->execute(['action' => 'quit'], $config);

echo "\n=== Example Complete ===\n";
