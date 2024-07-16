<?php

namespace Viceroy\Connections\Definitions;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Viceroy\Configuration\ConfigManager;
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Core\Request;
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

  function createGuzzleConnection() {
    $this->guzzleObject = new GuzzleClient();
  }

  public function setConnection(GuzzleClient $guzzleObject) {
    $this->guzzleObject = $guzzleObject;
  }

  public function getConnection() {
    return $this->guzzleObject;
  }

  public function getRequest(): Request {
    return $this->request;
  }

  public function getRolesManager(): RolesManager {
    return $this->rolesManager;
  }

  public function tokenize(string $sentence) {
    $uri = $this->getServerUri('tokenize');

    try {
      $response = $this->guzzleObject->post($uri, [
        'json' => ['content' => $sentence],
        'headers' => ['Content-Type' => 'application/json'],
      ]);
    }
    catch (\Exception $e) {
      echo($e->getMessage());
      return FALSE;
    }

    $tokensJsonResponse = $response->getBody()->getContents();

    $tokens = json_decode($tokensJsonResponse)->tokens;

    return $tokens;
  }

  private function getServerUri(string $verb) {
    $uri = $this->getConfiguration()->getServerConfigKey('host');
    $uri .= ':' . $this->getConfiguration()->getServerConfigKey('port');
    $uri .= $this->getConfiguration()->getServerConfigKey($verb);

    return $uri;
  }

  public function getConfiguration(): ConfigObjects {
    return $this->configuration;
  }

  public function setConfiguration(ConfigObjects $configuration) {
    $this->configuration = $configuration;
  }

  public function health() {
    $uri = $this->getServerUri('health');
    try {
      $response = $this->guzzleObject->get($uri);
    }
    catch (\Exception $e) {
      echo($e->getMessage());
      return FALSE;
    }

    var_dump($response->getBody()->getContents());
    return TRUE;
  }

  public function detokenize(array $promptJson) {
    if (empty($promptJson)) {
      $promptJson = $this->createGuzzleRequest();
    }

    $uri = $this->getServerUri('detokenize');

    try {
      $response = $this->guzzleObject->post($uri, [
        'json' => ['tokens' => $promptJson],
        'headers' => ['Content-Type' => 'application/json'],
      ]);
    }
    catch (\Exception $e) {
      echo($e->getMessage());
      return FALSE;
    }

    $tokensJsonResponse = $response->getBody()->getContents();

    $tokens = json_decode($tokensJsonResponse)->content;

    return $tokens;
  }

  private function createGuzzleRequest(): array {
    $configManager = new ConfigManager($this->configuration);

    $promptJson = $configManager->getJsonPrompt();

    $promptJson['messages'] = $this->rolesManager->getMessages();

    return $promptJson;
  }

  public function queryPost(array $promptJson = []): Response {
    if (empty($promptJson)) {
      $promptJson = $this->createGuzzleRequest();
    }

    $uri = $this->getServerUri('completions');

    $guzzleRequest = [
      'json' => $promptJson,
      'headers' => ['Content-Type' => 'application/json'],
    ];

    try {
      $response = $this->guzzleObject->post($uri, $guzzleRequest);
    }
    catch (RequestException $e) {
      $response = $e->getResponse();
    }

    return new Response($response);
  }

}