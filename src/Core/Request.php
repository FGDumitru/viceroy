<?php

namespace Viceroy\Core;

use Viceroy\Configuration\ConfigObjects;

class Request {

  private ConfigObjects $configObjects;

  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

}