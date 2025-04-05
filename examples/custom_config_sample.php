<?php

require_once '../vendor/autoload.php';

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// You can initialize an endpoint with your custom settings array.
// Or create the config object ahead of time and pass it as a parameter.
$llm = new OpenAICompatibleEndpointConnection(
    new ConfigObjects( // Define a connection at execution time.
        [
            'apiEndpoint' => 'http://127.0.0.1:8855', // Specify a custom API endpoint.
            'bearedToken' => '8855',
            'preferredModel' => 'gpt-4o'
        ]
    )
);


try {
    echo $llm->query('Enumerate rainbow colors.') . PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

$timings = $llm->getQuerytimings();
