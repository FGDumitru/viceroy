<?php

namespace Viceroy\Connections\Traits;

trait LLMDefaultParametersTrait {

  private $llamacpp = [
    "messages" => [],
    "temperature" => 0.7,
    "top_k" => 40,
    "top_p" => 0.95,
    "min_p" => 0.05,
    "n_predict" => -1,
    "stop" => ['<|im_end|>','<|endoftext|>','[/INST]','</s>'],
  ];

  public function readParameters($paramsType = 'llamacpp') {
    $params = $this->$paramsType;

    return $params;
  }

}