<?php
/**
 * chat_sample.php - Interactive Chat Interface Example
 *
 * This script provides a command-line chat interface for interacting with LLM models.
 * It demonstrates advanced features like real-time streaming, performance monitoring,
 * and terminal-based UI enhancements.
 *
 * Key Features:
 * 1. Real-Time Streaming: Shows AI responses character-by-character as they're generated
 * 2. Colorized Output: Uses ANSI escape codes for role-based message coloring (User/Assistant/System)
 * 3. Conversation History: Maintains context for multi-turn interactions (currently placeholder implementation)
 * 4. Performance Metrics: Displays tokens-per-second rate during response generation
 * 5. Graceful Error Handling: Catches exceptions and displays user-friendly error messages
 * 6. Command-Line Controls: Supports "q!" to exit and "-nocolor" to disable terminal colors
 *
 * Usage:
 * php chat_sample.php [-nocolor]
 *
 * Architecture:
 * - Uses OpenAI-compatible connection for LLM communication
 * - Implements message formatting with role-based styling
 * - Maintains conversation state through chatHistory array
 * - Leverages streaming callbacks for real-time output
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$useColors = !in_array('-nocolor', $argv);
$chatHistory = [];

/**
 * Formats chat message with timestamp and color coding
 *
 * @param string $role 'User' or 'AI'
 * @param string $message The message content
 * @param bool $useColors Whether to use terminal colors
 * @return string Formatted message string
 */
function formatMessage($role, $message, $useColors) {
    // Generate timestamped message with role-based styling
    $timestamp = date('Y-m-d H:i:s');
    $baseFormat = "[$timestamp] ";

    if ($useColors) {
        // ANSI color codes for terminal styling
        $reset = "\033[0m"; // Reset all formatting
        $bold = "\033[1m";  // Bold text
        $colors = [
            'User' => "\033[34m",    // Blue for user messages
            'AI' => "\033[32m",     // Green for AI responses
            'System' => "\033[33m"  // Yellow for system messages
        ];

        // Apply role-specific styling
        $roleColor = $colors[$role] ?? $reset;
        return $baseFormat . $bold . $roleColor . $role . $reset . ': ' . $message;
    } else {
        // Fallback for non-color terminals
        return $baseFormat . $role . ': ' . $message;
    }
}

/**
 * Displays a chat message with formatting and separators
 *
 * @param string $role 'User', 'AI' or 'System'
 * @param string $message The message content
 * @param bool $useColors Whether to use terminal colors
 */
function displayChatMessage(string $role, string $message, bool $useColors): void
{
    // Render formatted message with visual separators
    $formatted = formatMessage($role, $message, $useColors);

    // Create 84-character wide borders for consistent UI layout
    $separator = str_repeat('-', 84);
    echo PHP_EOL . $separator . PHP_EOL; // Top border
    echo $formatted . PHP_EOL;           // Formatted message content
    echo $separator . PHP_EOL;           // Bottom border
}

try {
    // Initialize LLM connection and model configuration
    $connection = new OpenAICompatibleEndpointConnection();
    $currentModel = $connection->getLLMmodelName();
    
    // Show startup message with usage instructions
    displayChatMessage('System', 'Chat interface initialized. Type your message or "q!" to quit.', $useColors);
    
    while (true) {
        // User input prompt with role indicator
        echo PHP_EOL . formatMessage('User', '', $useColors);
        $userInput = trim(fgets(STDIN));
        
        // Handle exit command and empty input
        if (strtolower($userInput) === 'q!') {
            displayChatMessage('System', 'Chat session ended.', $useColors);
            break;
        }
        if (empty($userInput)) continue;
        
        // Prepare for response handling
        $bufferedResponse = '';
        displayChatMessage('AI', "Thinking... (Current model: $currentModel)", $useColors);
        
        // Send query with streaming callback for real-time output
        $response = $connection->query(
            $userInput,
            // Streaming callback function
            function($chunk, $tps) use (&$bufferedResponse, $useColors) {
                // Display real-time response chunk with color coding
                $outputChunk = $useColors ? "\033[32m$chunk\033[0m" : $chunk;
                echo $outputChunk; // Immediate terminal output
                $bufferedResponse .= $chunk; // Accumulate raw response content
            }
        );
        
        // Update conversation history (currently only stores AI responses)
        $chatHistory[] = ['role' => 'AI', 'content' => $bufferedResponse];
        
        // Display tokens-per-second metric after response completion
        $tps = $connection->getCurrentTokensPerSecond();
        echo PHP_EOL . "Performance: $tps token(s)/second" . PHP_EOL;
    }
    
} catch (Exception $e) {
    // Graceful error handling with formatted message
    displayChatMessage('Error', "Fatal error: " . $e->getMessage(), $useColors);
    exit(1);
}
