<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;


require_once '../vendor/autoload.php';
$llmConnection = new OpenAICompatibleEndpointConnection();

if ($useGrok && $authorization = getenv('GrokAPI')) {
  echo "\n\tUsing Grok API\n";
  $llmConnection->setEndpointTypeToGroqAPI();
  $llmConnection->setLLMmodelName('llama-3.2-90b-vision-preview');
  $llmConnection->setBearedToken($authorization);
} else {
  echo "\n\tUsing default API.\n";
}

// Add a system message (if the model supports it).
$llmConnection->setSystemMessage('You are a helpful LLM that responds to user queries.');

$userQuery = 'Which animal can bark between a "dog" and a "cat"? Respond using a single word.';
echo PHP_EOL .'user: ' . $userQuery . PHP_EOL;

// This is the initial response.
$response = $llmConnection->query($userQuery);
echo 'assistant: ' . $response . PHP_EOL;

// Now ask an additional question.
$queryString = 'Also, what is the sum of the number of letters from each animal\'s name? Respond using a single value inside a JSON object, in the "result" key.';
echo "\nuser: $queryString\n";
$response = $llmConnection->query($queryString);

// This is the second LLM response.
echo 'assistant: ' . $response . PHP_EOL;

// Now ask an additional question.
$queryString = 'Actually... I need you to to translate into Romanian my original question.';
echo "\nuser: $queryString\n";
$response = $llmConnection->query($queryString);

// This is the second LLM response.
echo 'assistant: ' . $response . PHP_EOL;
