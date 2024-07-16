<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\OpenAiCompatibleConnection;

$test = new OpenAiCompatibleConnection();
$test->getRolesManager()->clearMessages()->setSystemMessage('You are a helpful LLM that responds to user queries in great detail.');
try {
  $test->getRolesManager()
    ->addMessage('user', 'What is the capital of Romania?');
}
catch (Exception $e) {
  var_dump($e->getMessage());
  die(1);
}

var_dump($test->health());

//die;
$tokens = $test->tokenize('This is a test!');
var_dump($tokens);

if ($tokens) {
  $det = $test->detokenize($tokens);
  var_dump($det);
}

$raspuns = $test->queryPost();
echo $raspuns->getLlmResponseRole() . ': ' . $raspuns->getLlmResponse();
$content = $raspuns->getRawContent();
var_dump(json_decode($content));
