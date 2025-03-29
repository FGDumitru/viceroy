<?php

/**
 * LLMDefaultParametersTrait - Provides default parameters for LLM connections
 * 
 * This trait contains default configuration parameters for LLM connections,
 * specifically tailored for llama.cpp compatibility.
 */
namespace Viceroy\Connections\Traits;

trait LLMDefaultParametersTrait {

  /**
   * @var array $llamacpp Default parameters for llama.cpp connections
   * Contains:
   * - messages: Array of messages
   * - temperature: Sampling temperature
   * - top_k: Top-k sampling
   * - top_p: Top-p sampling
   * - min_p: Minimum probability
   * - n_predict: Number of tokens to predict
   * - stop: Array of stop sequences
   */
  private $llamacpp = [
    "messages" => [],
    "temperature" => 0.7,
    "top_k" => 40,
    "top_p" => 0.95,
    "min_p" => 0.05,
    "n_predict" => -1,
    "stop" => ['<|im_end|>','<|endoftext|>','[/INST]','</s>'],
  ];

  /**
   * Reads parameters for specified type
   *
   * @param string $paramsType Type of parameters to read (default: 'llamacpp')
   * @return array The requested parameters
   */
  public function readParameters($paramsType = 'llamacpp') {
    $params = $this->$paramsType;

    return $params;
  }

}
