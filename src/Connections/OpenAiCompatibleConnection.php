<?php

namespace Viceroy\Connections;

use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\LLmConnectionAbstractClass;

class OpenAiCompatibleConnection extends LLmConnectionAbstractClass {

  public function __construct(ConfigObjects $config = NULL) {
    parent::__construct($config);
  }

}