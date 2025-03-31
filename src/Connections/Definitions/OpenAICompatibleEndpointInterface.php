<?php

/**
 * OpenAICompatibleEndpointInterface - Core contract for OpenAI API compatibility
 *
 * This interface defines the standard operations required for any service
 * implementing OpenAI API compatibility. Implementations must provide:
 * - Tokenization/detokenization capabilities
 * - Standardized query execution
 * - Response handling via Core\Response
 *
 * Architecture Role:
 * - Serves as the primary abstraction layer for OpenAI API operations
 * - Enables interchangeable endpoint implementations
 * - Provides consistent interface for connection classes
 *
 * @package Viceroy\Connections\Definitions
 */
namespace Viceroy\Connections\Definitions;

use Viceroy\Core\Response;

interface OpenAICompatibleEndpointInterface {

  /**
   * Tokenizes input text according to OpenAI's tokenization rules
   *
   * This method must:
   * - Split text into semantic tokens
   * - Handle special characters and whitespace properly
   * - Return false only on unrecoverable errors
   *
   * @param string $sentence The input text to tokenize
   * @return array|bool Array of tokens on success, false on critical failure
   * @throws \InvalidArgumentException If input is not a string
   */
  public function tokenize(string $sentence): array|bool;

  /**
   * Reconstructs text from tokenized JSON prompt
   *
   * This method must:
   * - Handle all token types produced by tokenize()
   * - Preserve original formatting when possible
   * - Return false only on unrecoverable errors
   *
   * @param array $promptJson Tokenized prompt in JSON format
   * @return string|bool Reconstructed string on success, false on critical failure
   * @throws \InvalidArgumentException If input is malformed
   */
  public function detokenize(array $promptJson): string|bool;

  /**
   * Executes a standardized POST query to the API endpoint
   *
   * This method must:
   * - Format the request according to OpenAI API specs
   * - Handle all required headers and authentication
   * - Return a standardized Response object
   * - Return false only on unrecoverable errors
   *
   * @param array $promptJson The prompt data to send (default: empty array)
   * @return Response|bool Response object on success, false on critical failure
   * @throws \RuntimeException For network or API errors
   */
  public function queryPost(array $promptJson = []): Response|bool;

}
