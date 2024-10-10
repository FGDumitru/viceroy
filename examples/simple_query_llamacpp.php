<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Simple\simpleLlamaCppOAICompatibleConnection;

$llm = new simpleLlamaCppOAICompatibleConnection();
echo $llm->query('Tell me a joke about php and java.');
