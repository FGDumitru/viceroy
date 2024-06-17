<?php

namespace Viceroy\Connections;

use Core\Request;
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Connections\Definitions\LLmConnectionAbstractClass;

class OpenAiCompatibleConnection extends LLmConnectionAbstractClass {

  public function connect() {
    // TODO: Implement connect() method.
  }

  public function __construct(ConfigObjects $config = NULL) {
    parent::__construct($config);

  }


}