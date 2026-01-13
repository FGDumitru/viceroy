<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;
use RuntimeException;

/**
 * PlaywrightTool - Control a browser using Playwright with persistent sessions
 *
 * This tool uses a stdin/stdout approach to maintain browser state between calls:
 * - A Node.js process stays alive and listens for JSON commands
 * - Commands are sent via stdin
 * - Responses are received via stdout
 * - Browser context is preserved between actions
 *
 * This enables multi-step workflows like:
 * 1. Navigate to a URL
 * 2. Wait for page load (network idle)
 * 3. Extract content
 * 4. Take screenshot
 * 5. Click elements
 * 6. Fill forms
 *
 * All in the same browser session.
 *
 * ## Installation
 *
 * This tool requires Playwright to be installed. Run the following commands:
 *
 * ```bash
 * # Install Playwright globally
 * npm install -g playwright
 *
 * # OR install locally in your project
 * npm install playwright
 *
 * # Install browser binaries
 * playwright install
 * ```
 *
 * ## Dependencies
 *
 * - Node.js (v14 or higher)
 * - npm or yarn
 * - Playwright npm package
 * - Browser binaries (chromium, firefox, webkit)
 *
 * ## Usage
 *
 * ```php
 * use Viceroy\Tools\PlaywrightTool;
 *
 * // Create tool with headed mode (visible browser)
 * $tool = new PlaywrightTool([
 *     'headed' => true,                // Show browser window
 *     'debug' => true,                 // Enable debug logging
 *     'screenshot_dir' => 'screenshots',
 *     'timeout' => 30000,
 *     'navigation_timeout' => 30000,
 *     'action_timeout' => 10000
 * ]);
 *
 * // Start browser session
 * $tool->execute(['action' => 'start'], $config);
 *
 * // Navigate to URL (returns DOM skeleton content)
 * $result = $tool->execute([
 *     'action' => 'navigate',
 *     'url' => 'https://example.com'
 * ], $config);
 *
 * // Access the DOM skeleton content
 * $content = $result['data']['content'] ?? '';
 *
 * // Wait for network idle (max 10 seconds)
 * $tool->execute([
 *     'action' => 'wait',
 *     'timeout' => 10000
 * ], $config);
 *
 * // Take screenshot
 * $tool->execute([
 *     'action' => 'screenshot',
 *     'screenshot_filename' => 'mypage'
 * ], $config);
 *
 * // Close browser
 * $tool->execute(['action' => 'quit'], $config);
 * ```
 *
 * ## Available Actions
 *
 * ### Basic Actions
 * - **start**: Start the browser server (auto-started on first action)
 * - **navigate**: Navigate to a URL (requires: url, optional: wait_until) - **Returns ARIA accessibility snapshot**
 * - **screenshot**: Take a screenshot (optional: screenshot_filename)
 * - **close/quit**: Close the browser session
 *
 * ### Element Actions
 * - **click**: Click an element (requires: selector)
 * - **fill**: Fill an input field (requires: selector, text)
 * - **evaluate**: Execute JavaScript (requires: selector, text with JS code)
 *
 * ### Wait Actions
 * - **wait**: Wait for network idle (optional: timeout in ms, default: action_timeout)
 * - **wait_for_selector**: Wait for a CSS selector (requires: selector, optional: state, timeout)
 *   - States: 'attached', 'detached', 'visible', 'hidden' (default: 'visible')
 * - **wait_for_text**: Wait for text to appear (requires: text, optional: timeout)
 * - **wait_for_text_gone**: Wait for text to disappear (requires: text, optional: timeout)
 * - **wait_for_loaded**: Wait for full page load including resources (optional: timeout, default: 30000)
 *
 * ### Content Extraction
 * - **content**: Extract page content as text
 * - **snapshot**: Capture page snapshot (optional: format) - **Returns ARIA or HTML snapshot**
 *   - Formats: 'aria' (default - uses Playwright's accessibility tree), 'html' (DOM skeleton)
 *
 * ## Wait Strategies
 *
 * **navigate action** uses a two-phase wait strategy (matching Playwright MCP):
 * - Phase 1: Navigate with `domcontentloaded` (HTML parsed, JS may not be finished)
 * - Phase 2: Wait for `load` state with 5 second timeout (ensures JavaScript frameworks rendered)
 * - The 5s timeout is acceptable - content is still available from phase 1
 * - This approach captures JavaScript-rendered content reliably
 *
 * **wait_for_loaded action** for complete page load:
 * - Waits for 'load' state (stylesheets, images, etc.)
 * - Default timeout: 30 seconds
 * - Best used when you need fully rendered pages
 *
 * ## Snapshot Actions
 *
 * **snapshot action** captures the current page state:
 * - **format='aria'** (default): Uses Playwright's built-in `_snapshotForAI()` method
 *   - Returns YAML-formatted accessibility tree (similar to Playwright MCP)
 *   - Captures actual rendered DOM after JavaScript execution
 *   - Includes unique `ref` identifiers for element interaction
 * - **format='html'**: Returns HTML DOM skeleton (same as navigate action)
 *
 * Example:
 * ```php
 * // Get ARIA accessibility snapshot
 * $result = $tool->execute(['action' => 'snapshot', 'format' => 'aria'], $config);
 * $ariaTree = $result['data']['snapshot'] ?? '';
 *
 * // Get HTML DOM skeleton
 * $result = $tool->execute(['action' => 'snapshot', 'format' => 'html'], $config);
 * $html = $result['data']['snapshot'] ?? '';
 * ```
 *
 * ## ARIA Accessibility Snapshot
 *
 * The `navigate` action automatically returns an ARIA accessibility snapshot that:
 * - Uses Playwright's built-in `_snapshotForAI()` method (same as Playwright MCP)
 * - Captures the actual rendered DOM after JavaScript execution
 * - Returns a YAML-formatted accessibility tree with:
 *   - Element roles (button, link, heading, etc.)
 *   - Accessible names and labels
 *   - ARIA states (checked, disabled, expanded, selected)
 *   - Unique `ref` identifiers for element interaction
 *   - Level information (for headings)
 *   - Cursor information for clickable elements
 *
 * This provides LLMs with a semantic understanding of the page structure
 * while significantly reducing token usage compared to raw HTML.
 *
 * **Example output:**
 * ```
 * - generic [ref=e2]:
 *   - heading "Example Domain" [level=1] [ref=e3]
 *   - paragraph [ref=e4]: This domain is for use in documentation examples...
 *   - paragraph [ref=e5]:
 *     - link "Learn more" [ref=e6] [cursor=pointer]:
 *       - /url: https://iana.org/domains/example
 * ```
 *
 * ## Constructor Options
 *
 * - **headed** (bool): Show browser window (default: false)
 * - **debug** (bool): Enable debug logging to error_log (default: false)
 * - **screenshot_dir** (string): Directory for screenshots (default: 'screenshots')
 * - **timeout** (int): Default timeout in milliseconds (default: 30000)
 * - **navigation_timeout** (int): Navigation timeout in milliseconds (default: 30000)
 * - **action_timeout** (int): Action timeout in milliseconds (default: 10000)
 *
 * ## JavaScript Rendering
 *
 * **IMPORTANT:** Modern websites often use JavaScript frameworks (React, Vue, Angular) that render content **after** the page loads. The `navigate` action now uses a two-phase wait strategy:
 * 1. First waits for `domcontentloaded` (HTML parsed)
 * 2. Then waits for `load` state with 5s timeout (ensures JavaScript rendered)
 *
 * For sites with heavy JavaScript or slower rendering, use explicit waits:
 * - Use `wait_for_selector` to wait for specific elements
 * - Use `wait_for_text` to wait for specific text content
 * - Use `wait_until => 'load'` for pages that need full resource loading
 *
 * ## Examples
 *
 * ### Navigate with smart waiting
 * ```php
 * // Fastest - returns as soon as DOM is ready (default)
 * $tool->execute([
 *     'action' => 'navigate',
 *     'url' => 'https://example.com'
 * ], $config);
 *
 * // Wait for full page load
 * $tool->execute([
 *     'action' => 'navigate',
 *     'url' => 'https://example.com',
 *     'wait_until' => 'load'
 * ], $config);
 * ```
 *
 * ### Wait for specific conditions
 * ```php
 * // Wait for an element to be visible
 * $tool->execute([
 *     'action' => 'wait_for_selector',
 *     'selector' => '.submit-button',
 *     'state' => 'visible',
 *     'timeout' => 5000
 * ], $config);
 *
 * // Wait for text to appear
 * $tool->execute([
 *     'action' => 'wait_for_text',
 *     'text' => 'Welcome',
 *     'timeout' => 10000
 * ], $config);
 *
 * // Wait for text to disappear (loading spinner, etc.)
 * $tool->execute([
 *     'action' => 'wait_for_text_gone',
 *     'text' => 'Loading...',
 *     'timeout' => 15000
 * ], $config);
 *
 * // Wait for complete page load
 * $tool->execute([
 *     'action' => 'wait_for_loaded',
 *     'timeout' => 30000
 * ], $config);
 * ```
 *
 * @package Viceroy\Tools
 */
