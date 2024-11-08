<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();


// Define some dynamic functions.
$llm->addNewFunction('adunare','Perform addition on the following parameters.');
$llm->addNewFunction('countWords','Return the words count from the following parameter. If more than one parameter is present, return the sum of their word count.');
$llm->addNewFunction('wordsToArrayAllCaps','Return an array of all the words. Capitalize them all first.');
$llm->addNewFunction('reverseWordsOrder','Reverse words order.');
$llm->addNewFunction('translate','You are an universal language translator. Translate the second parameter into the language pointed by the first parameter.');
$llm->addNewFunction('validEmail','Return TRUE if the email address contained in the first parameter is valid or FALSE if the email address contained in the first parameter is not valid.');



// Usage examples for dynamic functions (non-chained, direct result).
echo $llm->adunare(5,4) . PHP_EOL;
echo $llm->adunare(5,4,5) . PHP_EOL;

echo $llm->countWords('This is a simple test') . PHP_EOL;
echo $llm->countWords('This is a simple test','some additional words here') . PHP_EOL;
echo $llm->reverseWordsOrder('This is a simple test') . PHP_EOL;

echo $llm->translate('romanian', 'What else is new?') . PHP_EOL;
echo $llm->translate('RO', 'La pomme est rouge.') . PHP_EOL;
echo $llm->translate('fr', 'Mărul este galben.') . PHP_EOL;
echo $llm->translate('English language', 'सेब लाल है।') . PHP_EOL;

print_r($llm->wordsToArrayAllCaps('This is a simple test')) . PHP_EOL;

var_dump($llm->validEmail('un test@k'));
var_dump($llm->validEmail('un_test@k.com'));




// Chaining mode
$llm->setChainMode()->adunare(5,4)->adunare(6)->adunare(7);
echo PHP_EOL . $llm->getLastResponse() . PHP_EOL;
