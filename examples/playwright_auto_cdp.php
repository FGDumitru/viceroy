<?php
/**
 * Playwright Auto-Start CDP Example
 *
 * This example automatically starts Chrome with remote debugging,
 * then connects to it. This creates a separate "Debug" profile so your
 * main Chrome can stay open.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\PlaywrightTool;

echo "=== Playwright Auto-Start CDP Example ===\n\n";

// Configuration
$debugPort = 9222;
$debugProfile = getenv('HOME') . '/Library/Application Support/Google Chrome Debug';

// Create debug profile directory if it doesn't exist
if (!is_dir($debugProfile)) {
    echo "Creating debug profile: $debugProfile\n";
    mkdir($debugProfile, 0755, true);
}

// Check if Chrome is already listening on the debug port
$fp = @fsockopen('localhost', $debugPort, $errno, $errstr, 1);
if ($fp) {
    fclose($fp);
    echo "Chrome is already running on port $debugPort ✓\n\n";
} else {
    echo "Starting Chrome with remote debugging...\n";
    echo "Profile: $debugProfile\n";

    // Start Chrome with remote debugging
    $chromeCmd = sprintf(
        '/Applications/Google\\ Chrome.app/Contents/MacOS/Google\\ Chrome ' .
        '--remote-debugging-port=%d ' .
        '--user-data-dir="%s" ' .
        '> /dev/null 2>&1 &',
        $debugPort,
        $debugProfile
    );

    echo "Command: $chromeCmd\n\n";

    exec($chromeCmd);

    // Wait for Chrome to start
    echo "Waiting for Chrome to start...";
    for ($i = 0; $i < 30; $i++) {
        sleep(1);
        echo ".";
        $fp = @fsockopen('localhost', $debugPort, $errno, $errstr, 1);
        if ($fp) {
            fclose($fp);
            echo " ✓\n\n";
            break;
        }
    }

    // Final check
    $fp = @fsockopen('localhost', $debugPort, $errno, $errstr, 1);
    if (!$fp) {
        echo "\nERROR: Failed to start Chrome!\n";
        exit(1);
    }
    fclose($fp);
}

// Now connect to Chrome
echo "Connecting to Chrome via CDP...\n\n";

// Initialize configuration
$configData = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$config = new ConfigObjects($configData);
$connection = new OpenAICompatibleEndpointConnection($config);

// Set up system message
$connection->setSystemMessage(
    "You are connected to a Chrome browser via CDP (Chrome DevTools Protocol).\n\n" .
    "Available actions:\n" .
    "- navigate: Go to a URL\n" .
    "- content: Extract page content\n" .
    "- click: Click elements\n" .
    "- fill: Fill forms\n" .
    "- screenshot: Take screenshots"
);

// Enable tool support
$connection->enableToolSupport();

// Create Playwright tool with CDP connection
$playwrightTool = new PlaywrightTool([
    'debug' => false,
    'connect_cdp' => "http://localhost:$debugPort",
    'screenshot_dir' => 'screenshots',
    'timeout' => 30000
]);

$connection->addToolDefinition($playwrightTool);

echo "--- Test: Check GitHub Login Status ---\n\n";

// Navigate to GitHub directly and extract username from the page
echo "Navigating to GitHub...\n\n";

$username = null;

try {
    // First, navigate to GitHub
    $navResult = $playwrightTool->execute([
        'action' => 'navigate',
        'url' => 'https://github.com',
        'waitUntil' => 'domcontentloaded'
    ], $config);

    if (!$navResult['isError']) {
        // Get the ARIA snapshot which contains login status
        $snapshotResult = $playwrightTool->execute([
            'action' => 'snapshot',
            'format' => 'aria'
        ], $config);

        if (!$snapshotResult['isError']) {
            // Snapshot action returns data under 'snapshot' key, not 'content'
            $content = $snapshotResult['data']['snapshot'] ?? $snapshotResult['data']['content'] ?? '';

            // Look for login indicators in the ARIA snapshot
            // GitHub shows avatar with @username alt text when logged in
            if (preg_match('/@([A-Za-z0-9_-]+)/', $content, $matches)) {
                $username = $matches[1];
            }

            // Also check for dashboard link which contains username
            if (!$username && preg_match('/Go to ([A-Za-z0-9_-]+) dashboard/', $content, $matches)) {
                $username = $matches[1];
            }

            // Also check for profile links
            if (!$username && preg_match('/\/([A-Za-z0-9_-]+)\/dashboard/', $content, $matches)) {
                $username = $matches[1];
            }
        }
    }
} catch (\Throwable $e) {
    echo "[Navigation Error: {$e->getMessage()}]\n\n";
}

// Print the result
if ($username) {
    echo "╔══════════════════════════════════════════╗\n";
    echo "║  GitHub Login Status: LOGGED IN           ║\n";
    echo "║  Username: {$username}\n";
    echo "╚══════════════════════════════════════════╝\n";
} else {
    echo "╔══════════════════════════════════════════╗\n";
    echo "║  GitHub Login Status: NOT LOGGED IN        ║\n";
    echo "╚══════════════════════════════════════════╝\n";
    echo "\nERROR: You must be logged into GitHub to retrieve your projects.\n";
    echo "Please log in at https://github.com and try again.\n\n";

    // Clean up and exit
    try {
        $playwrightTool->execute(['action' => 'quit'], $config);
    } catch (\Throwable $e) {
        // Ignore cleanup errors
    }
    exit(1);
}

// If logged in, get the list of repositories
echo "\n--- Getting Your GitHub Projects ---\n\n";
echo "Navigating to your repositories page...\n\n";

try {
    $reposResult = $playwrightTool->execute([
        'action' => 'navigate',
        'url' => "https://github.com/{$username}?tab=repositories",
        'waitUntil' => 'domcontentloaded'
    ], $config);

    if ($reposResult['isError']) {
        echo "Failed to navigate to repositories page.\n";
    } else {
        $allRepositories = [];
        $pageNumber = 1;

        // Loop through all pages of repositories using URL pagination
        while (true) {
            echo "Fetching page $pageNumber...\n";

            // Get a snapshot of the current repositories page
            $reposSnapshot = $playwrightTool->execute([
                'action' => 'snapshot',
                'format' => 'aria'
            ], $config);

            if ($reposSnapshot['isError']) {
                echo "Error getting snapshot for page $pageNumber\n";
                break;
            }

            $reposContent = $reposSnapshot['data']['snapshot'] ?? $reposSnapshot['data']['content'] ?? '';

            // Extract repository names from the ARIA snapshot
            // GitHub repositories appear as links with pattern "username/reponame"
            if (preg_match_all('/link "([A-Za-z0-9_-]+\/[A-Za-z0-9_.-]+)" \[ref=/', $reposContent, $matches)) {
                $pageRepos = $matches[1];
                $allRepositories = array_merge($allRepositories, $pageRepos);
                echo "  Found " . count($pageRepos) . " repositories on this page\n";
            }

            // Check if there's a "Next" button or pagination link
            $hasNext = false;
            $nextPatterns = [
                '/link "Next" \[ref=([e0-9]+)\]/',
                '/button "Next" \[ref=([e0-9]+)\]/',
            ];

            foreach ($nextPatterns as $pattern) {
                if (preg_match($pattern, $reposContent, $nextMatch)) {
                    $nextRef = $nextMatch[1];
                    $hasNext = true;
                    echo "  Found Next button (ref: $nextRef)\n";

                    // Click the Next button
                    $clickResult = $playwrightTool->execute([
                        'action' => 'click',
                        'ref' => $nextRef,
                        'waitUntil' => 'domcontentloaded'
                    ], $config);

                    if ($clickResult['isError']) {
                        echo "  Error clicking Next button, trying URL navigation\n";
                        $hasNext = false;
                    }
                    break;
                }
            }

            // If no Next button found or click failed, try URL pagination
            if (!$hasNext) {
                // Check if the current page has fewer repos than expected (indicates last page)
                // Or check for page numbers in the URL
                $currentUrl = $reposResult['data']['url'] ?? '';

                // If we found repos but no Next button, try direct URL pagination
                if (count($pageRepos ?? []) >= 25) {
                    // Try the next page URL directly
                    $nextPageUrl = "https://github.com/{$username}?page=" . ($pageNumber + 1) . "&tab=repositories";
                    echo "  Trying direct URL: page " . ($pageNumber + 1) . "\n";

                    $navResult = $playwrightTool->execute([
                        'action' => 'navigate',
                        'url' => $nextPageUrl,
                        'waitUntil' => 'domcontentloaded'
                    ], $config);

                    if (!$navResult['isError']) {
                        // Check if this page actually has content (not a 404 or empty page)
                        $checkSnapshot = $playwrightTool->execute([
                            'action' => 'snapshot',
                            'format' => 'aria'
                        ], $config);

                        if (!$checkSnapshot['isError']) {
                            $checkContent = $checkSnapshot['data']['snapshot'] ?? $checkSnapshot['data']['content'] ?? '';

                            // If we find repositories on this page, continue
                            if (preg_match('/link "([A-Za-z0-9_-]+\/[A-Za-z0-9_.-]+)" \[ref=/', $checkContent)) {
                                $hasNext = true;
                                $pageNumber++;
                                // Small delay to let the page load
                                usleep(500000); // 0.5 seconds
                            } else {
                                echo "  No repositories found on next page, stopping\n";
                                break;
                            }
                        }
                    } else {
                        echo "  Could not navigate to next page\n";
                        break;
                    }
                } else {
                    echo "  Last page reached (less than 25 repos)\n";
                    break;
                }
            } else {
                $pageNumber++;
                // Small delay to let the page load
                usleep(500000); // 0.5 seconds
            }

            // Safety limit to prevent infinite loops
            if ($pageNumber > 50) {
                echo "Reached safety limit of 50 pages\n";
                break;
            }
        }

        // Remove duplicates and re-index
        $allRepositories = array_values(array_unique($allRepositories));

        echo "\n";

        if (!empty($allRepositories)) {
            echo "╔═══════════════════════════════════════════════════╗\n";
            $countStr = count($allRepositories) . " found";
            echo "║  Your GitHub Projects ({$countStr})            ║\n";
            echo "╠═══════════════════════════════════════════════════╣\n";

            foreach ($allRepositories as $index => $repo) {
                $num = str_pad($index + 1, 2, ' ', STR_PAD_LEFT);
                $line = "║  {$num}. {$repo}";
                $padding = max(0, 48 - strlen($repo));
                echo $line . str_repeat(' ', $padding) . "║\n";
            }

            echo "╚═══════════════════════════════════════════════════╝\n";
        } else {
            echo "No repositories found or page structure changed.\n";
            echo "Visit https://github.com/{$username}?tab=repositories to verify.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[Error fetching repositories: {$e->getMessage()}]\n";
}

echo "\n\n=== Complete ===\n\n";
echo "Notes:\n";
echo "- A separate 'Chrome Debug' profile was created for automation\n";
echo "- Your main Chrome can stay open while using this\n";
echo "- The debug profile will remember your sessions between runs\n";
echo "- To clean up: Close Chrome and delete: $debugProfile\n";
