<?php

/**
 * Request - Core HTTP request handler for Viceroy
 *
 * This class provides:
 * - HTTP request formatting and execution
 * - Header and authentication management
 * - Payload construction for API calls
 * - Integration with configuration system
 *
 * Planned Functionality:
 * - GET/POST/PUT/DELETE request handling
 * - Automatic retry logic
 * - Request logging
 * - Response validation
 *
 * Architecture Role:
 * - Works with ConfigObjects for settings
 * - Integrates with Connections for execution
 * - Provides standardized request interface
 *
 * @package Viceroy\Core
 */
namespace Viceroy\Core;

use Viceroy\Configuration\ConfigObjects;

class Request {

  /**
   * @var ConfigObjects $configObjects Configuration objects container
   *
   * Provides access to:
   * - API endpoint configuration
   * - Authentication settings
   * - Request timeout values
   * - Debug mode status
   */
  private ConfigObjects $configObjects;

  /**
   * Constructor
   *
   * Initializes the request handler with configuration settings
   *
   * @param ConfigObjects $configObjects Configuration objects container
   * @throws \InvalidArgumentException If configuration is invalid
   */
  public function __construct(ConfigObjects $configObjects) {
    $this->configObjects = $configObjects;
  }

}
