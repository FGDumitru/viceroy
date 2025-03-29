<?php

/**
 * OpenAICompatibleEndpointInterface - Defines the contract for OpenAI-compatible endpoints
 * 
 * This interface specifies the required methods for classes that implement
 * OpenAI API-compatible functionality including tokenization and query operations.
 */
namespace Viceroy\Connections\Definitions;

use Viceroy\Core\Response;

interface OpenAICompatibleEndpointInterface {

  /**
   * Tokenizes a sentence into tokens
   *
   * @param string $sentence The input sentence to tokenize
   * @return array|bool Array of tokens on success, false on failure
   */
  public function tokenize(string $sentence): array|bool;

  /**
   * Detokenizes a JSON prompt array into a string
   *
   * @param array $promptJson The prompt in JSON format
   * @return string|bool Detokenized string on success, false on failure
   */
  public function detokenize(array $promptJson): string|bool;

  /**
   * Executes a POST query to the endpoint
   *
   * @param array $promptJson The prompt data to send (default: empty array)
   * @return Response|bool Response object on success, false on failure
   */
  public function queryPost(array $promptJson = []): Response|bool;

}