class PlaywrightTool implements ToolInterface
{
    private ?string $screenshotDir = 'screenshots';
    private bool $debugMode = false;
    private int $timeout = 30000;
    private int $navigationTimeout = 30000;
    private int $actionTimeout = 10000;
    private bool $headed = false;
    private bool $useProfile = false;
    private ?string $channel = null;
    private ?string $userDataDir = null;
    private ?string $connectCdp = null;
    private ?string $serverScriptPath = null;
    private $process = null;
    private $pipes = [];
    private bool $isServerRunning = false;
    private int $commandId = 0;
    private string $instanceId;

    /**
     * Constructor
     *
     * @param array|null $options Configuration options
     * @param bool $options['headed'] Show browser window (default: false)
     * @param bool $options['debug'] Enable debug logging (default: false)
     * @param string $options['screenshot_dir'] Directory for screenshots (default: 'screenshots')
     * @param int $options['timeout'] Default timeout in milliseconds (default: 30000)
     * @param int $options['navigation_timeout'] Navigation timeout in milliseconds (default: 30000)
     * @param int $options['action_timeout'] Action timeout in milliseconds (default: 10000)
     * @param bool $options['use_profile'] Use browser profile with cookies/sessions (default: false)
     * @param string $options['channel'] Browser channel: 'chrome', 'msedge', 'chrome-beta' (default: null = bundled Chromium)
     * @param string $options['user_data_dir'] Custom user data directory path (default: OS default)
     * @param string $options['connect_cdp'] Connect to existing browser via CDP (e.g., 'http://localhost:9222')
     */
    public function __construct(?array $options = null)
    {
        $options = $options ?? [];

        $this->debugMode = $options['debug'] ?? false;
        $this->timeout = $options['timeout'] ?? 30000;
        $this->navigationTimeout = $options['navigation_timeout'] ?? $this->timeout;
        $this->actionTimeout = $options['action_timeout'] ?? 10000;
        $this->screenshotDir = $options['screenshot_dir'] ?? 'screenshots';
        $this->headed = !empty($options['headed']); // Ensure proper boolean conversion
        $this->useProfile = !empty($options['use_profile']);
        $this->channel = $options['channel'] ?? null;
        $this->userDataDir = $options['user_data_dir'] ?? null;
        $this->connectCdp = $options['connect_cdp'] ?? null;

        // Generate a unique instance ID for this tool instance
        $this->instanceId = uniqid('playwright_', true);

        // Create screenshots directory
        if (!is_dir($this->screenshotDir)) {
            mkdir($this->screenshotDir, 0755, true);
        }

        // Set up server script path
        $this->serverScriptPath = $this->createServerScript();
    }

    public function __destruct()
    {
        $this->stopServer();
    }

