<?php
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

try {

    $connection = new OpenAICompatibleEndpointConnection();

    $buffered = '';

    echo PHP_EOL . str_repeat('-', 84);
    echo PHP_EOL . str_repeat('-', 16) . " Displaying the response in real-time. ";
    echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;
    $buf = '';
    $response = $connection->queryPost('What is the result of 9 ^ 3? Reason about it.', function ($chunk) use (&$buffered) {
        echo $chunk; // Here are the tokens as they are received from the server.
        $buffered .= $chunk;
    });

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo PHP_EOL . str_repeat('-', 84);
echo PHP_EOL . str_repeat('-', 16) . " Recalling the response after the reaming has ended. ";
echo PHP_EOL . str_repeat('-', 84);
echo PHP_EOL . $response->getLlmResponseRole() . ': ' . $response->getLlmResponse() . PHP_EOL;

// As a sanity check, let's compare the buffered response with the final LLM response.
echo PHP_EOL . str_repeat('-', 84);
$areEqual = $buffered === $response->getLlmResponse() ? 'EQUAL' : 'NOT EQUAL';
echo PHP_EOL . str_repeat('-', 16) . " Comparing the buffered string with the recall string: $areEqual";
echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;
