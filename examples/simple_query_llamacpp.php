<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Simple\simpleLlamaCppOAICompatibleConnection;

$llm = new simpleLlamaCppOAICompatibleConnection();

$llm->setLLMmodelName('gpt-4o');

echo $llm->query('Tell me a joke about php and java.');
