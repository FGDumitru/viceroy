<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Plugins\SelfDefiningFunctionsPlugin;

/**
 * Self-Defined Functions Proof of Concept
 *
 * Demonstrates how to use the SelfDefiningFunctionsPlugin to dynamically
 * create and call functions through the LLM connection, including function chaining.
 */

$llm = new OpenAICompatibleEndpointConnection();
$plugin = new SelfDefiningFunctionsPlugin();
$llm->addPlugin($plugin);

// Define various function types
$plugin->addNewFunction('capitalize', 'Capitalize all letters from the following string: [Parameter 1]');
$plugin->addNewFunction('sum', 'Sum the following numbers: [Parameter 1], [Parameter 2], [Parameter 3]');
$plugin->addNewFunction('reverse', 'Reverse this string: [Parameter 1]');
$plugin->addNewFunction('contains', 'Does this string: [Parameter 1] contain this substring: [Parameter 2]? Answer true or false');
$plugin->addNewFunction('length', 'Return the length of this string: [Parameter 1]');
$plugin->addNewFunction('extract_json_value', 'Extract the value of key "[Parameter 1]" from this JSON: [Parameter 2]');

// Test basic functions
echo "=== Basic Function Tests ===\n";
echo "Capitalize 'hello': " . json_decode($llm->capitalize('hello'))->response . PHP_EOL;
echo "Sum 1 + 2 + 3: " . json_decode($llm->sum(1, 2, 3))->response . PHP_EOL;
echo "Reverse 'abc': " . json_decode($llm->reverse('abc'))->response . PHP_EOL;
echo "Contains 'hello world' 'world': " . json_decode($llm->contains('hello world', 'world'))->response . PHP_EOL;
echo "Length of 'test': " . json_decode($llm->length('test'))->response . PHP_EOL;

// Test function chaining
echo "\n=== Function Chaining Tests ===\n";

// Basic chaining examples
$reversedCapitalized = $llm->reverse($llm->capitalize('chain'));
echo "Reverse of capitalized 'chain': " . json_decode($reversedCapitalized)->response . PHP_EOL;

$jsonResponse = $llm->capitalize('test');
var_dump($jsonResponse);
die;
$extractedValue = $llm->extract_json_value('response', $jsonResponse);
echo "Extracted value from JSON response: " . $extractedValue . PHP_EOL;
die;
// Advanced chaining example
$chainLLM = new OpenAICompatibleEndpointConnection();
$chainPlugin = new SelfDefiningFunctionsPlugin();
$chainLLM->addPlugin($chainPlugin);

$chainPlugin->addNewFunction('add', 'Add all numeric values provided in the parameters. Return the total sum.');
$chainPlugin->addNewFunction('multiply', 'Multiply all numeric values provided in the parameters. Return the product.');
$chainPlugin->addNewFunction('numberToLiteral', 'Convert a numeric value to its literal form.');

// Advanced chaining with chain mode
$chainLLM->setChainMode(true);
$literal = $chainLLM->add(5, 3)->multiply(2)->numberToLiteral()->getLastResponse();
echo "Chaining result (add(5,3)->multiply(2)->numberToLiteral()): " . json_decode($literal)->response . PHP_EOL;

// Test error case
echo "\n=== Error Handling Test ===\n";
try {
    echo "Calling undefined function: " . $llm->undefinedFunction() . PHP_EOL;
} catch (Exception $e) {
    echo "Error caught: " . $e->getMessage() . PHP_EOL;
}

// Debug output
echo "\n=== Debug Information ===\n";
echo "Last Prompt Sent:\n";
print_r($llm->getRolesManager()->getMessages());
