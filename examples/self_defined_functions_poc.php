<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();

$modelId = 'qwen_QwQ-32B-Q8_0';
$llm->setLLMmodelName($modelId);

$llm->setConnectionTimeout(0);

// Color constants
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[31m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_MAGENTA', "\033[35m");
define('COLOR_CYAN', "\033[36m");

// Define dynamic functions
$llm->addNewFunction('adunare', 'Perform addition on the numeric values provided in each parameter. Return the sum.');
$llm->addNewFunction('countWords', 'Count the words in each parameter. If multiple parameters are provided, return the sum of their word counts.');
$llm->addNewFunction('wordsToArrayAllCaps', 'Convert the text in each parameter into an array of words. Capitalize each word in the resulting arrays.');
$llm->addNewFunction('reverseWordsOrder', 'Reverse the order of words in the provided text parameter.');
$llm->addNewFunction('translate', 'Translate the text in the second parameter into the target language specified by the language code in the first parameter. Do your best to figure out the input and output languages and do not output anything other than the translation.');
$llm->addNewFunction('validEmail', 'Check if the email address provided in the first parameter is valid. Return TRUE for a valid email or FALSE if it is invalid.');
$llm->addNewFunction('leetSpeak', 'Transform the following parameters into l33t speak.');
$llm->addNewFunction('reverseString', 'Reverse the string in the first parameter. Ensure the entire string is reversed, including handling of special characters and spaces.');

function displayResult($title, $input, $result, $description) {
    echo COLOR_CYAN . "────────────────────────────────────────────────────────" . COLOR_RESET . PHP_EOL;
    echo COLOR_CYAN . "  " . str_pad($title, 54) . COLOR_RESET . PHP_EOL;
    echo COLOR_MAGENTA . "  Description: " . $description . COLOR_RESET . PHP_EOL;
    echo COLOR_CYAN . "────────────────────────────────────────────────────────" . COLOR_RESET . PHP_EOL;
    echo COLOR_YELLOW . "  Input:  " . COLOR_RESET . str_pad(json_encode($input), 45) . PHP_EOL;
    echo COLOR_GREEN . "  Result: " . COLOR_RESET . str_pad(json_encode($result), 45) . PHP_EOL;
    echo COLOR_CYAN . "────────────────────────────────────────────────────────" . COLOR_RESET . PHP_EOL . PHP_EOL;
}

displayResult('Reverse String', 'Hello World!', $llm->reverseString('Hello World!'), 'Reverses the input string character by character.');
displayResult('Leet Speak', 'Hello world!', $llm->leetSpeak('Hello world!'), 'Converts text into l33t speak.');
displayResult('Addition', [5, 4], $llm->adunare(5, 4), 'Adds the two numbers provided.');
displayResult('Word Count', 'This is a test', $llm->countWords('This is a test'), 'Counts the number of words in the input text.');
displayResult('Reverse Words', 'This is a test', $llm->reverseWordsOrder('This is a test'), 'Reverses the order of words in the input text.');
displayResult('Translation', ['romanian', 'Hello world'], $llm->translate('romanian', 'Hello world'), 'Translates the given text into the specified target language.');
displayResult('Email Validation', 'test@example.com', $llm->validEmail('test@example.com'), 'Checks if the provided email address is valid.');

// Chaining mode demonstration
$chainLLM = new SelfDynamicParametersConnection();
$chainLLM->setConnectionTimeout(0);
$chainLLM->setLLMmodelName($modelId);

$chainLLM->addNewFunction('add', 'Add all numeric values provided in the parameters. Return the total sum.');
$chainLLM->addNewFunction('multiply', 'Multiply all numeric values provided in the parameters. Return the product.');
$chainLLM->addNewFunction('numberToLiteral', 'Convert a numeric value to its literal form.');

$chain = $chainLLM->setChainMode();
$result = $chain->add(5, 3)->multiply(2)->numberToLiteral();

displayResult('Chaining Result', 'add(5,3)->multiply(2)->numberToLiteral()', $result->getLastResponse(), 'Performs addition, multiplication, and converts to literal form through function chaining.');
