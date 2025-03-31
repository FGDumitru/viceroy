<?php
/**
 * tokenizer.php - Text Tokenization Example
 *
 * This script demonstrates:
 * - Text tokenization into LLM tokens
 * - Token counting
 * - Detokenization back to text
 * - Round-trip validation
 *
 * Note: Works specifically with llama.cpp server instances
 * and may not work with other OpenAI-compatible endpoints
 *
 * Usage:
 * php tokenizer.php
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

echo "This example works ONLY with llama.cpp server instance. It will not work with llama-swap or any other OpenAI compatible endpoint.\n";

// Sample text for tokenization demo
$text = <<<TEXT
Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
TEXT;

echo "\nOriginal text:\n\t$text\n";

// Initialize connection with specific model
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setLLMmodelName('gpt-4o'); // Model must support tokenization


// Tokenize the input text
$tokens = $llmConnection->tokenize($text);

if ($tokens) {
  $tokensCount = count($tokens);

  echo "Text tokenized to: $tokensCount tokens.";
  
  // Attempt to reconstruct original text from tokens
  $detokenizedString = $llmConnection->detokenize($tokens);
  
  if ($detokenizedString) {
    echo "\nTokens detokenized back to:\n\t$detokenizedString\n";
    
    // Verify round-trip integrity
    if ($detokenizedString === $text) {
      echo "\nRound-trip validation successful - original text perfectly reconstructed\n";
    } else {
      echo "\nWarning: Detokenized text differs from original input\n";
    }
  } else {
    echo "\nFailed to detokenize the tokens list.\n";
  }


} else {
  echo "\nFailed to generate the tokens list.\n";
}
