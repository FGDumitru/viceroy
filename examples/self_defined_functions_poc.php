<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();
// Enable debug output to see actual LLM response:
 $llm->setDebugMode(true);

$models = $llm->getModels();
sort($models);
$models = array_reverse($models);

$modelId = 'gpt-4o'; // Ensure this matches your model
$modelId = 'Llama-3.3-70B-Instruct-Q4_K_M_CTX-24K'; // Ensure this matches your model
$llm->setLLMmodelName($modelId);
$llm->setEndpointTypeToLlamaCpp(); // Explicitly set endpoint type

$llm->setConnectionTimeout(0);

// Define dynamic functions
$llm->addNewFunction('adunare', 'Perform addition on the numeric values provided in each parameter. Return the sum.');
$llm->addNewFunction('countWords', 'Count the words in each parameter. If multiple parameters are provided, return the sum of their word counts.');
$llm->addNewFunction('wordsToArrayAllCaps', 'Convert the text in each parameter into an array of words. Capitalize each word in the resulting arrays.');
$llm->addNewFunction('reverseWordsOrder', 'Reverse the order of words in the provided text parameter.');
$llm->addNewFunction('translate', 'Translate the text in the second parameter into the target language specified by the language code in the first parameter. Do your best to figure out the input and output languages and do not output anything other than the translation.');
$llm->addNewFunction('validEmail', 'Check if the email address provided in the first parameter is valid. Return TRUE for a valid email or FALSE if it is invalid.');
$llm->addNewFunction('leetSpeak', 'Transform the following parameters into l33t speak.');
$llm->addNewFunction('reverseString', 'Reverse the string in the first parameter. Ensure the entire string is reversed, including handling of special characters and spaces.');

// Color constants
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[31m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_MAGENTA', "\033[35m");
define('COLOR_CYAN', "\033[36m");

function testFunction($llm, $functionName, $expected, ...$args) {
    echo COLOR_CYAN . "Testing $functionName()..." . COLOR_RESET . PHP_EOL;
    echo COLOR_YELLOW . "Input: " . COLOR_RESET . json_encode($args) . PHP_EOL;
    
    try {
        $result = $llm->$functionName(...$args);
        $passed = $functionName === 'leetSpeak' 
            ? preg_match('/h[3@]ll[o0] w[o0]r[l1]d[!1]/i', $result)
            : $result == $expected;
        
        echo COLOR_YELLOW . "Result: " . COLOR_RESET . json_encode($result) . PHP_EOL;
        echo COLOR_YELLOW . "Expected: " . COLOR_RESET . json_encode($expected) . PHP_EOL;
        echo $passed 
            ? COLOR_GREEN . "✓ PASSED" . COLOR_RESET . PHP_EOL 
            : COLOR_RED . "✗ FAILED" . COLOR_RESET . PHP_EOL;
    } catch (Exception $e) {
        echo COLOR_RED . "Error: " . $e->getMessage() . COLOR_RESET . PHP_EOL;
    }
    
    echo str_repeat('-', 50) . PHP_EOL;
}

// Test dynamic functions
testFunction($llm, 'reverseString', '!aedi dna gnirts gnitseretnI yreV a si sihT', 'This is a Very Interesting string and idea!');
testFunction($llm, 'leetSpeak', 'H3ll0 w0rld!', 'Hello world!');
testFunction($llm, 'adunare', 9, 5, 4);
testFunction($llm, 'adunare', 14, 5, 4, 5);
testFunction($llm, 'countWords', 5, 'This is a simple test');
testFunction($llm, 'countWords', 9, 'This is a simple test', 'some additional words here');
testFunction($llm, 'reverseWordsOrder', 'test simple a is This', 'This is a simple test');
testFunction($llm, 'translate', 'Ce mai este nou?', 'romanian', 'What else is new?');
testFunction($llm, 'translate', 'Marul este rosu', 'ROmanian', 'La pomme est rouge.');
testFunction($llm, 'translate', 'La pomme est jaune.', 'french', 'Mărul este galben.');
testFunction($llm, 'translate', 'The apple is red', 'English', 'सेब लाल है।');
testFunction($llm, 'wordsToArrayAllCaps', ['THIS', 'IS', 'A', 'SIMPLE', 'TEST'], 'This is a simple test');
testFunction($llm, 'validEmail', false, 'un test@k');
testFunction($llm, 'validEmail', true, 'un_test@k.com');

// Chaining mode example
try {
    $chainLLM = new SelfDynamicParametersConnection();
    $chainLLM->setConnectionTimeout(0);
    $chainLLM->setLLMmodelName($modelId);

    // Register functions for chaining
    $chainLLM->addNewFunction('add', 'Add all numeric values provided in the parameters. Return the total sum.');
    $chainLLM->addNewFunction('multiply', 'Multiply all numeric values provided in the parameters. Return the product.');
    $chainLLM->addNewFunction('numberToLiteral', 'Convert a numeric value to its literal form (e.g., 10 to "ten", 1 to "one", 2 to "two" and so on).');

    echo COLOR_CYAN . "Testing chaining mode..." . COLOR_RESET . PHP_EOL;

    // Set up chaining mode
    $chain = $chainLLM->setChainMode();

    // Execute the entire chain in one go
    $finalResult = $chain->add(5, 3)
                         ->multiply(2)
                         ->numberToLiteral();

    // Display the final result
    echo "Final result after entire chain execution: " . json_encode($finalResult->getLastResponse()) . PHP_EOL;
} catch (Exception $e) {
    echo COLOR_RED . "Chain test failed: " . $e->getMessage() . COLOR_RESET . PHP_EOL;
}
echo str_repeat('-', 50) . PHP_EOL;
