<?php
// Load Composer autoloader to access Viceroy library classes
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Check if -nocolor flag is present to disable ANSI color codes
$useColors = !in_array('-nocolor', $argv);

/*************************
 * FUNCTION DEFINITIONS *
 *************************/

// Formats messages with timestamps, colors, and role styling
function formatMessage($role, $message, $useColors) {
    $timestamp = date('Y-m-d H:i:s'); // Current timestamp
    
    $formatted = "[$timestamp] ";
    
    if ($useColors) {
        // ANSI escape codes for styling
        $reset = "\033[0m";          // Reset all formatting
        $bold = "\033[1m";           // Bold text
        $userColor = "\033[34m";     // Blue for user messages
        $assistantColor = "\033[32m";// Green for assistant
        
        // Apply color based on role
        $roleColor = ($role === 'You') ? $userColor : $assistantColor;
        
        // Construct styled message
        $formatted .= $bold . $roleColor . $role . $reset . ': ' . $message;
    } else {
        // Plain text formatting without colors
        $formatted .= $role . ': ' . $message;
    }
    
    return $formatted;
}

// Displays formatted messages with decorative borders
function displayChatMessage($role, $message, $useColors) {
    $formattedMessage = formatMessage($role, $message, $useColors);
    
    // Print top border
    echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;
    
    // Print formatted message
    echo $formattedMessage . PHP_EOL;
    
    // Print bottom border
    echo str_repeat('-', 84) . PHP_EOL;
}

/*************************
 * MAIN PROGRAM FLOW    *
 *************************/

try {
    // Initialize connection to LLM service
    $connection = new OpenAICompatibleEndpointConnection();
    
    // Show startup message
    displayChatMessage('System', 'Chat interface initialized. Type your message or "exit" to quit.', $useColors);
    
    // Main chat loop
    while (true) {
        // Prompt user for input
        echo PHP_EOL . formatMessage('You', '', $useColors);
        $userInput = trim(fgets(STDIN));
        
        // Exit condition
        if (strtolower($userInput) === 'exit') {
            displayChatMessage('System', 'Chat session ended.', $useColors);
            break;
        }
        
        // Skip empty messages
        if (empty($userInput)) continue;
        
        // Prepare response buffer
        $bufferedResponse = '';
        
        // Show thinking indicator
        displayChatMessage('AI', 'Thinking...', $useColors);
        
        // Query the LLM with real-time streaming
        $response = $connection->queryPost($userInput, function($chunk) use (&$bufferedResponse, $useColors) {
            // Display each chunk immediately
            if ($useColors) {
                echo "\033[32m" . $chunk . "\033[0m"; // Green text for AI response
            } else {
                echo $chunk;
            }
            
            // Accumulate full response
            $bufferedResponse .= $chunk;
        });
        
        // Ensure proper line break after response
        echo PHP_EOL;
    }
    
} catch (Exception $e) {
    // Handle errors gracefully
    displayChatMessage('Error', $e->getMessage(), $useColors);
    exit(1);
}