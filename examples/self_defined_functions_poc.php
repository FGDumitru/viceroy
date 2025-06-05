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
$llm->registerPlugin($plugin);

// Define various function types
$plugin->addNewFunction('capitalize', 'Capitalize all letters from the following string: [Parameter 1]');
$plugin->addNewFunction('sum', 'Sum the following numbers: [Parameter 1], [Parameter 2], [Parameter 3]');
$plugin->addNewFunction('reverse', 'Reverse this string: [Parameter 1]');
$plugin->addNewFunction('contains', 'Does this string: [Parameter 1] contain this substring: [Parameter 2]? Answer true or false');
$plugin->addNewFunction('length', 'Return the length of this string: [Parameter 1]');
$plugin->addNewFunction('extract_json_value', 'Extract the value of key "[Parameter 1]" from this JSON: [Parameter 2]');

// Test basic functions
echo "=== Basic Function Tests ===\n";
echo "Capitalize 'hello': " . $llm->capitalize('hello') . PHP_EOL;
echo "Sum 1 + 2 + 3: " . $llm->sum(1, 2, 3) . PHP_EOL;
echo "Reverse 'abc': " . $llm->reverse('abc') . PHP_EOL;
echo "Contains 'hello world' 'world': " . $llm->contains('hello world', 'world')  ? 'True' : 'False'. PHP_EOL;
echo "Length of 'test': " . $llm->length('test') . PHP_EOL;



// Advanced chaining example
$chainLLM = new OpenAICompatibleEndpointConnection();
$chainPlugin = new SelfDefiningFunctionsPlugin();
$chainPlugin->setChainMode();

$chainLLM->registerPlugin($chainPlugin);

$chainPlugin->addNewFunction('add', 'Add all numeric values provided in the parameters. Return the total sum.');
$chainPlugin->addNewFunction('multiply', 'Multiply all numeric values provided in the parameters. Return the product.');
$chainPlugin->addNewFunction('numberToLiteral', 'Convert a numeric value to its literal form. E.g. The literal value of number 1 is one.');

// Advanced chaining with chain mode
$literal = $chainLLM->add(5, 3)->multiply(2)->numberToLiteral()->getLastResponse();
echo "Chaining result of: add(5,3)->multiply(2)->numberToLiteral()->getLastResponse() is `" . $chainPlugin->getLastResponse() . '`' . PHP_EOL;

