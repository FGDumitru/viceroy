<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// This connection will use the config.json file (if it exists). See custom config sample for more options.
$llm = new OpenAICompatibleEndpointConnection();

try {
    echo $llm->query('Tell me a joke about php and java.') . PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

$timings = $llm->getQuerytimings();
