<?php

namespace Viceroy\Connections;

use Viceroy\Connections\Definitions\LLmConnectionAbstractClass;

class OpenAiCompatibleConnection extends LLmConnectionAbstractClass {

  public function __construct(...$params) {
    parent::__construct(...$params);
  }

}