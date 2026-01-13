<?php
/**
 * Playwright Native Browser Example
 *
 * This example demonstrates using PlaywrightTool with your actual browser profile.
 * This means you'll have access to:
 * - Your existing cookies and logins (GitHub, Google, etc.)
 * - Saved passwords and autofill
 * - Browser extensions
 * - Bookmarks and history
 *
 * Usage:
 *   php examples/playwright_native.php
 *
 * IMPORTANT: Close your Chrome browser before running this script!
 * Playwright cannot connect if Chrome is already running.
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
    "You are a web automation expert. Use the browser_control tool to help users " .
    "interact with websites using their actual browser profile (with existing cookies/logins).\n\n" .
    "IMPORTANT: This browser has the user's actual profile, so:\n" .
    "- They will be logged into sites they normally use\n" .
    "- Their cookies and sessions are available\n" .
    "- Don't log out of any services\n" .
    "- Be respectful of their saved data\n\n" .
    "WIKIPEDIA SELECTORS (CRITICAL):\n" .
    "Wikipedia search input has id=\"searchInput\" (NOT \"search\"), name=\"search\", class=\"mw-searchInput\"\n" .
    "- CORRECT: input[name=\"search\"], #searchInput, .mw-searchInput\n" .
    "- WRONG: input#search, #search (do NOT exist!)\n\n" .
    "Available browser actions:\n" .
    "- navigate: Go to a URL (returns ARIA snapshot)\n" .
    "- content: Extract plain text content\n" .
    "- click: Click an element using CSS selector\n" .
    "- fill: Fill an input field with text\n" .
    "- screenshot: Take a screenshot\n" .
    "- wait: Wait for page to load\n" .
    "- quit: Close the browser"
);

// Enable tool support
$connection->enableToolSupport();

// Create Playwright tool with native browser profile
// Note: 'headed' must be true when using a profile (for now)
$playwrightTool = new PlaywrightTool([
    'debug' => true,
    'headed' => true,           // Show browser window
    'use_profile' => true,      // NEW: Use actual browser profile
    'channel' => 'chrome',      // NEW: Use installed Chrome (or 'msedge' for Edge)
    'screenshot_dir' => 'screenshots',
    'timeout' => 30000
]);

$connection->addToolDefinition($playwrightTool);

echo "=== Playwright Native Browser Example ===\n";
echo "This example uses your ACTUAL Chrome browser with your profile.\n";
echo "You'll have access to your existing cookies, logins, and sessions.\n\n";

echo "IMPORTANT: Make sure Chrome is CLOSED before running this!\n\n";

// Example 1: Check if you're logged into GitHub
echo "--- Example 1: Check GitHub Login Status ---\n";
echo "Let's see if you're logged into GitHub...\n\n";

$query1 = "Navigate to https://github.com and check if I'm logged in. " .
          "Look for signs that show my login status (like username, notifications, etc.).";

echo "Query: {$query1}\n\n";
echo "AI: ";

try {
    $response1 = $connection->query($query1, function($chunk, $tps) {
        echo $chunk;
        flush();
    });

    if (is_object($response1) && method_exists($response1, 'getLlmResponse')) {
        $connection->addAssistantMessage($response1->getLlmResponse());
    }
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\n--- Example 2: Visit Your Recently Visited Sites ---\n";

$query2 = "Navigate to https://news.ycombinator.com and tell me the top 5 headlines. " .
          "Since we're using my actual browser profile, the site will show my preferences.";

echo "Query: {$query2}\n\n";
echo "AI: ";

try {
    $response2 = $connection->query($query2, function($chunk, $tps) {
        echo $chunk;
        flush();
    });

    if (is_object($response2) && method_exists($response2, 'getLlmResponse')) {
        $connection->addAssistantMessage($response2->getLlmResponse());
    }
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\n--- Example 3: Demonstrate Profile Benefits ---\n";

$query3 = "Navigate to https://www.wikipedia.org and search for 'Web browser'. " .
          "Click the first result and extract the first paragraph. " .
          "Notice that the site may remember your preferences from the actual browser.";

echo "Query: {$query3}\n\n";
echo "AI: ";

try {
    $response3 = $connection->query($query3, function($chunk, $tps) {
        echo $chunk;
        flush();
    });
} catch (\Throwable $e) {
    echo "\n[Error: {$e->getMessage()}]\n";
}

echo "\n\n--- Taking a Screenshot ---\n";
echo "Let's capture the current state of the browser...\n\n";

try {
    $screenshotResult = $playwrightTool->execute([
        'action' => 'screenshot',
        'screenshot_filename' => 'native_browser_example'
    ], $config);

    if (!$screenshotResult['isError']) {
        echo "Screenshot saved!\n";
    }
} catch (\Throwable $e) {
    echo "[Error taking screenshot: {$e->getMessage()}]\n";
}

echo "\n\n=== Examples Complete ===\n";
echo "\nWhat just happened:\n";
echo "1. We used your ACTUAL Chrome browser (not a fresh incognito window)\n";
echo "2. Your cookies, sessions, and logins were available\n";
echo "3. Sites recognized you as the same user from your normal browsing\n";
echo "\nBenefits of using your native profile:\n";
echo "- No need to log in again\n";
echo "- Your preferences and settings are preserved\n";
echo "- Browser extensions are available\n";
echo "- Autofill and saved passwords work\n";
echo "\nConfiguration used:\n";
echo "- headed: true (required for profile mode)\n";
echo "- use_profile: true (use actual browser profile)\n";
echo "- channel: 'chrome' (use installed Chrome)\n";
echo "\nTo use Edge instead of Chrome:\n";
echo "  'channel' => 'msedge'\n";
echo "\nTo use a custom profile directory:\n";
echo "  'user_data_dir' => '/path/to/your/chrome/profile'\n";
