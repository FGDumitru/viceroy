<?php
/**
 * query_llamacpp.php - Basic LLM Query Example
 *
 * This script demonstrates:
 * - Establishing a connection to LLM
 * - Setting system and user messages
 * - Configuring generation parameters
 * - Processing and validating responses
 *
 * Usage:
 * php query_llamacpp.php
 *
 * Key Features:
 * - Shows basic query/response flow
 * - Includes parameter tuning examples
 * - Measures response time
 * - Performs simple output validation
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Initialize connection with 5 minute timeout
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setGuzzleConnectionTimeout(300); // 5 minutes


// Configure conversation context
$llmConnection->getRolesManager()
  ->clearMessages()
  ->setSystemMessage('You are a helpful LLM that responds to user queries.');

// Execute query with controlled parameters
try {
  $queryString = 'Is the number 9.11 larger than 9.9? Respond only with either [YES] or [NO].';
  echo $queryString . "\n";
  
  $llmConnection
    ->setParameter('temperature', 0.3)  // Lower = more deterministic
    ->setParameter('top_p', 0.5)       // Nucleus sampling threshold
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
