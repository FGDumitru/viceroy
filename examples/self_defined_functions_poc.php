<?php
declare(strict_types=1);
require_once '../vendor/autoload.php';

use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();

$models = $llm->getModels();
sort($models);
$models = array_reverse($models);

$modelId = 'Qwen_QwQ-32B-Q8_0';

$llm->setConnectionTimeout(0);

$useGrok = FALSE;
if ($useGrok && $authorization = getenv('GrokAPI')) {
  echo "\n\tUsing Grok API\n";
  $llm->setEndpointTypeToGroqAPI();
  $llm->setLLMmodelName('llama-3.2-90b-vision-preview');
  $llm->setBearedToken($authorization);
} else {
  echo "\n\tUsing default API.\n";
  $llm->setLLMmodelName($modelId);
}


// Define some dynamic functions.
$llm->addNewFunction('adunare', 'Perform addition on the numeric values provided in each parameter. Return the sum.');

$llm->addNewFunction('countWords', 'Count the words in each parameter. If multiple parameters are provided, return the sum of their word counts.');

$llm->addNewFunction('wordsToArrayAllCaps', 'Convert the text in each parameter into an array of words. Capitalize each word in the resulting arrays.');

$llm->addNewFunction('reverseWordsOrder', 'Reverse the order of words in the provided text parameter.');

$llm->addNewFunction('translate', 'Translate the text in the second parameter into the target language specified by the language code in the first parameter. Do you best to figure out the input and output languages and do not output anything other than the translation.');

$llm->addNewFunction('validEmail', 'Check if the email address provided in the first parameter is valid. Return TRUE for a valid email or FALSE if it is invalid.');

$llm->addNewFunction('leetSpeak', 'Transform the following parameters into l33t speak.');
$llm->addNewFunction('reverseString', 'Reverse the string in the first parameter');


echo $llm->reverseString('This is a Very Interestring string and idea!');
//die;

echo $llm->leetSpeak('Hello world!') . PHP_EOL; // H3ll0 w0rld!

// Usage examples for dynamic functions (non-chained, direct result).
echo $llm->adunare(5,4) . PHP_EOL; // 9
echo $llm->adunare(5,4,5) . PHP_EOL; // 14

echo $llm->countWords('This is a simple test') . PHP_EOL; // 5
echo $llm->countWords('This is a simple test','some additional words here') . PHP_EOL; // 9
echo $llm->reverseWordsOrder('This is a simple test') . PHP_EOL; // test simple a is This

echo $llm->translate('romanian', 'What else is new?') . PHP_EOL; // Ce mai e nou?
echo $llm->translate('ROmanian', 'La pomme est rouge.') . PHP_EOL; // Marul este rosu
echo $llm->translate('french', 'Mărul este galben.') . PHP_EOL; // La pomme est jaune.
echo $llm->translate('English', 'सेब लाल है।') . PHP_EOL; // The apple is red

var_dump($llm->wordsToArrayAllCaps('This is a simple test'));

var_dump($llm->validEmail('un test@k'));
var_dump($llm->validEmail('un_test@k.com'));




// Chaining mode
$llm->setChainMode()->adunare(5,4)->adunare(6)->adunare(7);
echo PHP_EOL . $llm->getLastResponse() . PHP_EOL;
