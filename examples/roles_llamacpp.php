<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\llamacppOAICompatibleConnection;

$llmConnection = new llamacppOAICompatibleConnection();

// Check the endpoint health.
if (!$llmConnection->health()) {
  die('The LLM health status is not valid! Check the server connection.');
}

// Add a system message (if the model supports it).
$llmConnection->setSystemMessage('You are a helpful LLM that responds to user queries.');

$userQuery = 'Which animal can bark between a "dog" and a "cat"? Respond using a single word.';
echo PHP_EOL .'user: ' . $userQuery . PHP_EOL;

// This is the initial response.
$response = $llmConnection->query($userQuery);
echo 'assistant: ' . $response . PHP_EOL;

// Now ask an additional question.
$queryString = 'Also, what is the sum of the number of letter from each animal\'s name? Respond using a JSON object with the "result" key.';
echo "\nuser: $queryString\n";
$response = $llmConnection->query($queryString);

// This is the second LLM response.
echo 'assistant: ' . $response . PHP_EOL;
