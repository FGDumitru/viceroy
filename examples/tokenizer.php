<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\llamacppOAICompatibleConnection;

$text = <<<TEXT
Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
TEXT;

$llmConnection = new llamacppOAICompatibleConnection();

// Check the endpoint health.
if (!$llmConnection->health()) {
  die('The LLM health status is not valid!');
}

$tokens = $llmConnection->tokenize($text);

if ($tokens) {
  $tokensCount = count($tokens);

  echo "Text tokenized to: $tokensCount tokens.";
} else {
  echo "\nFailed to generate the tokens lost.\n";
}