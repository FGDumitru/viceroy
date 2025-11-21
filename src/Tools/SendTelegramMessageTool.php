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
                'description' => 'Sends a telegram message to the user. Automatically splits long messages (>4000 characters) into multiple parts with intelligent boundary detection and [part/total] numbering.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'The message content to send via Telegram. If longer than 4000 characters, it will be automatically split into multiple messages using intelligent boundary detection (paragraphs, sentences, words) while preserving URLs.'
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
     * Split a message into chunks of maximum 4000 characters using intelligent boundaries
     *
     * This method splits a message that exceeds Telegram's 4096 character limit into
     * smaller chunks using a priority-based approach:
     * 1. Paragraph boundaries (\n\n)
     * 2. Line boundaries (\n)
     * 3. Sentence endings (., !, ? followed by space)
     * 4. Word boundaries (spaces)
     * 5. Last resort: character boundaries
     *
     * Links and URLs are protected from splitting and moved entirely to the next chunk if needed.
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
        $remainingText = $message;
        
        while (!empty($remainingText)) {
            $chunk = $this->extractIntelligentChunk($remainingText, $maxLength);
            $chunks[] = $chunk;
            $remainingText = substr($remainingText, strlen($chunk));
        }
        
        return $chunks;
    }
    
    /**
     * Extract an intelligent chunk from the beginning of text
     *
     * @param string $text The text to extract from
     * @param int $maxLength Maximum length of the chunk
     * @return string The extracted chunk
     */
    private function extractIntelligentChunk(string $text, int $maxLength): string
    {
        // If the entire text fits, return it
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        // First, check if we have URLs that would be split and move them to next chunk
        $urlSplitPos = $this->handleUrlBoundaries($text, $maxLength);
        if ($urlSplitPos !== false && $urlSplitPos > 0) {
            return substr($text, 0, $urlSplitPos);
        }
        
        // Get the candidate chunk (max length)
        $candidate = substr($text, 0, $maxLength);
        
        // Try to split at paragraph boundaries first
        $splitPos = $this->findBestSplitPosition($candidate, $maxLength, "\n\n");
        if ($splitPos !== false) {
            return substr($text, 0, $splitPos);
        }
        
        // Try to split at line boundaries
        $splitPos = $this->findBestSplitPosition($candidate, $maxLength, "\n");
        if ($splitPos !== false) {
            return substr($text, 0, $splitPos);
        }
        
        // Try to split at sentence boundaries
        $splitPos = $this->findSentenceBoundary($candidate, $maxLength);
        if ($splitPos !== false) {
            return substr($text, 0, $splitPos);
        }
        
        // Try to split at word boundaries
        $splitPos = $this->findWordBoundary($candidate, $maxLength);
        if ($splitPos !== false) {
            return substr($text, 0, $splitPos);
        }
        
        // Absolute last resort: split at max length
        return $candidate;
    }
    
    /**
     * Find the best split position for a given delimiter
     *
     * @param string $text The text to search in
     * @param int $maxLength Maximum allowed length
     * @param string $delimiter The delimiter to split on
     * @return int|false The split position or false if not found
     */
    private function findBestSplitPosition(string $text, int $maxLength, string $delimiter): int|false
    {
        $lastPos = strrpos($text, $delimiter);
        if ($lastPos !== false && $lastPos > 0) {
            return $lastPos + strlen($delimiter);
        }
        return false;
    }
    
    /**
     * Find sentence boundaries (., !, ? followed by space)
     *
     * @param string $text The text to search in
     * @param int $maxLength Maximum allowed length
     * @return int|false The split position or false if not found
     */
    private function findSentenceBoundary(string $text, int $maxLength): int|false
    {
        // Look for sentence endings followed by space
        $patterns = ['\. ', '! ', '? '];
        
        $bestPos = false;
        
        foreach ($patterns as $pattern) {
            $offset = 0;
            while (($pos = strpos($text, $pattern, $offset)) !== false) {
                $splitPos = $pos + strlen($pattern);
                if ($splitPos < $maxLength) {
                    // Keep track of the best (closest to maxLength) position
                    if ($bestPos === false || $splitPos > $bestPos) {
                        $bestPos = $splitPos;
                    }
                }
                $offset = $pos + 1;
            }
        }
        
        return $bestPos;
    }
    
    /**
     * Find word boundaries (spaces) that don't break URLs
     *
     * @param string $text The text to search in
     * @param int $maxLength Maximum allowed length
     * @return int|false The split position or false if not found
     */
    private function findWordBoundary(string $text, int $maxLength): int|false
    {
        // Find the last space before max length
        $lastSpace = strrpos(substr($text, 0, $maxLength), ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            // Make sure we're not in the middle of a URL
            $beforeSpace = substr($text, 0, $lastSpace);
            $afterSpace = substr($text, $lastSpace + 1);
            
            if (!$this->isUrlFragment($afterSpace) && !$this->isUrlFragment($beforeSpace)) {
                return $lastSpace;
            }
        }
        
        return false;
    }
    
    /**
     * Handle URL boundaries by moving entire URLs to next chunk
     *
     * @param string $text The text to analyze
     * @param int $maxLength Maximum allowed length
     * @return int|false The split position or false if not found
     */
    private function handleUrlBoundaries(string $text, int $maxLength): int|false
    {
        // Look for URLs near the boundary
        $urlPattern = '/https?:\/\/[^\s]+/i';
        
        if (preg_match_all($urlPattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $urlStart = $match[1];
                $urlEnd = $urlStart + strlen($match[0]);
                
                // If URL spans across the boundary, split before it
                if ($urlStart < $maxLength && $urlEnd > $maxLength) {
                    // Try to find a good split point before the URL
                    $beforeUrl = substr($text, 0, $urlStart);
                    
                    // Try to find a sentence or word boundary before the URL
                    $sentencePos = $this->findSentenceBoundary($beforeUrl, $urlStart);
                    if ($sentencePos !== false && $sentencePos > 0) {
                        return $sentencePos;
                    }
                    
                    // Try word boundary
                    $wordPos = strrpos($beforeUrl, ' ');
                    if ($wordPos !== false && $wordPos > 0) {
                        return $wordPos;
                    }
                    
                    // Last resort: split right before the URL
                    return $urlStart;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if a text fragment is part of a URL
     *
     * @param string $text The text to check
     * @return bool True if it appears to be part of a URL
     */
    private function isUrlFragment(string $text): bool
    {
        // Check for common URL patterns
        $urlPatterns = [
            '/^https?:\/\//i',
            '/www\./i',
            '/\.com$/i',
            '/\.org$/i',
            '/\.net$/i',
            '/\.io$/i',
            '/\.gov$/i',
            '/\.edu$/i'
        ];
        
        foreach ($urlPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
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