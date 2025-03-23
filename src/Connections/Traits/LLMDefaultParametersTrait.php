<?php

namespace Viceroy\Connections\Traits;

trait LLMDefaultParametersTrait {

  private $llamacpp = [
    "messages" => [],
    "temperature" => 0.7,
    "top_k" => 40,
    "top_p" => 0.95,
    "min_p" => 0.05,
//    "n_predict" => 8000,
//    "n_keep" => 0,
//    "stream" => FALSE,
    "stop" => ['<|im_end|>'],
//    "tfs_z" => 1.0,
//    "typical_p" => 1.0,
//    "repeat_penalty" => 1.1,
//    "repeat_last_n" => 0,
//    "penalize_nl" => TRUE,
//    "presence_penalty" => 0.0,
//    "frequency_penalty" => 0.0,
//    "penalty_prompt" => NULL,
//    "mirostat" => 0,
//    "mirostat_tau" => 5.0,
//    "mirostat_eta" => 0.1,
//    "seed" => 0,
//    "ignore_eos" => FALSE,
//    "logit_bias" => [],
//    "n_probs" => 0,
//    "min_keep" => 0,
//    "image_data" => [],
//    "id_slot" => -1,
//    "cache_prompt" => TRUE,
//    "samplers" => [
//      "top_k",
//      "tfs_z",
//      "typical_p",
//      "top_p",
//      "min_p",
//      "temperature",
//    ],
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