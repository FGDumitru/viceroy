<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$text = <<<TEXT
Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
TEXT;

echo "\nOriginal text:\n\t$text\n";

$llmConnection = new OpenAICompatibleEndpointConnection();

// Check the endpoint health.
if (!$llmConnection->health()) {
  die('The LLM health status is not valid!');
}

$tokens = $llmConnection->tokenize($text);

if ($tokens) {
  $tokensCount = count($tokens);

  echo "Text tokenized to: $tokensCount tokens.";
  
  $detokenizedString = $llmConnection->detokenize($tokens);
  
  if ($detokenizedString) {
    echo "\nTokens detokenized back to:\n\t$detokenizedString\n";
  } else {
    echo "\nFailed to detokenized the tokens list.\n";
  }

  if ($detokenizedString === $text) {
    echo "\nText detokenized back to correct input:\n\t$text\n";
  }


} else {
  echo "\nFailed to generate the tokens list.\n";
}
