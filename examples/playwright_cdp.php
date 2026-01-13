<?php
/**
 * Playwright CDP Connection Example
 *
 * This example demonstrates connecting to an already-running Chrome browser
 * using Chrome DevTools Protocol (CDP).
 *
 * Setup Instructions:
 * 1. Start Chrome with remote debugging enabled:
 *    macOS:
 *      /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222
 *
 *    Windows:
 *      "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222
 *
 *    Linux:
 *      google-chrome --remote-debugging-port=9222
 *
 * 2. Verify it's working: Visit http://localhost:9222/json - you should see JSON output
 * 3. Run this script: php examples/playwright_cdp.php
 *
 * Benefits:
 * - No need to close Chrome before running the script
 * - Works with your actual browser with all tabs and sessions
 * - Can see what the script is doing in real-time
 * - No profile conflicts or "browser already in use" errors
 *
 * Usage:
 *   php examples/playwright_cdp.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\PlaywrightTool;

// Initialize configuration
$configData = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$config = new ConfigObjects($configData);
$connection = new OpenAICompatibleEndpointConnection($config);

// Set up system message
$connection->setSystemMessage(
    "You are a web automation expert connected to the user's actual running browser via CDP.\n\n" .
    "IMPORTANT: This browser is already open and the user can see what you're doing.\n" .
    "- The user's existing tabs and sessions are available\n" .
    "- Be respectful of their open tabs\n" .
    "- Don't close tabs you didn't open\n\n" .
    "Available browser actions:\n" .
    "- navigate: Go to a URL (opens in new tab)\n" .
    "- content: Extract plain text content\n" .
    "- click: Click an element using CSS selector\n" .
    "- fill: Fill an input field with text\n" .
    "- screenshot: Take a screenshot\n" .
    "- quit: Disconnect from browser (doesn't close it)"
);

// Enable tool support
$connection->enableToolSupport();

// Create Playwright tool with CDP connection
// This connects to your already-running Chrome browser
$playwrightTool = new PlaywrightTool([
    'debug' => true,
    'connect_cdp' => 'http://localhost:9222',  // NEW: Connect via CDP
    'screenshot_dir' => 'screenshots',
    'timeout' => 30000
]);

$connection->addToolDefinition($playwrightTool);

echo "=== Playwright CDP Connection Example ===\n";
echo "This connects to your already-running Chrome browser.\n\n";

echo "Setup:\n";
echo "1. Make sure Chrome is running with: --remote-debugging-port=9222\n";
echo "2. Verify: curl http://localhost:9222/json\n\n";

// Quick check if Chrome is listening on port 9222
$fp = @fsockopen('localhost', 9222, $errno, $errstr, 2);
if (!$fp) {
    echo "ERROR: Cannot connect to Chrome on port 9222!\n\n";
    echo "Please start Chrome with:\n";
    echo "  macOS:\n";
    echo "    /Applications/Google\\ Chrome.app/Contents/MacOS/Google\\ Chrome --remote-debugging-port=9222\n\n";
    echo "  Windows:\n";
    echo "    chrome.exe --remote-debugging-port=9222\n\n";
    echo "  Linux:\n";
    echo "    google-chrome --remote-debugging-port=9222\n\n";
    exit(1);
}
fclose($fp);

echo "Connected to Chrome! ✓\n\n";

// Example: Navigate in the existing browser
echo "--- Example: Navigate to a website ---\n";

$query = "Navigate to https://example.com and tell me what you see on the page. " .
        "This will open a new tab in your existing browser.";

echo "Query: {$query}\n\n";
echo "AI: ";

try {
    $response = $connection->query($query, function($chunk, $tps) {
        echo $chunk;
        flush();
    });
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\n--- Example Complete ---\n";
echo "\nWhat's special about CDP mode:\n";
echo "1. Chrome stays open - you can still use it normally\n";
echo "2. The script opens new tabs alongside your existing ones\n";
echo "3. You can see the automation happening in real-time\n";
echo "4. No need to close Chrome or manage profiles\n";
echo "5. Works with any Chrome extensions you have installed\n\n";
echo "Configuration:\n";
echo "  'connect_cdp' => 'http://localhost:9222'  // CDP endpoint\n\n";
echo "To use a different port:\n";
echo "  1. Start Chrome: chrome.exe --remote-debugging-port=9333\n";
echo "  2. Connect with: 'connect_cdp' => 'http://localhost:9333'\n";
