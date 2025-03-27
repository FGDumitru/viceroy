<?php

namespace Viceroy\Connections\Traits;

trait LLMDefaultParametersTrait {

  private $llamacpp = [
    "messages" => [],
    "temperature" => 0.7,
    "stop" => ['<|im_end|>','<|endoftext|>','[/INST]','</s>'],
  ];

  private $groqApi = [
    "messages" => [],
    "temperature" => 0.01,
    "seed" => 0,
  ];

  public function readParameters($paramsType = 'llamacpp') {
    $params = $this->$paramsType;

    return $params;
  }

}