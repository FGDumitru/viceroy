<?php

/**
 * LLMDefaultParametersTrait - Default parameter management for LLM connections
 *
 * This trait provides:
 * - Preconfigured default parameters for LLM interactions
 * - Type-safe parameter access methods
 * - llama.cpp optimized defaults
 *
 * Key Features:
 * - Centralized parameter definitions
 * - Easy parameter retrieval
 * - Designed for composition with ConfigManager
 *
 * Usage Example:
 * class MyConfig {
 *   use LLMDefaultParametersTrait;
 *   // ... uses $this->readParameters()
 * }
 *
 * @package Viceroy\Connections\Traits
 */
namespace Viceroy\Connections\Traits;

trait LLMDefaultParametersTrait {

  /**
   * @var array $llamacpp Default parameters for llama.cpp connections
   *
   * Contains optimized defaults for llama.cpp with:
   * - messages: Array of message objects (empty by default)
   * - temperature: 0.7 (Balanced creativity vs determinism)
   * - top_k: 40 (Consider top 40 tokens)
   * - top_p: 0.95 (Nucleus sampling threshold)
   * - min_p: 0.05 (Minimum probability threshold)
   * - n_predict: -1 (Unlimited prediction length)
   * - stop: Common stop sequences
   * - cache_prompt: true (Optimize repeated prompts)
   *
   * These defaults can be overridden by ConfigManager when needed.
   */
  private $llamacpp = [
    "messages" => [],
    "temperature" => 0.7,
    "stop" => ['<|im_end|>','<|endoftext|>','[/INST]','</s>']
  ];

  /**
   * Retrieves parameters for the specified type
   *
   * Currently only supports 'llamacpp' parameter type.
   * Returns a copy of the parameters to prevent modification of defaults.
   *
   * @param string $paramsType Type of parameters to read (default: 'llamacpp')
   * @return array The requested parameters (copied array)
   * @throws \InvalidArgumentException If unknown parameter type requested
   */
  public function readParameters($paramsType = 'llamacpp') {
    $params = $this->$paramsType;

    return $params;
  }

}
