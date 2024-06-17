<?php

namespace Viceroy\Connections\Definitions;

use GuzzleHttp\Exception\RequestException;
use Viceroy\Configuration\ConfigManager;
use Viceroy\Core\Request;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Core\Response;
use Viceroy\Core\RolesManager;

abstract class LLmConnectionAbstractClass implements LlmConnectionInterface {

  private ?GuzzleClient $guzzleObject = NULL;

  private ConfigObjects $configuration;

  private ConfigManager $configManager;

  private Request $request;

  private RolesManager $rolesManager;

  function __construct(ConfigObjects $config = NULL) {
    if (is_null($config)) {
      $this->configuration = new ConfigObjects();
    }

    $this->createGuzzleConnection();

    $this->request = new Request($this->configuration);

    $this->rolesManager = new RolesManager();

  }

  public function setConnection(GuzzleClient $guzzleObject) {
    $this->guzzleObject = $guzzleObject;
  }

  public function getConnection() {
    return $this->guzzleObject;
  }

  function createGuzzleConnection() {
    $this->guzzleObject = new GuzzleClient();
  }

  public function getConfiguration(): ConfigObjects {
    return $this->configuration;
  }

  public function setConfiguration(ConfigObjects $configuration) {
    $this->configuration = $configuration;
  }

  public function getRequest(): Request {
    return $this->request;
  }

  public function getRolesManager(): RolesManager {
    return $this->rolesManager;
  }

  public function queryPost(array $promptJson = []): Response {

    if (empty($promptJson)) {
      $promptJson = $this->createGuzzleRequest();
    }

    $uri = $this->getConfiguration()->getServerConfigKey('host');
    $uri .= ':' . $this->getConfiguration()->getServerConfigKey('port');
    $uri .= $this->getConfiguration()->getServerConfigKey('app');

    $guzzleRequest = [
      'json' => $promptJson,
      'headers' => ['Content-Type' => 'application/json'],
    ];

    try {
      $response = $this->guzzleObject->post($uri, $guzzleRequest);

    } catch (RequestException $e) {
      $response = $e->getResponse();
    }

    return new Response($response);
  }

  private function createGuzzleRequest(): array {
    $configManager = new ConfigManager($this->configuration);

    $promptJson = $configManager->getJsonPrompt();

    $promptJson['messages'] = $this->rolesManager->getMessages();

    return $promptJson;
  }

}