<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;

class SendTelegramMessageTool implements ToolInterface
{
    public function getName(): string
    {
        return 'send_telegram_message';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'send_telegram_message',
                'description' => 'Sends a telegram message to the user. Automatically splits long messages (>4000 characters) into multiple parts with [part/total] numbering.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'The message content to send via Telegram. If longer than 4000 characters, it will be automatically split into multiple messages.'
                        ]
                    ],
                    'required' => ['message']
                ]
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        return isset($arguments['message']) && is_string($arguments['message']) && !empty(trim($arguments['message']));
    }

    /**
     * Split a message into chunks of maximum 4000 characters
     *
     * This method splits a message that exceeds Telegram's 4096 character limit into
     * smaller chunks of 4000 characters each (leaving a buffer for the [X/Y] prefix).
     * The splitting is done by character count to ensure all content is preserved.
     *
     * @param string $message The message to split
     * @return array Array of message chunks, each with a maximum length of 4000 characters
     */
    private function splitMessage(string $message): array
    {
        $maxLength = 4000; // Leave buffer from Telegram's 4096 limit and [X/Y] prefix
        $messageLength = strlen($message);
        
        if ($messageLength <= $maxLength) {
            return [$message];
        }
        
        $chunks = [];
        $currentPosition = 0;
        
        while ($currentPosition < $messageLength) {
            $chunk = substr($message, $currentPosition, $maxLength);
            $chunks[] = $chunk;
            $currentPosition += $maxLength;
        }
        
        return $chunks;
    }
    
    /**
     * Format a message chunk with numbering prefix
     *
     * This method adds a [current/total] prefix to each message chunk to help
     * users identify the order of messages when a long message is split into
     * multiple parts. For example, the first part of a 3-part message will
     * be prefixed with "[1/3]".
     *
     * @param string $chunk The message chunk to format
     * @param int $current Current chunk number (1-based indexing)
     * @param int $total Total number of chunks in the complete message
     * @return string Formatted chunk with [current/total] prefix
     */
    private function formatMessageChunk(string $chunk, int $current, int $total): string
    {
        return "[{$current}/{$total}] {$chunk}";
    }
    
    /**
     * Send a single telegram message
     *
     * This method executes the telegramMe command with the provided message.
     * The message is properly escaped for shell execution to prevent injection.
     *
     * @param string $message The message to send via telegramMe command
     * @return array Result array containing:
     *               - returnCode: Exit code from the telegramMe command (0 for success)
     *               - output: Command output as a string
     */
    private function sendSingleMessage(string $message): array
    {
        // Escape the message for shell execution
        $escapedMessage = escapeshellarg($message);
        
        // Execute the telegramMe command
        $command = "telegramMe {$escapedMessage}";
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        // Convert output array to string
        $outputString = implode("\n", $output);
        
        return [
            'returnCode' => $returnCode,
            'output' => $outputString
        ];
    }

    /**
     * Execute the send_telegram_message tool
     *
     * This method sends a message via Telegram, automatically handling message splitting
     * if the content exceeds 4000 characters. When splitting is required, each chunk
     * is prefixed with [current/total] numbering to maintain message order.
     *
     * @param array $arguments Tool arguments containing:
     *                         - message (string): The message content to send
     * @param mixed $configuration Configuration object (not used in this tool)
     * @return array Response containing:
     *               - content: Array with response text
     *               - isError: Boolean indicating if an error occurred
     *               - metadata: Array with additional information:
     *                 - total_chunks: Total number of message parts
     *                 - successful_sends: Number of successfully sent parts
     *                 - failed_sends: Number of failed sends
     *                 - was_split: Boolean indicating if message was split
     */
    public function execute(array $arguments, $configuration): array
    {
        $message = $arguments['message'] ?? '';

        if (!$this->validateArguments($arguments)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Message parameter is required and must be a non-empty string."
                    ]
                ],
                'isError' => true,
                'metadata' => [
                    'total_chunks' => 0,
                    'successful_sends' => 0,
                    'failed_sends' => 0,
                    'was_split' => false
                ]
            ];
        }

        try {
            // Split the message into chunks if necessary
            $chunks = $this->splitMessage($message);
            $totalChunks = count($chunks);
            
            $successfulSends = 0;
            $failedSends = 0;
            $outputs = [];
            
            foreach ($chunks as $index => $chunk) {
                // Format chunk with numbering if there are multiple chunks
                $formattedChunk = ($totalChunks > 1)
                    ? $this->formatMessageChunk($chunk, $index + 1, $totalChunks)
                    : $chunk;
                
                $result = $this->sendSingleMessage($formattedChunk);
                $outputs[] = $result['output'];
                
                if ($result['returnCode'] === 0) {
                    $successfulSends++;
                } else {
                    $failedSends++;
                }
            }
            
            // Combine all outputs for response
            $combinedOutput = implode("\n", $outputs);
            
            if ($failedSends === 0) {
                $responseText = "Telegram message(s) sent successfully. ";
                if ($totalChunks > 1) {
                    $responseText .= "Sent {$totalChunks} parts ({$successfulSends}/{$totalChunks} successful). ";
                }
                $responseText .= "Output: " . $combinedOutput;
                
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $responseText
                        ]
                    ],
                    'isError' => false,
                    'metadata' => [
                        'total_chunks' => $totalChunks,
                        'successful_sends' => $successfulSends,
                        'failed_sends' => $failedSends,
                        'was_split' => $totalChunks > 1
                    ]
                ];
            } else {
                $responseText = "Error: Failed to send some Telegram messages. ";
                $responseText .= "Sent {$successfulSends}/{$totalChunks} successfully. ";
                $responseText .= "Failed {$failedSends}/{$totalChunks}. ";
                $responseText .= "Output: " . $combinedOutput;
                
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $responseText
                        ]
                    ],
                    'isError' => true,
                    'metadata' => [
                        'total_chunks' => $totalChunks,
                        'successful_sends' => $successfulSends,
                        'failed_sends' => $failedSends,
                        'was_split' => $totalChunks > 1
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Exception occurred while sending Telegram message - " . $e->getMessage()
                    ]
                ],
                'isError' => true,
                'metadata' => [
                    'total_chunks' => 0,
                    'successful_sends' => 0,
                    'failed_sends' => 0,
                    'was_split' => false
                ]
            ];
        }
    }
}