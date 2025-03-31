<?php
/**
 * roles_llamacpp.php - Multi-turn Conversation Example
 *
 * This script demonstrates:
 * - Role-based conversation management
 * - Context maintenance across turns
 * - Building on previous responses
 * - Different response formats
 *
 * Usage:
 * php roles_llamacpp.php
 *
 * Conversation Flow:
 * 1. Initial question about animals
 * 2. Follow-up math question
 * 3. Translation request
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
// Initialize connection with system message
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setSystemMessage('You are a helpful LLM that responds to user queries.'); // Sets default behavior

// First turn: Simple factual question with strict response format
$userQuery = 'Which animal can bark between a "dog" and a "cat"? Respond using a single word.';
echo PHP_EOL .'user: ' . $userQuery . PHP_EOL; // Display user query

// Get and display response (maintains conversation state internally)
$response = $llmConnection->query($userQuery);
echo 'assistant: ' . $response . PHP_EOL; // Expected: "dog" (single word)

// Second turn: Math follow-up building on previous context
$queryString = 'Also, what is the sum of the number of letters from each animal\'s name? Respond using a single value inside a JSON object, in the "result" key.';
echo "\nuser: $queryString\n"; // Uses previous answer ("dog"=3, "cat"=3)
$response = $llmConnection->query($queryString);

echo 'assistant: ' . $response . PHP_EOL; // Expected: {"result":6} (dog=3 + cat=3)

// Third turn: Translation request
$queryString = 'Actually... I need you to to translate into Romanian my original question.';
echo "\nuser: $queryString\n";
$response = $llmConnection->query($queryString);

echo 'assistant: ' . $response . PHP_EOL; // Expected: Romanian translation
