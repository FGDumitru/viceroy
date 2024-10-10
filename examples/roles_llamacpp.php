<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\llamacppOAICompatibleConnection;

$llmConnection = new llamacppOAICompatibleConnection();

// Check the endpoint health.
if (!$llmConnection->health()) {
  die('The LLM health status is not valid!');
}

// Add a system message (if the model supports it).
$llmConnection->getRolesManager()
  ->clearMessages()
  ->setSystemMessage('You are a helpful LLM that responds to user queries.');

// Query the model as a User.
try {
  $queryString = 'Which animal can bark between a "dog" and a "cat"? Respond using a single word.';
  echo "\nuser: $queryString\n";
  $llmConnection->getRolesManager()
    ->addMessage('user', $queryString);
}
catch (Exception $e) {
  echo($e->getMessage());
  die(1);
}

// Perform the actual LLM query.
$response = $llmConnection->queryPost();

if ($response) {
  echo $response->getLlmResponseRole() . ': ' . $response->getLlmResponse();

  $content = $response->getLlmResponse();
  $timer = $llmConnection->getLastQueryMicrotime();
}
else {
  echo "\nCould not get a response from the LLM\n";
}

// Add the LLM response.
$llmConnection->getRolesManager()
  ->addMessage('assistant', $content);


// Now ask an additional question.
$queryString = 'Also, what is the sum of the number of letter from each animal\'s name? Respond using a JSON object with the "result" key.';
echo "\nuser: $queryString\n";
$llmConnection->getRolesManager()
  ->addMessage('user', $queryString);
$response = $llmConnection->queryPost();
if ($response) {
  echo $response->getLlmResponseRole() . ': ' . $response->getLlmResponse() . PHP_EOL;
}