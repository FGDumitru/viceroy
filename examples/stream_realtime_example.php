<?php
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

try {

    $connection = new OpenAICompatibleEndpointConnection();
    $connection->setLLMmodelName('qwen_QwQ-32B-Q8_0');

    $parameters = [
        'messages' => [
            ['role' => 'user', 'content' => 'What is x when x+5 = 10?']
        ],
        'stream' => true,
        'model' => 'qwen_QwQ-32B-Q8_0'
    ];

    echo "Streaming response:\n";
    $buf = '';
    $response = $connection->queryPost($parameters, function($chunk)  {
        echo $chunk;
    });

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo PHP_EOL . $response->getLlmResponseRole() . ': ' . $response->getLlmResponse();
