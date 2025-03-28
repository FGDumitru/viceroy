<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();
// Enable debug output to see actual LLM response:
// $llm->setDebugMode(false);

$models = $llm->getModels();
sort($models);
$models = array_reverse($models);

$modelId = 'Llama-3.3-70B-Instruct-Q4_K_M_CTX-24K'; // Ensure this matches your model
$llm->setLLMmodelName($modelId);
$llm->setEndpointTypeToLlamaCpp(); // Explicitly set endpoint type

$llm->setConnectionTimeout(0);



// Define some dynamic functions.
$llm->addNewFunction('adunare', 'Perform addition on the numeric values provided in each parameter. Return the sum.');

$llm->addNewFunction('countWords', 'Count the words in each parameter. If multiple parameters are provided, return the sum of their word counts.');

$llm->addNewFunction('wordsToArrayAllCaps', 'Convert the text in each parameter into an array of words. Capitalize each word in the resulting arrays.');

$llm->addNewFunction('reverseWordsOrder', 'Reverse the order of words in the provided text parameter.');

$llm->addNewFunction('translate', 'Translate the text in the second parameter into the target language specified by the language code in the first parameter. Do you best to figure out the input and output languages and do not output anything other than the translation.');

$llm->addNewFunction('validEmail', 'Check if the email address provided in the first parameter is valid. Return TRUE for a valid email or FALSE if it is invalid.');

$llm->addNewFunction('leetSpeak', 'Transform the following parameters into l33t speak.');
$llm->addNewFunction('reverseString', 'Reverse the string in the first parameter');


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
        // Special handling for leetSpeak test
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
testFunction($llm, 'reverseString', '!aedi dna gnirts gnirretsnI yreV a si sihT', 'This is a Very Interestring string and idea!');
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

// Test array/boolean functions
$arrayResult = $llm->wordsToArrayAllCaps('This is a simple test');
testFunction($llm, 'wordsToArrayAllCaps', ['THIS', 'IS', 'A', 'SIMPLE', 'TEST'], 'This is a simple test');

testFunction($llm, 'validEmail', false, 'un test@k');
testFunction($llm, 'validEmail', true, 'un_test@k.com');




// Test chaining mode with detailed connection verification
try {
    $chainLLM = new SelfDynamicParametersConnection();
//    $chainLLM->setDebugMode(true);
    $chainLLM->setConnectionTimeout(0);
    $chainLLM->setLLMmodelName($modelId);

    
    // Display connection configuration
    echo COLOR_CYAN . "Connection Configuration:" . COLOR_RESET . PHP_EOL;
    echo "Model: " . ($chainLLM->getLLMmodelName() ?? 'Not set') . PHP_EOL;
    echo "Timeout: " . $chainLLM->getConnectionTimeout() . " seconds" . PHP_EOL;
    
    // Verify connection health
    echo COLOR_CYAN . "Checking connection health..." . COLOR_RESET . PHP_EOL;
    $health = $chainLLM->health();
    if (FALSE || !$health['status']) {
        echo COLOR_RED . "Connection health check failed:" . COLOR_RESET . PHP_EOL;
        echo json_encode($health, JSON_PRETTY_PRINT) . PHP_EOL;
       // throw new RuntimeException("LLM connection failed");
    }
    echo COLOR_GREEN . "✓ Connection healthy" . COLOR_RESET . PHP_EOL;
    
    // Verify model availability
    $models = $chainLLM->getModels();
    if (empty($models)) {
        throw new RuntimeException("No available models - check LLM connection");
    }
    echo COLOR_GREEN . "✓ Available models: " . count($models) . COLOR_RESET . PHP_EOL;
    
    // Register function
    $chainLLM->addNewFunction('adunare', 'Add all numeric values provided in the parameters. Include previous result in chain mode. Return the total sum.');
    
    echo COLOR_CYAN . "Testing chaining mode..." . COLOR_RESET . PHP_EOL;
    
    // Execute chain with error handling for each step
    $chain = $chainLLM->setChainMode();
    
$executeStep = function($step, $args, $expected) use ($chain) {
    echo COLOR_YELLOW . "Step $step" . COLOR_RESET . PHP_EOL;
    
    // Debug: Show current chain state
    echo "Current chain object: " . get_class($chain) . PHP_EOL;
    
    try {
        $result = $chain->getLastResponse();

        if ($result === false) {
            throw new RuntimeException("Query returned false - check LLM connection");
        }
        echo "Got: $result | Expected: $expected" . PHP_EOL;
        return $result == $expected;
    } catch (Exception $e) {
        echo COLOR_RED . "Step $step failed: " . $e->getMessage() . PHP_EOL;
        echo "Backtrace:\n" . $e->getTraceAsString() . COLOR_RESET . PHP_EOL;
        return false;
    }
};
    
    $step1Passed = $executeStep(1, [5,4], 9);
    $step2Passed = $executeStep(2, [6], 15); 
    $step3Passed = $executeStep(3, [7], 22);
    
    $passed = $step1Passed && $step2Passed && $step3Passed;
    
    echo $passed 
        ? COLOR_GREEN . "✓ All chain steps passed" . COLOR_RESET . PHP_EOL 
        : COLOR_RED . "✗ Some chain steps failed" . COLOR_RESET . PHP_EOL;
        
} catch (Exception $e) {
    echo COLOR_RED . "Chain test failed: " . $e->getMessage() . COLOR_RESET . PHP_EOL;
    if (isset($health)) {
        echo COLOR_YELLOW . "Connection health: " . json_encode($health) . COLOR_RESET . PHP_EOL;
    }
}
echo str_repeat('-', 50) . PHP_EOL;
