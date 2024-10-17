<?php

namespace Viceroy\Connections\Definitions;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Viceroy\Configuration\ConfigManager;
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Core\Request;
use Viceroy\Core\Response;
use Viceroy\Core\RolesManager;

abstract class LLmConnectionAbstractClass implements LlmConnectionInterface {

  private ?GuzzleClient $guzzleObject = NULL;

  private ConfigObjects $configuration;

  private ConfigManager $configManager;
  
  private ?float $queryTime = NULL;

  private string $model = 'localLLM';

  private Request $request;

  private RolesManager $rolesManager;

  public $guzzleParametersFormationCallback;

  public $promptType = 'llamacpp';

  private $endpointUri = '';

  public function getEndpointUri(): string {
    return $this->endpointUri;
  }

  public function setEndpointUri(string $endpointUri): LLmConnectionAbstractClass {
    $this->endpointUri = $endpointUri;
    return $this;
  }

  private $bearedToken = '';

  public function setBearedToken(string $bearedToken): LLmConnectionAbstractClass {
    $this->bearedToken = $bearedToken;
    return $this;
  }

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

  public function tokenize(string $sentence): bool|array {
    $uri = $this->getServerUri('tokenize');

    try {
      $response = $this->guzzleObject->post($uri, [
        'json' => ['content' => $sentence],
        'headers' => ['Content-Type' => 'application/json'],
      ]);
    }
    catch (Exception $e) {
      return FALSE;
    }

    $tokensJsonResponse = $response->getBody()->getContents();

    $tokens = json_decode($tokensJsonResponse)->tokens;

    return $tokens;
  }

  private function getServerUri(string $verb) {

    if (!empty($this->endpointUri)) {
      return $this->endpointUri;
    }

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

  public function health(): bool|\GuzzleHttp\Psr7\Response {
    $uri = $this->getServerUri('health');
    try {
      $response = $this->guzzleObject->get($uri);
    }
    catch (GuzzleException $e) {
      return FALSE;
    }

    return $response;
  }

  public function detokenize(array $promptJson): string|bool {
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
    catch (Exception $e) {
      return FALSE;
    }

    $tokensJsonResponse = $response->getBody()->getContents();

    return json_decode($tokensJsonResponse)->content;
  }

  private function createGuzzleRequest(): array {
    $configManager = new ConfigManager($this->configuration);

    $promptJson = $configManager->getJsonPrompt($this->promptType);

    $promptJson['messages'] = $this->rolesManager->getMessages($this->promptType);
    $promptJson['model'] = $this->model;
    return $promptJson;
  }

  public function queryPost(array $promptJson = []): Response|bool {
    if (empty($promptJson)) {
      $promptJson = $this->createGuzzleRequest();
    }

    $uri = $this->getServerUri('completions');

    $guzzleRequest = [
      'model' => $this->model,
      'json' => $promptJson,
      'headers' => ['Content-Type' => 'application/json'],
    ];

    if (!empty($this->bearedToken)) {
      $guzzleRequest['headers']['Authorization'] = 'Bearer ' . $this->bearedToken;
    }

    if (is_callable($this->guzzleParametersFormationCallback)) {
     [$uri,$guzzleRequest] = call_user_func($this->guzzleParametersFormationCallback, $uri, $guzzleRequest);
    }

//    var_dump($guzzleRequest);
//    die;


    $timer = microtime(TRUE);
    try {
      $response = $this->guzzleObject->post($uri, $guzzleRequest);
//      var_dump($response);
      $this->queryTime = microtime(TRUE) - $timer;
    }
    catch (GuzzleException $e) {
      var_dump($e);
      $this->queryTime = NULL;
      return FALSE;
    }

    return new Response($response);
  }
  
  public function getLastQueryMicrotime() {
    return round($this->queryTime,4);
  }

  public function getParameterValue($param) {

  }

  public function clear() {
    $this->getRolesManager()->clearMessages();
  }

  public function setSystemMessage($systemMessage) {
    $this->getRolesManager()->setSystemMessage($systemMessage);
  }

  public function query($query) {
  $this->getRolesManager()->addUserMessage($query);
  $response = $this->queryPost();
  $this->getRolesManager()->addAssistantMessage($response->getLlmResponse());
  return $response->getLlmResponse();
  }

  public function setEndpointTypeToLlamaCpp(){
    $this->promptType = 'llamacpp';
    $this->setEndpointUri('');
  }

  public function setEndpointTypeToGroqAPI() {
    $this->promptType = 'groqApi';
    $this->setEndpointUri('https://api.groq.com/openai/v1/chat/completions');
  }

  public function setLLMmodelName($modelName) {
    $this->model = $modelName;
  }

}