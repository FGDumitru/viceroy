<?php

/**
 * Request - Handles HTTP request operations
 * 
 * This class provides the foundation for handling HTTP requests
 * and interacts with configuration objects.
 */
namespace Viceroy\Core;

use Viceroy\Configuration\ConfigObjects;

class Request {

  /**
   * @var ConfigObjects $configObjects Configuration objects container
   */
  private ConfigObjects $configObjects;

  /**
   * Constructor
   *
   * @param ConfigObjects $configObjects Configuration objects container
   */
  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

}