    public function getName(): string
    {
        return 'browser_control';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'browser_control',
                'description' => 'Control a persistent browser for web automation. Navigate websites, extract content, click elements, fill forms, take screenshots. The browser stays open between calls for multi-step workflows.

IMPORTANT: The navigate action returns an ARIA accessibility snapshot (not HTML). This is a structured tree showing element roles, accessible names, and unique [ref=eX] identifiers.

AVAILABLE ACTIONS:
- navigate: Go to a URL, returns ARIA snapshot of the page structure
- snapshot: Get ARIA or HTML snapshot of current page
- content: Extract plain text content (readable, not structured)
- click: Click an element using CSS selector
- fill: Fill an input field with text
- wait: Wait for network idle (page to finish loading)
- wait_for_selector: Wait for an element to appear/be visible
- wait_for_text: Wait for text to appear on the page
- screenshot: Capture a screenshot of the current page
- evaluate: Run JavaScript code on the page
- start: Initialize browser (auto-started on first action)
- quit: Close the browser session

EXAMPLES:
1. Navigate and extract: {"action": "navigate", "url": "https://example.com"}
2. Fill search form: {"action": "fill", "selector": "input[name=\'q\']", "text": "search terms"}
3. Click button: {"action": "click", "selector": "button[type=\'submit\']"}
4. Get plain text: {"action": "content"}
5. Wait for element: {"action": "wait_for_selector", "selector": ".results", "timeout": 10000}

IMPORTANT - FORM SUBMISSION:
The fill action smartly detects submit buttons:
- If a SUBMIT button EXISTS in the form: Tool does NOT auto-press Enter. You MUST click the button explicitly.
- If NO submit button exists (search forms only): Tool auto-presses Enter to submit.

WIKIPEDIA SELECTORS (CRITICAL - READ THIS):
Wikipedia search input has id="searchInput" (NOT "search"), name="search", class="mw-searchInput"
- CORRECT: input[name="search"], #searchInput, .mw-searchInput
- WRONG: input#search, #search (these do NOT exist!)
- Search button: button:has-text("Search"), .cdx-search-input__end-button

HOW TO SUBMIT FORMS:
1. Fill the input: {"action": "fill", "selector": "input[name=\'search\']", "text": "query"}
2. Check the tool response - it will tell you if a submit button was detected
3. If button detected, click it: {"action": "click", "selector": "button[type=\'submit\']"}
4. If no button detected, the form was auto-submitted (Enter was pressed)

FALLBACK IF CLICK FAILS:
- If clicking the submit button fails (element not found, not clickable, etc.), use evaluate to press Enter:
  {"action": "evaluate", "code": "document.querySelector(\'input[name=\\\"search\\\"]\').dispatchEvent(new KeyboardEvent(\'keydown\', {key: \'Enter\', code: \'Enter\', keyCode: 13, bubbles: true}));"}
- Or navigate directly to the search URL if the site uses GET parameters',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'enum' => [
                                'start', 'navigate', 'screenshot', 'click', 'fill', 'content', 'evaluate',
                                'wait', 'wait_for_selector', 'wait_for_text', 'wait_for_text_gone', 'wait_for_loaded',
                                'snapshot', 'close', 'quit'
                            ],
                            'description' => 'The browser action to perform. Use "start" to begin a session, "quit" to end it. Most actions auto-start the browser if needed.'
                        ],
                        'url' => [
                            'type' => 'string',
                            'description' => 'URL for navigate action. Must include protocol (https:// or http://). Examples: "https://example.com", "https://www.google.com"'
                        ],
                        'selector' => [
                            'type' => 'string',
                            'description' => 'CSS selector for click/fill/evaluate/wait_for_selector actions.

Examples:
- "button.submit" - button with class "submit"
- "#username" - element with id "username"
- "input[name=\'email\']" - input with name attribute "email"
- "a[href*=\'login\']" - anchor tag containing "login" in href
- ".search-box" - element with class "search-box"
- "button[type=\'submit\']" - submit button'
                        ],
                        'text' => [
                            'type' => 'string',
                            'description' => 'Usage depends on the action:
- For fill: Text to type into the input field. Tool auto-presses Enter for search forms.
- For evaluate: JavaScript code to execute (e.g., "document.title", "window.scrollTo(0, document.body.scrollHeight)")
- For wait_for_text/wait_for_text_gone: Text string to wait for appearance/disappearance

Examples:
- fill: {"action": "fill", "selector": "input[name=\'q\']", "text": "PHP tutorial"}
- evaluate: {"action": "evaluate", "selector": "body", "text": "document.body.innerHTML.length"}
- wait_for_text: {"action": "wait_for_text", "text": "Results", "timeout": 10000}'
                        ],
                        'timeout' => [
                            'type' => 'number',
                            'description' => 'Maximum time to wait in milliseconds. Default: 10000 for most actions, 30000 for wait_for_loaded. Use longer timeouts for slow-loading pages.',
                            'default' => 10000
                        ],
                        'state' => [
                            'type' => 'string',
                            'enum' => ['attached', 'detached', 'visible', 'hidden'],
                            'description' => 'Element state for wait_for_selector action:
- "attached": Element exists in DOM (may be hidden)
- "detached": Element removed from DOM
- "visible": Element is visible (default)
- "hidden": Element is not visible

Example: {"action": "wait_for_selector", "selector": ".loading", "state": "hidden", "timeout": 15000}',
                            'default' => 'visible'
                        ],
                        'screenshot_filename' => [
                            'type' => 'string',
                            'description' => 'Custom filename for screenshot (without extension). File is saved as PNG in the configured screenshot_dir. If not specified, uses timestamp-based name.

Example: {"action": "screenshot", "screenshot_filename": "my_page"} -> saves as "my_page.png"'
                        ],
                        'wait_until' => [
                            'type' => 'string',
                            'enum' => ['domcontentloaded', 'load', 'networkidle'],
                            'description' => 'When to consider navigation succeeded (for navigate action):
- "domcontentloaded" (default): HTML parsed, fast but JS may not be finished
- "load": All resources loaded (stylesheets, images), includes JS rendering
- "networkidle": No network activity for 500ms, slowest but most thorough

Example: {"action": "navigate", "url": "https://example.com", "wait_until": "load"}',
                            'default' => 'domcontentloaded'
                        ],
                        'format' => [
                            'type' => 'string',
                            'enum' => ['aria', 'html'],
                            'description' => 'Snapshot format for snapshot action:
- "aria" (default): ARIA accessibility tree with roles, names, refs (same as navigate)
- "html": HTML DOM skeleton with essential attributes

Example: {"action": "snapshot", "format": "html"}'
                        ]
                    ],
                    'required' => ['action']
                ]
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        if (!isset($arguments['action']) || !is_string($arguments['action'])) {
            return false;
        }

        $action = $arguments['action'];
        $validActions = [
            'start', 'navigate', 'screenshot', 'click', 'fill', 'content', 'evaluate',
            'wait', 'wait_for_selector', 'wait_for_text', 'wait_for_text_gone', 'wait_for_loaded',
            'snapshot', 'close', 'quit'
        ];

        if (!in_array($action, $validActions)) {
            return false;
        }

        // Validate action-specific required parameters
        switch ($action) {
            case 'navigate':
                if (!isset($arguments['url']) || !is_string($arguments['url'])) {
                    return false;
                }
                if (!filter_var($arguments['url'], FILTER_VALIDATE_URL)) {
                    return false;
                }
                break;

            case 'click':
            case 'fill':
            case 'evaluate':
            case 'wait_for_selector':
                if (!isset($arguments['selector']) || !is_string($arguments['selector'])) {
                    return false;
                }
                break;

            case 'fill':
            case 'evaluate':
                if (!isset($arguments['text']) || !is_string($arguments['text'])) {
                    return false;
                }
                break;

            case 'wait_for_text':
            case 'wait_for_text_gone':
                if (!isset($arguments['text']) || !is_string($arguments['text'])) {
                    return false;
                }
                break;

            case 'wait':
            case 'wait_for_selector':
            case 'wait_for_text':
            case 'wait_for_text_gone':
            case 'wait_for_loaded':
                if (isset($arguments['timeout']) && (!is_numeric($arguments['timeout']) || $arguments['timeout'] < 0)) {
                    return false;
                }
                break;

            case 'wait_for_selector':
                if (isset($arguments['state']) && !in_array($arguments['state'], ['attached', 'detached', 'visible', 'hidden'])) {
                    return false;
                }
                break;
        }

        return true;
    }

    public function execute(array $arguments, $configuration): array
    {
        if (!$this->validateArguments($arguments)) {
            return $this->errorResponse('Invalid arguments provided for browser action');
        }

        $action = $arguments['action'];

        try {
            switch ($action) {
                case 'start':
                    return $this->startServer();

                case 'navigate':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'navigate',
                        'url' => $arguments['url'],
                        'waitUntil' => $arguments['wait_until'] ?? 'domcontentloaded'
                    ]);

                case 'screenshot':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'screenshot',
                        'filename' => $arguments['screenshot_filename'] ?? null
                    ]);

                case 'click':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'click',
                        'selector' => $arguments['selector']
                    ]);

                case 'fill':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'fill',
                        'selector' => $arguments['selector'],
                        'text' => $arguments['text']
                    ]);

                case 'content':
                    $this->ensureServerRunning();
                    return $this->sendCommand(['action' => 'content']);

                case 'evaluate':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'evaluate',
                        'selector' => $arguments['selector'],
                        'javascript' => $arguments['text']
                    ]);

                case 'wait':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'wait',
                        'timeout' => $arguments['timeout'] ?? $this->actionTimeout
                    ]);

                case 'wait_for_selector':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'wait_for_selector',
                        'selector' => $arguments['selector'],
                        'state' => $arguments['state'] ?? 'visible',
                        'timeout' => $arguments['timeout'] ?? $this->actionTimeout
                    ]);

                case 'wait_for_text':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'wait_for_text',
                        'text' => $arguments['text'],
                        'timeout' => $arguments['timeout'] ?? $this->actionTimeout
                    ]);

                case 'wait_for_text_gone':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'wait_for_text_gone',
                        'text' => $arguments['text'],
                        'timeout' => $arguments['timeout'] ?? $this->actionTimeout
                    ]);

                case 'wait_for_loaded':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'wait_for_loaded',
                        'timeout' => $arguments['timeout'] ?? 30000
                    ]);

                case 'snapshot':
                    $this->ensureServerRunning();
                    return $this->sendCommand([
                        'action' => 'snapshot',
                        'format' => $arguments['format'] ?? 'aria'
                    ]);

                case 'close':
                    return $this->stopServer();

                case 'quit':
                    return $this->stopServer();

                default:
                    return $this->errorResponse("Unknown action: {$action}");
            }
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("PlaywrightTool error: " . $e->getMessage());
            }
            return $this->errorResponse("Browser action failed: " . $e->getMessage());
        }
    }

    /**
     * Create the Node.js server script that handles stdin/stdout communication
     */
    private function createServerScript(): string
    {
        $headedOption = $this->headed ? 'true' : 'false';
        $useProfileOption = $this->useProfile ? 'true' : 'false';
        $navTimeout = $this->navigationTimeout;
        $actionTimeout = $this->actionTimeout;
        $screenshotDir = $this->screenshotDir;
        $channel = $this->channel ? json_encode($this->channel) : 'null';
        $userDataDir = $this->userDataDir ? json_encode($this->userDataDir) : 'null';
        $connectCdp = $this->connectCdp ? json_encode($this->connectCdp) : 'null';

        $script = <<<JS
const { chromium } = require('playwright');
let browser = null;
let page = null;
const HEADED = {$headedOption};
const USE_PROFILE = {$useProfileOption};
const NAVIGATION_TIMEOUT = {$navTimeout};
const ACTION_TIMEOUT = {$actionTimeout};
const CHANNEL = {$channel};
const USER_DATA_DIR = {$userDataDir};
const CONNECT_CDP = {$connectCdp};

// Listen for commands from stdin
process.stdin.setEncoding('utf8');
process.stdin.on('data', async (data) => {
    const input = data.toString().trim();

    // Handle multiple JSON objects separated by newlines
    const lines = input.split('\\n').filter(line => line.trim());

    for (const line of lines) {
        try {
            const command = JSON.parse(line);
            await handleCommand(command);
        } catch (e) {
            respond({
                success: false,
                error: 'Invalid JSON: ' + e.message,
                command: line
            });
        }
    }
});

async function handleCommand(cmd) {
    // Store the command id for response matching
    currentCommandId = cmd.id || null;

    try {
        switch (cmd.action) {
            case 'init':
                await initBrowser();
                respond({ success: true, message: 'Browser initialized in ' + (HEADED ? 'headed' : 'headless') + ' mode' });
                break;

            case 'navigate':
                if (!page) throw new Error('Browser not initialized');

                // Phase 1: Navigate with domcontentloaded (HTML parsed, JS may not be finished)
                // This matches Playwright MCP's two-phase navigation strategy
                const waitUntil = cmd.waitUntil || 'domcontentloaded';
                const gotoOptions = {
                    waitUntil: 'domcontentloaded',
                    timeout: NAVIGATION_TIMEOUT
                };

                await page.goto(cmd.url, gotoOptions);
                const title = await page.title();

                // Phase 2: Wait for 'load' state with configurable timeout (default: 5000ms)
                // This ensures JavaScript frameworks have rendered their content
                const loadWaitTimeout = 5000;
                try {
                    await page.waitForLoadState('load', { timeout: loadWaitTimeout });
                } catch (e) {
                    // Timeout is acceptable - content is still available from domcontentloaded
                    // Some pages continuously load resources, so we don't want to fail here
                }

                // Generate ARIA accessibility snapshot (similar to Playwright MCP)
                // This is the default content format for navigate action
                try {
                    const ariaSnapshot = await page._snapshotForAI('response');
                    respond({
                        success: true,
                        title: title,
                        url: page.url(),
                        format: 'aria',
                        content: ariaSnapshot.full,
                        content_incremental: ariaSnapshot.incremental
                    });
                } catch (e) {
                    // Fallback to manual ARIA tree generation if _snapshotForAI is not available
                    const manualSnapshot = await page.evaluate(() => {
                        function ariaTree(node, depth = 0) {
                            if (!node) return '';

                            // Get computed role
                            const role = node.getAttribute('role') || node.tagName.toLowerCase();

                            // Skip certain elements
                            if (['script', 'style', 'noscript', 'head'].includes(role)) {
                                return '';
                            }

                            // Get accessible name
                            let name = '';
                            if (node.getAttribute('aria-label')) {
                                name = node.getAttribute('aria-label');
                            } else if (node.textContent) {
                                name = node.textContent.trim().substring(0, 50);
                            }

                            // Get ARIA states
                            const states = [];
                            if (node.getAttribute('aria-checked')) states.push('checked:' + node.getAttribute('aria-checked'));
                            if (node.getAttribute('aria-disabled') === 'true') states.push('disabled');
                            if (node.getAttribute('aria-expanded')) states.push('expanded:' + node.getAttribute('aria-expanded'));
                            if (node.getAttribute('aria-selected')) states.push('selected:' + node.getAttribute('aria-selected'));

                            const indent = '  '.repeat(depth);
                            let result = indent + '- ' + role;
                            if (name) result += ' "' + name.replace(/"/g, '\\\\"') + '"';
                            if (states.length > 0) result += ' [' + states.join(', ') + ']';
                            result += '\\n';

                            // Process children
                            for (const child of node.children) {
                                result += ariaTree(child, depth + 1);
                            }

                            return result;
                        }

                        return ariaTree(document.body);
                    });

                    respond({
                        success: true,
                        title: title,
                        url: page.url(),
                        format: 'aria',
                        content: manualSnapshot
                    });
                }
                break;

            case 'screenshot':
                if (!page) throw new Error('Browser not initialized');
                const filename = cmd.filename || 'screenshot_' + Date.now();
                const path = '$screenshotDir/' + filename + '.png';
                await page.screenshot({ path: path });
                respond({
                    success: true,
                    screenshot_path: path,
                    message: 'Screenshot saved to ' + path
                });
                break;

            case 'click':
                if (!page) throw new Error('Browser not initialized');
                await page.waitForSelector(cmd.selector, { timeout: ACTION_TIMEOUT });
                await page.click(cmd.selector);
                respond({
                    success: true,
                    message: 'Clicked: ' + cmd.selector
                });
                break;

            case 'fill':
                if (!page) throw new Error('Browser not initialized');
                await page.waitForSelector(cmd.selector, { timeout: ACTION_TIMEOUT });
                await page.fill(cmd.selector, cmd.text);

                // Auto-detect if this is a search form and press Enter if no submit button exists
                // Search forms typically have: type="search", name="q"/"search", or placeholder containing "search"
                const isSearchForm = await page.evaluate((selector) => {
                    const input = document.querySelector(selector);
                    if (!input) return false;

                    // Check if it's a search input
                    const inputType = input.getAttribute('type') || '';
                    const inputName = input.getAttribute('name') || '';
                    const inputPlaceholder = input.getAttribute('placeholder') || '';
                    const inputId = input.getAttribute('id') || '';
                    const inputClass = input.getAttribute('class') || '';

                    // Detect search-like characteristics
                    const isSearchType = inputType.toLowerCase() === 'search';
                    const hasSearchName = /search|q|query/i.test(inputName);
                    const hasSearchPlaceholder = /search|find|search/i.test(inputPlaceholder);
                    const hasSearchId = /search/i.test(inputId);
                    const hasSearchClass = /search/i.test(inputClass);

                    if (!isSearchType && !hasSearchName && !hasSearchPlaceholder && !hasSearchId && !hasSearchClass) {
                        return false;
                    }

                    // Check if there's a submit button in the same form
                    const form = input.closest('form');
                    if (form) {
                        const submitButton = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
                        if (submitButton) {
                            return false; // Submit button exists, don't auto-press Enter
                        }
                    }

                    // Check for nearby submit button (within parent or sibling)
                    const parent = input.parentElement;
                    if (parent) {
                        const nearbySubmit = parent.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
                        if (nearbySubmit) {
                            return false; // Submit button exists nearby
                        }
                    }

                    return true; // It's a search form without submit button, press Enter
                }, cmd.selector);

                if (isSearchForm) {
                    // Press Enter to submit the search form
                    await page.keyboard.press('Enter');
                    respond({
                        success: true,
                        message: 'Filled search form and pressed Enter: ' + cmd.selector
                    });
                } else {
                    respond({
                        success: true,
                        message: 'Filled: ' + cmd.selector + ' (submit button exists - click it to submit form)'
                    });
                }
                break;

            case 'content':
                if (!page) throw new Error('Browser not initialized');
                const content = await page.evaluate(() => {
                    // Remove unwanted elements
                    const remove = document.querySelectorAll('script, style, nav, footer, header, aside, iframe, noscript');
                    remove.forEach(el => el.remove());

                    const main = document.querySelector('main, article, [role="main"], .content, #content') || document.body;
                    let text = main.innerText || main.textContent || '';

                    // Get links
                    const links = Array.from(document.querySelectorAll('a[href]')).slice(0, 20).map(a => ({
                        text: a.textContent.trim(),
                        href: a.getAttribute('href')
                    }));

                    return {
                        text: text.trim(),
                        title: document.title,
                        url: window.location.href,
                        links: links
                    };
                });
                respond({
                    success: true,
                    title: content.title,
                    url: content.url,
                    content: content.text
                });
                break;

            case 'evaluate':
                if (!page) throw new Error('Browser not initialized');
                const result = await page.evaluate((selector, js) => {
                    const el = document.querySelector(selector);
                    if (!el) return null;
                    return eval(js);
                }, cmd.selector, cmd.javascript);
                respond({
                    success: true,
                    result: result
                });
                break;

            case 'wait':
                // Wait for network to be idle, with a maximum timeout
                const maxWait = cmd.timeout || ACTION_TIMEOUT;
                try {
                    await page.waitForLoadState('networkidle', { timeout: maxWait });
                    respond({
                        success: true,
                        message: 'Network idle after ' + maxWait + 'ms max wait'
                    });
                } catch (e) {
                    // Timeout is acceptable - page may be "done enough"
                    respond({
                        success: true,
                        message: 'Waited ' + maxWait + 'ms (network may still be active)'
                    });
                }
                break;

            case 'wait_for_selector':
                if (!page) throw new Error('Browser not initialized');
                const selectorState = cmd.state || 'visible';
                const selectorTimeout = cmd.timeout || ACTION_TIMEOUT;
                try {
                    await page.waitForSelector(cmd.selector, {
                        state: selectorState,
                        timeout: selectorTimeout
                    });
                    respond({
                        success: true,
                        message: 'Selector "' + cmd.selector + '" found with state: ' + selectorState
                    });
                } catch (e) {
                    respond({
                        success: false,
                        error: 'Selector "' + cmd.selector + '" not found within ' + selectorTimeout + 'ms: ' + e.message
                    });
                }
                break;

            case 'wait_for_text':
                if (!page) throw new Error('Browser not initialized');
                const textTimeout = cmd.timeout || ACTION_TIMEOUT;
                try {
                    await page.waitForSelector('text="' + cmd.text + '"', { timeout: textTimeout });
                    respond({
                        success: true,
                        message: 'Text "' + cmd.text + '" found'
                    });
                } catch (e) {
                    respond({
                        success: false,
                        error: 'Text "' + cmd.text + '" not found within ' + textTimeout + 'ms: ' + e.message
                    });
                }
                break;

            case 'wait_for_text_gone':
                if (!page) throw new Error('Browser not initialized');
                const textGoneTimeout = cmd.timeout || ACTION_TIMEOUT;
                try {
                    await page.waitForSelector('text="' + cmd.text + '"', {
                        state: 'hidden',
                        timeout: textGoneTimeout
                    });
                    respond({
                        success: true,
                        message: 'Text "' + cmd.text + '" is no longer visible'
                    });
                } catch (e) {
                    respond({
                        success: false,
                        error: 'Text "' + cmd.text + '" still visible after ' + textGoneTimeout + 'ms: ' + e.message
                    });
                }
                break;

            case 'wait_for_loaded':
                if (!page) throw new Error('Browser not initialized');
                const loadedTimeout = cmd.timeout || 30000;
                try {
                    // Wait for load state - more comprehensive than domcontentloaded
                    await page.waitForLoadState('load', { timeout: loadedTimeout });
                    respond({
                        success: true,
                        message: 'Page fully loaded (including stylesheets and images)'
                    });
                } catch (e) {
                    respond({
                        success: true,
                        message: 'Page load completed with timeout after ' + loadedTimeout + 'ms (content is available)'
                    });
                }
                break;

            case 'snapshot':
                if (!page) throw new Error('Browser not initialized');
                const format = cmd.format || 'aria';

                if (format === 'aria') {
                    // Use Playwright's built-in accessibility snapshot (similar to Playwright MCP)
                    // This captures the actual rendered DOM with JavaScript executed
                    try {
                        const ariaSnapshot = await page._snapshotForAI('response');
                        respond({
                            success: true,
                            title: await page.title(),
                            url: page.url(),
                            format: 'aria',
                            snapshot: ariaSnapshot.full,
                            snapshot_incremental: ariaSnapshot.incremental
                        });
                    } catch (e) {
                        // Fallback to manual ARIA tree generation if _snapshotForAI is not available
                        const manualSnapshot = await page.evaluate(() => {
                            function ariaTree(node, depth = 0) {
                                if (!node) return '';

                                // Get computed role
                                const role = node.getAttribute('role') || node.tagName.toLowerCase();

                                // Skip certain elements
                                if (['script', 'style', 'noscript', 'head'].includes(role)) {
                                    return '';
                                }

                                // Get accessible name
                                let name = '';
                                if (node.getAttribute('aria-label')) {
                                    name = node.getAttribute('aria-label');
                                } else if (node.textContent) {
                                    name = node.textContent.trim().substring(0, 50);
                                }

                                // Get ARIA states
                                const states = [];
                                if (node.getAttribute('aria-checked')) states.push('checked:' + node.getAttribute('aria-checked'));
                                if (node.getAttribute('aria-disabled') === 'true') states.push('disabled');
                                if (node.getAttribute('aria-expanded')) states.push('expanded:' + node.getAttribute('aria-expanded'));
                                if (node.getAttribute('aria-selected')) states.push('selected:' + node.getAttribute('aria-selected'));

                                const indent = '  '.repeat(depth);
                                let result = indent + '- ' + role;
                                if (name) result += ' "' + name.replace(/"/g, '\\\\"') + '"';
                                if (states.length > 0) result += ' [' + states.join(', ') + ']';
                                result += '\\n';

                                // Process children
                                for (const child of node.children) {
                                    result += ariaTree(child, depth + 1);
                                }

                                return result;
                            }

                            return ariaTree(document.body);
                        });

                        respond({
                            success: true,
                            title: await page.title(),
                            url: page.url(),
                            format: 'aria',
                            snapshot: manualSnapshot
                        });
                    }
                } else {
                    // Fallback to HTML DOM skeleton
                    const skeleton = await page.evaluate(() => {
                        const allowedAttributes = new Set([
                            'id', 'class', 'name', 'type', 'placeholder',
                            'aria-label', 'role', 'href', 'title', 'data-testid', 'value'
                        ]);
                        const removeTags = new Set(['script', 'style', 'svg', 'head', 'meta', 'link', 'noscript']);

                        function cleanNode(node) {
                            if (node.nodeType === Node.TEXT_NODE) {
                                const text = node.textContent.trim();
                                if (!text) return '';
                                return text;
                            }

                            if (node.nodeType !== Node.ELEMENT_NODE) {
                                return '';
                            }

                            if (removeTags.has(node.tagName.toLowerCase())) {
                                let result = '';
                                for (const child of node.childNodes) {
                                    result += cleanNode(child);
                                }
                                return result;
                            }

                            let html = '<' + node.tagName.toLowerCase();

                            if (node.hasAttributes()) {
                                for (const attr of node.attributes) {
                                    if (allowedAttributes.has(attr.name.toLowerCase())) {
                                        html += ' ' + attr.name + '="' + attr.value.replace(/"/g, '&quot;') + '"';
                                    }
                                }
                            }
                            html += '>';

                            for (const child of node.childNodes) {
                                html += cleanNode(child);
                            }

                            html += '</' + node.tagName.toLowerCase() + '>';
                            return html;
                        }

                        const root = document.body || document.documentElement;
                        if (!root) return '';

                        return cleanNode(root);
                    });

                    respond({
                        success: true,
                        title: await page.title(),
                        url: page.url(),
                        format: 'html',
                        snapshot: skeleton
                    });
                }
                break;

            case 'close':
            case 'quit':
                await closeBrowser();
                respond({ success: true, message: 'Browser closed' });
                break;

            default:
                respond({
                    success: false,
                    error: 'Unknown action: ' + cmd.action
                });
        }
    } catch (e) {
        respond({
            success: false,
            error: e.message
        });
    }
}

async function initBrowser() {
    if (browser) {
        await browser.close();
    }

    // Connect to existing browser via CDP (Chrome DevTools Protocol)
    if (CONNECT_CDP) {
        try {
            browser = await chromium.connectOverCDP(CONNECT_CDP);
            // Get the first context and page from the connected browser
            const context = browser.contexts()[0];
            if (!context) {
                throw new Error('No browser context found. Make sure Chrome was started with --remote-debugging-port=' + CONNECT_CDP.split(':').pop());
            }
            page = context.pages()[0] || await context.newPage();

            // Set default navigation timeout
            page.setDefaultNavigationTimeout(NAVIGATION_TIMEOUT);
            page.setDefaultTimeout(ACTION_TIMEOUT);
            return;
        } catch (e) {
            throw new Error('Failed to connect to browser via CDP: ' + e.message + '. Make sure Chrome is running with: --remote-debugging-port=' + CONNECT_CDP.split(':').pop());
        }
    }

    const launchOptions = {
        headless: !HEADED,
        slowMo: 50 // Slow down by 50ms for better visibility in headed mode
    };

    // Use existing browser profile if requested
    if (USE_PROFILE) {
        // Use persistent context to access existing browser profile
        const contextOptions = {
            headless: !HEADED,
            slowMo: 50
        };

        // Add channel if specified (chrome, msedge, etc.)
        if (CHANNEL) {
            contextOptions.channel = CHANNEL;
        }

        // launchPersistentContext requires userDataDir as first argument
        // If not specified, use empty string to create a temporary profile
        const userDataDirPath = USER_DATA_DIR || '';

        // Launch persistent context (uses existing browser profile)
        const context = await chromium.launchPersistentContext(userDataDirPath, contextOptions);
        page = context.pages()[0] || await context.newPage();
        browser = context;
    } else {
        // Standard incognito mode (fresh session)
        if (CHANNEL) {
            launchOptions.channel = CHANNEL;
        }
        browser = await chromium.launch(launchOptions);
        page = await browser.newPage();
    }

    // Set default navigation timeout
    page.setDefaultNavigationTimeout(NAVIGATION_TIMEOUT);
    page.setDefaultTimeout(ACTION_TIMEOUT);
}

async function closeBrowser() {
    if (browser) {
        await browser.close();
        browser = null;
        page = null;
    }
}

function respond(data) {
    // Include the command id if available for response matching
    if (currentCommandId !== null) {
        data.id = currentCommandId;
    }
    console.log(JSON.stringify(data));
}

let currentCommandId = null;

// Handle graceful shutdown
process.on('SIGTERM', async () => {
    await closeBrowser();
    process.exit(0);
});

process.on('SIGINT', async () => {
    await closeBrowser();
    process.exit(0);
});

// Notify ready
console.log(JSON.stringify({ ready: true }));
JS;

        // Write script to the project directory (not /tmp) so Node.js can find the playwright module
        $projectRoot = dirname(__DIR__, 2);
        $scriptPath = $projectRoot . '/.viceroy_playwright_server_' . $this->instanceId . '.js';
        file_put_contents($scriptPath, $script);

        return $scriptPath;
    }

    /**
     * Start the Playwright server process
     */
    private function startServer(): array
    {
        if ($this->isServerRunning) {
            return $this->successResponse("Browser server already running");
        }

        $projectRoot = dirname(__DIR__, 2);
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $command = "cd " . escapeshellarg($projectRoot) . " && node " . escapeshellarg($this->serverScriptPath);

        if ($this->debugMode) {
            error_log("PlaywrightTool: __DIR__ = " . __DIR__);
            error_log("PlaywrightTool: projectRoot = " . $projectRoot);
            error_log("PlaywrightTool: serverScriptPath = " . $this->serverScriptPath);
            error_log("PlaywrightTool: headed = " . ($this->headed ? 'true' : 'false'));
            error_log("PlaywrightTool: command = " . $command);
        }

        $this->process = proc_open($command, $descriptorspec, $this->pipes);

        if (!is_resource($this->process)) {
            throw new RuntimeException("Failed to start Playwright server process");
        }

        // Set non-blocking mode for stdout
        stream_set_blocking($this->pipes[1], false);

        // Wait for "ready" message
        $ready = false;
        $startTime = microtime(true);
        $timeout = 10; // 10 seconds

        while (!$ready && (microtime(true) - $startTime) < $timeout) {
            $line = fgets($this->pipes[1]);
            if ($this->debugMode && $line) {
                error_log("PlaywrightTool: received line: " . trim($line));
            }
            if ($line) {
                $data = json_decode(trim($line), true);
                if ($data && isset($data['ready']) && $data['ready']) {
                    $ready = true;
                }
            }
            usleep(100000); // 100ms
        }

        if (!$ready) {
            if ($this->debugMode) {
                // Try to read from stderr to see what went wrong
                $stderr = stream_get_contents($this->pipes[2]);
                error_log("PlaywrightTool: stderr output: " . $stderr);
            }
            $this->stopServer();
            throw new RuntimeException("Playwright server failed to start within timeout");
        }

        // Initialize browser
        $this->sendCommand(['action' => 'init'], false);

        $this->isServerRunning = true;

        return $this->successResponse("Browser server started and ready in " . ($this->headed ? 'HEADED' : 'headless') . " mode", [
            'server_script' => $this->serverScriptPath,
            'headed' => $this->headed
        ]);
    }

    /**
     * Stop the Playwright server process
     */
    private function stopServer(): array
    {
        if (!$this->isServerRunning) {
            return $this->successResponse("Browser server not running");
        }

        // Send quit command
        try {
            $this->sendCommand(['action' => 'quit'], false);
        } catch (\Exception $e) {
            // Ignore errors during shutdown
        }

        // Close pipes
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        // Terminate process
        if (is_resource($this->process)) {
            $status = proc_terminate($this->process);
            if (!$status) {
                proc_close($this->process);
            }
        }

        // Clean up script file
        if ($this->serverScriptPath && file_exists($this->serverScriptPath)) {
            @unlink($this->serverScriptPath);
        }

        $this->isServerRunning = false;
        $this->process = null;
        $this->pipes = [];

        return $this->successResponse("Browser server stopped");
    }

    /**
     * Ensure the server is running
     */
    private function ensureServerRunning(): void
    {
        if (!$this->isServerRunning) {
            $this->cleanupOldTempFiles();
            $this->startServer();
        }
    }

    /**
     * Clean up temporary JavaScript files older than 5 minutes
     * These files are created by the server but may be left behind if scripts crash
     */
    private function cleanupOldTempFiles(): void
    {
        try {
            // Use the same project root calculation as createServerScript()
            $projectRoot = dirname(__DIR__, 2);
            $pattern = $projectRoot . '/.viceroy_playwright_server_*.js';
            $files = glob($pattern);

            if (empty($files)) {
                return;
            }

            $now = time();
            $maxAge = 300; // 5 minutes in seconds
            $cleaned = 0;

            foreach ($files as $file) {
                $age = $now - filemtime($file);

                // Only delete files older than 5 minutes
                if ($age > $maxAge) {
                    if (@unlink($file)) {
                        $cleaned++;
                        if ($this->debugMode) {
                            error_log("PlaywrightTool: Cleaned up old temp file: " . basename($file) . " (age: " . round($age / 60, 1) . " minutes)");
                        }
                    }
                }
            }

            if ($this->debugMode && $cleaned > 0) {
                error_log("PlaywrightTool: Cleaned up {$cleaned} old temp file(s)");
            }
        } catch (\Exception $e) {
            // Silently ignore cleanup errors
            if ($this->debugMode) {
                error_log("PlaywrightTool: Cleanup error: " . $e->getMessage());
            }
        }
    }

    /**
     * Send a command to the Playwright server and get the response
     */
    private function sendCommand(array $command, bool $autoStart = true): array
    {
        if ($autoStart && !$this->isServerRunning) {
            $this->startServer();
        }

        $command['id'] = ++$this->commandId;
        $commandJson = json_encode($command) . "\n";

        if ($this->debugMode) {
            error_log("PlaywrightTool sending: " . trim($commandJson));
        }

        // Send command
        fwrite($this->pipes[0], $commandJson);
        fflush($this->pipes[0]);

        // Read response
        $response = null;
        $startTime = microtime(true);
        $timeout = $this->timeout / 1000; // Convert to seconds

        while ((microtime(true) - $startTime) < $timeout) {
            $line = fgets($this->pipes[1]);

            if ($line) {
                $trimmed = trim($line);
                if ($this->debugMode) {
                    error_log("PlaywrightTool received: " . $trimmed);
                }

                $data = json_decode($trimmed, true);
                if ($data && json_last_error() === JSON_ERROR_NONE) {
                    if (isset($data['id']) && $data['id'] === $command['id']) {
                        $response = $data;
                        break;
                    }
                }
            }

            usleep(10000); // 10ms
        }

        if ($response === null) {
            throw new RuntimeException("Timeout waiting for response from Playwright server");
        }

        if (!$response['success']) {
            throw new RuntimeException($response['error'] ?? 'Unknown error');
        }

        // Build response in tool format
        return [
            'content' => [
                ['type' => 'text', 'text' => $response['message'] ?? 'Action completed']
            ],
            'isError' => false,
            'data' => $response
        ];
    }

    /**
     * Create a success response
     */
    private function successResponse(string $message, ?array $data = null): array
    {
        $content = [['type' => 'text', 'text' => $message]];

        if ($data !== null) {
            $content[] = [
                'type' => 'text',
                'text' => "\n\n" . json_encode($data, JSON_PRETTY_PRINT)
            ];
        }

        return [
            'content' => $content,
            'isError' => false,
            'data' => $data
        ];
    }

    /**
     * Create an error response
     */
    private function errorResponse(string $message): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => "Error: {$message}"]
            ],
            'isError' => true
        ];
    }
}
