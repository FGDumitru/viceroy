<?php

namespace Viceroy\Connections;

use Viceroy\Configuration\ConfigObjects;

class llamacppOAICompatibleConnection extends OpenAiCompatibleConnection {
  
  function __construct(ConfigObjects $config = NULL) {
    parent::__construct($config);
  }

}