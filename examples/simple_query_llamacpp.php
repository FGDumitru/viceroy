<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$llm = new OpenAICompatibleEndpointConnection();

try {
    echo $llm->query('Tell me a joke about php and java.') . PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

$timings = $llm->getQuerytimings();
