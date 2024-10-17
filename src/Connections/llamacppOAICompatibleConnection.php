<?php

namespace Viceroy\Connections;

class llamacppOAICompatibleConnection extends OpenAiCompatibleConnection {
  
  function __construct(...$params) {
    parent::__construct(...$params);
  }

}