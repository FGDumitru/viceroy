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
$llmConnection->setGuzzleConnectionTimeout(300); // 5 minute timeout for API calls


// Configure conversation context with system message
$llmConnection->getRolesManager()
  ->clearMessages() // Reset any previous conversation
  ->setSystemMessage('You are a helpful LLM that responds to user queries.'); // Set behavior instructions

// Execute query with controlled parameters
try {
  // Define query with strict response format requirements
  $queryString = 'Is the number 9.11 larger than 9.9? Respond only with either [YES] or [NO].';
  echo $queryString . "\n"; // Display the query
  
  // Configure generation parameters
  $llmConnection
    ->setParameter('temperature', 0.3)  // Low temperature for deterministic output
    ->setParameter('top_p', 0.5)       // Restrict sampling to top 50% probability mass
    ->getRolesManager()
    ->addMessage('user', $queryString); // Add user query to conversation
}
catch (Exception $e) {
  echo($e->getMessage());
  die(1);
}

// Execute the query and get response
$response = $llmConnection->queryPost(); // Send POST request to LLM

if ($response) {
  // Display response with role prefix (e.g. "assistant: ")
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
