<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;
use Viceroy\Tools\PlaywrightTool;

/**
 * WhatsApp Web Tool
 *
 * Retrieves unread messages from WhatsApp Web using Playwright CDP connection.
 * Requires WhatsApp Web to be open and logged in the browser.
 */
class WhatsAppWebTool implements ToolInterface
{
    private PlaywrightTool $playwrightTool;
    private ?string $cdpPort;
    private int $waitTime;

    public function __construct(?array $options = null)
    {
        $this->cdpPort = $options['cdp_port'] ?? '9222';
        $this->waitTime = $options['wait_time'] ?? 10;

        // Initialize Playwright tool for CDP connection
        $this->playwrightTool = new PlaywrightTool([
            'debug' => false,
            'connect_cdp' => "http://localhost:{$this->cdpPort}",
            'screenshot_dir' => 'screenshots',
            'timeout' => 30000
        ]);
    }

    public function getName(): string
    {
        return 'whatsapp_web_unread';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'whatsapp_web_unread',
                'description' => 'Retrieves unread messages from WhatsApp Web. Requires WhatsApp Web to be open and logged in at https://web.whatsapp.com. Messages are grouped by contact with a preview of the message content.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cdp_port' => [
                            'type' => 'string',
                            'description' => 'Chrome DevTools Protocol port (default: 9222)',
                            'default' => '9222'
                        ],
                        'wait_time' => [
                            'type' => 'integer',
                            'description' => 'Seconds to wait for WhatsApp Web to load messages (default: 10)',
                            'default' => 10
                        ],
                        'include_read' => [
                            'type' => 'boolean',
                            'description' => 'Include recently read messages in the results (default: false)',
                            'default' => false
                        ]
                    ]
                ]
            ]
        ];
    }

    public function validateArguments($arguments): bool
    {
        // Validate cdp_port if provided
        if (isset($arguments['cdp_port'])) {
            if (!is_numeric($arguments['cdp_port']) || $arguments['cdp_port'] < 1 || $arguments['cdp_port'] > 65535) {
                return false;
            }
        }

        // Validate wait_time if provided
        if (isset($arguments['wait_time'])) {
            if (!is_numeric($arguments['wait_time']) || $arguments['wait_time'] < 1 || $arguments['wait_time'] > 60) {
                return false;
            }
        }

        // Validate include_read if provided
        if (isset($arguments['include_read'])) {
            if (!is_bool($arguments['include_read'])) {
                return false;
            }
        }

        return true;
    }

    public function execute($arguments, $configuration): array
    {
        if (!$this->validateArguments($arguments)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: Invalid arguments provided. cdp_port must be 1-65535, wait_time must be 1-60, include_read must be boolean.'
                    ]
                ],
                'isError' => true
            ];
        }

        $cdpPort = $arguments['cdp_port'] ?? $this->cdpPort;
        $waitTime = $arguments['wait_time'] ?? $this->waitTime;
        $includeRead = $arguments['include_read'] ?? false;

        try {
            // Check if Chrome is running on the specified port
            $fp = @fsockopen('localhost', $cdpPort, $errno, $errstr, 2);
            if (!$fp) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Error: Chrome is not running on port {$cdpPort}. Please start Chrome with remote debugging enabled using: ./examples/start_chrome_debug.sh"
                        ]
                    ],
                    'isError' => true
                ];
            }
            fclose($fp);

            // Navigate to WhatsApp Web (ensureServerRunning is called automatically)
            $navResult = $this->playwrightTool->execute([
                'action' => 'navigate',
                'url' => 'https://web.whatsapp.com',
                'wait_until' => 'domcontentloaded'
            ], $configuration);

            if ($navResult['isError']) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Error: Failed to navigate to WhatsApp Web'
                        ]
                    ],
                    'isError' => true
                ];
            }

            // Wait for WhatsApp to load messages
            sleep($waitTime);

            // Get fresh snapshot after waiting
            $snapshotResult = $this->playwrightTool->execute([
                'action' => 'snapshot',
                'format' => 'aria'
            ], $configuration);

            if ($snapshotResult['isError']) {
                // If snapshot fails, try using the content from navigate
                $content = $navResult['data']['content'] ?? '';
                if (empty($content)) {
                    return [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Error: Failed to get page content after navigation'
                            ]
                        ],
                        'isError' => true
                    ];
                }
            } else {
                // Use snapshot content (snapshot action returns data under 'snapshot' key)
                $content = $snapshotResult['data']['snapshot'] ?? '';
                if (empty($content)) {
                    // Fallback to navigate content if snapshot is empty
                    $content = $navResult['data']['content'] ?? '';
                }
            }

            // Check if logged in
            if (stripos($content, 'QR') !== false || stripos($content, 'Use WhatsApp on your phone') !== false) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Error: Not logged into WhatsApp Web. Please scan the QR code at https://web.whatsapp.com to log in, then try again.'
                        ]
                    ],
                    'isError' => true
                ];
            }

            // Parse unread messages
            $unreadMessages = $this->parseUnreadMessages($content);

            // Get total unread count
            $totalUnread = 0;
            if (preg_match('/generic\s*"([0-9]+)\s+unread\s+messages"/s', $content, $totalMatch)) {
                $totalUnread = (int) $totalMatch[1];
            }

            // Build response
            if (empty($unreadMessages)) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "No unread messages found on WhatsApp Web. Total unread indicator shows: {$totalUnread}"
                        ]
                    ],
                    'isError' => false,
                    'data' => [
                        'total_unread' => $totalUnread,
                        'chats' => []
                    ]
                ];
            }

            // Format output
            $chatCount = count($unreadMessages);
            $calculatedTotal = array_sum(array_column($unreadMessages, 'count'));

            $outputText = "WhatsApp Web Unread Messages\n";
            $outputText .= "============================\n\n";
            $outputText .= "Total Chats: {$chatCount}\n";
            $outputText .= "Total Messages: {$calculatedTotal}\n\n";
            $outputText .= "Messages by Contact:\n\n";

            foreach ($unreadMessages as $index => $msg) {
                $num = $index + 1;
                $outputText .= "{$num}. {$msg['contact']}\n";
                $outputText .= "   Unread: {$msg['count']}\n";
                $outputText .= "   Preview: \"{$msg['preview']}\"\n\n";
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $outputText
                    ]
                ],
                'isError' => false,
                'data' => [
                    'total_unread' => $calculatedTotal,
                    'chat_count' => $chatCount,
                    'chats' => $unreadMessages
                ]
            ];

        } catch (\Throwable $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Failed to retrieve WhatsApp messages - {$e->getMessage()}"
                    ]
                ],
                'isError' => true
            ];
        } finally {
            // Clean up Playwright connection
            try {
                $this->playwrightTool->execute(['action' => 'quit'], $configuration);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Parse unread messages from ARIA snapshot content
     *
     * @param string $content ARIA snapshot content
     * @return array List of unread messages with contact, count, and preview
     */
    private function parseUnreadMessages(string $content): array
    {
        $unreadMessages = [];

        // Pattern to match rows with unread messages
        // Format: 'row "Contact time message_preview X unread message"'
        $rowPattern = '/\'row\s+"([^"]*?([0-9]+)\s+unread[^"]*)"\s*\[ref=([e0-9]+)\]/s';

        if (preg_match_all($rowPattern, $content, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $match) {
                $fullRow = $match[1];
                $unreadCount = (int) $match[2];

                // Extract contact name (before time)
                if (preg_match('/^([A-Za-z0-9ĂăÂâÎîȘșȚț\s\(\)]+?)\s+(?:Today|Yesterday|[0-9]{1,2}:[0-9]{2})/s', $fullRow, $nameMatch)) {
                    $contactName = trim($nameMatch[1]);

                    // Extract message preview (after time/sender, before unread count)
                    if (preg_match('/(?:Today|Yesterday|[0-9]{1,2}:[0-9]{2})\s+(.+?)\s+[0-9]+\s+unread/s', $fullRow, $previewMatch)) {
                        $messagePreview = trim($previewMatch[1]);

                        // Clean up the preview
                        $messagePreview = preg_replace('/‪|‬/', '', $messagePreview); // Remove LRM/RLM marks
                        $messagePreview = preg_replace('/\s+Maybe\s+[A-Za-z]+:\s*/', ': ', $messagePreview);
                        $messagePreview = html_entity_decode($messagePreview, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                        $unreadMessages[] = [
                            'contact' => $contactName,
                            'count' => $unreadCount,
                            'preview' => $messagePreview
                        ];
                    }
                }
            }
        }

        return $unreadMessages;
    }
}
