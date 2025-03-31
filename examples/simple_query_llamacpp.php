<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$llm = new OpenAICompatibleEndpointConnection();

echo $llm->query('Tell me a joke about php and java.');
