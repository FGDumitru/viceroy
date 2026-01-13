<?php
/**
 * Playwright WhatsApp Web Example
 *
 * This example connects to WhatsApp Web and extracts unread messages
 * using the WhatsAppWebTool.
 *
 * Setup Instructions:
 * 1. Make sure Chrome is running with remote debugging enabled:
 *    ./examples/start_chrome_debug.sh
 *
 * 2. Open https://web.whatsapp.com in your browser and log in
 *
 * 3. Run this script: php examples/playwright_whatsapp.php
 *
 * Features:
 * - Checks if you're logged into WhatsApp Web
 * - Extracts unread messages from all chats
 * - Groups messages by sender
 * - Shows message count per sender
 * - Displays message preview (first 80 chars)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\WhatsAppWebTool;

echo "=== WhatsApp Web Unread Messages ===\n\n";

// Configuration
$debugPort = 9222;

// Check if Chrome is running
$fp = @fsockopen('localhost', $debugPort, $errno, $errstr, 2);
if (!$fp) {
    echo "ERROR: Chrome is not running on port $debugPort!\n\n";
    echo "Please start Chrome with remote debugging:\n";
    echo "  ./examples/start_chrome_debug.sh\n\n";
    exit(1);
}
fclose($fp);
echo "Chrome detected on port $debugPort ✓\n\n";

// Initialize configuration
$configData = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$config = new ConfigObjects($configData);

// Create the WhatsApp Web tool
$whatsappTool = new WhatsAppWebTool([
    'cdp_port' => $debugPort,
    'wait_time' => 10
]);

echo "--- Retrieving Unread Messages ---\n\n";

// Execute the tool
$result = $whatsappTool->execute([], $config);

// Display the results
if ($result['isError']) {
    echo "╔══════════════════════════════════════════╗\n";
    echo "║  ERROR                                     ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    echo $result['content'][0]['text'] . "\n\n";

    echo "Troubleshooting:\n";
    echo "1. Make sure Chrome is running: ./examples/start_chrome_debug.sh\n";
    echo "2. Open https://web.whatsapp.com and log in\n";
    echo "3. Wait for messages to load, then run again\n\n";
    exit(1);
}

// Success - display the formatted output
echo $result['content'][0]['text'];

// If structured data is available, show additional info
if (isset($result['data'])) {
    $data = $result['data'];

    echo "=== Additional Information ===\n\n";
    echo "Total Unread: " . $data['total_unread'] . "\n";
    echo "Chat Count: " . $data['chat_count'] . "\n";

    var_dump($data);
}

echo "\n=== Complete ===\n\n";
echo "Notes:\n";
echo "- Make sure WhatsApp Web is loaded and you're logged in\n";
echo "- The script uses the CDP connection to your existing browser\n";
echo "- No need to close Chrome - it stays open for your use\n";
echo "- To log out of WhatsApp Web: Use the browser menu\n";
echo "- Temporary files are automatically cleaned up\n\n";
