<?php
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$useColors = !in_array('-nocolor', $argv);
$chatHistory = [];

function formatMessage($role, $message, $useColors) {
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] ";
    
    if ($useColors) {
        $reset = "\033[0m";
        $bold = "\033[1m";
        $userColor = "\033[34m";
        $assistantColor = "\033[32m";
        
        $roleColor = ($role === 'User') ? $userColor : $assistantColor;
        $formatted .= $bold . $roleColor . $role . $reset . ': ' . $message;
    } else {
        $formatted .= $role . ': ' . $message;
    }
    
    return $formatted;
}

function displayChatMessage($role, $message, $useColors) {
    $formatted = formatMessage($role, $message, $useColors);
    echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;
    echo $formatted . PHP_EOL;
    echo str_repeat('-', 84) . PHP_EOL;
}

try {
    $connection = new OpenAICompatibleEndpointConnection();

    $currentModel = $connection->getLLMmodelName();
    
    displayChatMessage('System', 'Chat interface initialized. Type your message or "q!" to quit.', $useColors);
    
    while (true) {
        echo PHP_EOL . formatMessage('User', '', $useColors);
        $userInput = trim(fgets(STDIN));
        
        if (strtolower($userInput) === 'q!') {
            displayChatMessage('System', 'Chat session ended.', $useColors);
            break;
        }
        
        if (empty($userInput)) continue;

        $bufferedResponse = '';
        displayChatMessage('AI', 'Thinking... (model: ' . $currentModel . ')', $useColors);

        // Send full history in prompt
        $response = $connection->query($userInput, function($chunk, $tps) use (&$bufferedResponse, $useColors) {

            if ($useColors) {
                echo "\033[32m" . $chunk . "\033[0m";
            } else {
                echo $chunk;
            }

            $bufferedResponse .= $chunk;
        });

        // Add AI response to history
        $chatHistory[] = ['role' => 'AI', 'content' => $bufferedResponse];
        echo PHP_EOL;

        $tps = $connection->getCurrentTokensPerSecond();
        echo "[$tps token(s)s per second)]" . PHP_EOL;
    }
    
} catch (Exception $e) {
    displayChatMessage('Error', $e->getMessage(), $useColors);
    exit(1);
}

