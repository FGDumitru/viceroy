<?php
/**
 * stream_realtime_example.php - Real-time Streaming Example
 *
 * This script demonstrates:
 * - Real-time streaming of LLM responses
 * - Chunk-by-chunk processing
 * - Response buffering and validation
 *
 * Usage:
 * php stream_realtime_example.php
 *
 * Key Features:
 * - Shows tokens as they're generated
 * - Verifies streamed vs complete response
 * - Demonstrates callback handling
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

try {
    // Initialize connection.
    // This connection will use the config.json file (if it exists). See custom config sample for more options.
    $connection = new OpenAICompatibleEndpointConnection();
    $buffered = ''; // Buffer to accumulate streamed content for later comparison

    // Display streaming header to indicate real-time output
    echo PHP_EOL . str_repeat('-', 84);
    echo PHP_EOL . str_repeat('-', 16) . " Displaying the response in real-time. ";
    echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;

    // Execute streaming query with callback for chunk processing
    $response = $connection->queryPost(
        'What is the result of 9 ^ 3? Reason about it. Adter you finish, output the following character: #',
        function ($chunk) use (&$buffered) {
            // Output each chunk as it arrives
            echo $chunk; // Output tokens as they arrive
            $buffered .= $chunk; // Accumulate in buffer
        }
    );

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

    // Display complete response after streaming
    echo PHP_EOL . str_repeat('-', 84);
    echo PHP_EOL . str_repeat('-', 16) . " Complete response after streaming ended ";
    echo PHP_EOL . str_repeat('-', 84);
    echo PHP_EOL . $response->getLlmResponseRole() . ': ' . $response->getLlmResponse() . PHP_EOL;

    // Validate streaming consistency
    echo PHP_EOL . str_repeat('-', 84);
    $areEqual = $buffered === $response->getLlmResponse() ? 'EQUAL' : 'NOT EQUAL';
    echo PHP_EOL . str_repeat('-', 16) . " Streamed vs Complete Response Comparison: $areEqual";
    echo PHP_EOL . str_repeat('-', 84) . PHP_EOL;
