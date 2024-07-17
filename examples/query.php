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
  $llmConnection->getRolesManager()
    ->addMessage('user', 'Is the number 9.11 larger than 9.9? Respond only with either [YES] or [NO].');
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
  
  // Test the output for model sanity.
  if (strpos(strtolower($content), '[no]')) {
    echo "\nThe LLM responded correctly!\n";
  }
  else {
    echo "\nThe LLM did NOT responded correctly!\n";
  }

  echo 'Query time: ' . $llmConnection->getLastQueryMicrotime() . " ms.\n";
}
else {
  echo "\nCould not get a response from the LLM\n";
}
