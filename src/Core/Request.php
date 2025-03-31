<?php

/**
 * Core HTTP request handler for Viceroy
 *
 * Provides:
 * - HTTP request formatting and execution
 * - Header and authentication management
 * - Payload construction for API calls
 * - Integration with configuration system
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

class Request
{
    /**
     * @var ConfigObjects $configObjects Configuration objects container
     */
    private ConfigObjects $configObjects;

    /**
     * Initializes the request handler with configuration settings
     *
     * @param ConfigObjects $configObjects Configuration objects container
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function __construct(ConfigObjects $configObjects)
    {
        $this->configObjects = $configObjects;
    }
}
