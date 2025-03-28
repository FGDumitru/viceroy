<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Simple\simpleLlamaCppOAICompatibleConnection;

$llm = new simpleLlamaCppOAICompatibleConnection();

$llm->setLLMmodelName('qwen_QwQ-32B-Q8_0');

echo $llm->query('Tell me a joke about php and java.');
