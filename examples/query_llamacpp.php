<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$llmConnection = new OpenAICompatibleEndpointConnection();

// Timeout usage example, wait 5 minutes before timing out.
$llmConnection->setGuzzleConnectionTimeout(300);


// Add a system message (if the model supports it).
$llmConnection->getRolesManager()
  ->clearMessages()
  ->setSystemMessage('You are a helpful LLM that responds to user queries.');

// Query the model as a User.
try {
  $queryString = 'Is the number 9.11 larger than 9.9? Respond only with either [YES] or [NO].';
  echo $queryString . "\n";
  
  $llmConnection
    ->setParameter('temperature', 0.3)
    ->setParameter('top_p', 0.5)
    ->getRolesManager()
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

  // Test the output for model sanity.
  if (str_contains(strtolower($content), '[no]')) {
    echo "\nThe LLM responded correctly!\n";
  }
  else {
    echo "\nThe LLM has NOT responded correctly!\n";
  }

  echo 'Query time: ' . $llmConnection->getLastQueryMicrotime() . " ms.\n";
}
else {
  echo "\nCould not get a response from the LLM\n";
}
