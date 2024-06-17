<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\OpenAiCompatibleConnection;

$test = new OpenAiCompatibleConnection();
$test->getRolesManager()->clearMessages()->setSystemMessage('You are a helpful LLM that responds to user queries in great detail.');
$test->getRolesManager()->addMessage('user','What is the result of 4+4? Respond only with the result.');

//$configData = $test->getConfiguration()->getFullConfigData();
//$configData['server']['host'] = 'http://192.168.0.115';
//$test->getConfiguration()->setFullConfigData($configData);


$raspuns = $test->queryPost();
echo $raspuns->getLlmResponseRole() . ': ' . $raspuns->getLlmResponse();





