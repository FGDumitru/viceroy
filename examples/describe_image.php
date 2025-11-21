<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Initialize connection with 5 minute timeout
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setConnectionTimeout(300); // 5 minute timeout for API calls
// Select a vision model.
$llmConnection->setLLMmodelName('Qwen3-VL-32B-Instruct-UD-Q4_K_XL'); // Set the desired model from config

// 1st example - describe and compare images.
$queryString = 'Describe the attached images. Then perform a comparison.';
echo $queryString . "\n"; // Display the query
$rolesManager = $llmConnection->getRolesManager();
$rolesManager->addMessage(
  'user',
  $queryString,
  [__DIR__ . '/images/image_1.jpg', __DIR__ . '/images/image_2.jpg']
);
// Execute the query and get streaming response.
$response = $llmConnection->queryPost(function ($chunk) {
  echo $chunk;
}); // Send POST request to LLM
$timer = $llmConnection->getLastQueryMicrotime();
echo PHP_EOL . 'Query time: ' . $llmConnection->getLastQueryMicrotime() . ' ms.' . PHP_EOL;



// 2nd example - perform OCR on image and summarize text.
$rolesManager->clearMessages(); // Clear messages for next query
$rolesManager->addMessage(
  'user',
  'Please extract all the available text from the attached image using OCR. Then, summarize the extracted text.',
  __DIR__ . '/images/ocr.png'
);
// Execute the query and get response.
$response = $llmConnection->queryPost(function ($chunk) {
  echo $chunk;
}); // Send POST request to LLM
$timer = $llmConnection->getLastQueryMicrotime();
echo PHP_EOL . 'Query time: ' . $llmConnection->getLastQueryMicrotime() . ' ms.' . PHP_EOL;
