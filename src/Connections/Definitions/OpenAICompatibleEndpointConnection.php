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
use RuntimeException;

/**
 * Represents a connection to an OpenAI-compatible API endpoint.
 * Implements OpenAICompatibleEndpointInterface to provide methods for interacting with the API.
 */
class OpenAICompatibleEndpointConnection implements OpenAICompatibleEndpointInterface
{

    private ?GuzzleClient $guzzleObject = NULL;

    private array $guzzleCustomOptions = [];

    private ConfigObjects $configuration;

    private ConfigManager $configManager;

    private ?float $queryTime = NULL;

    protected string $model = '';

    private Request $request;

    private RolesManager $rolesManager;

    public $guzzleParametersFormationCallback;

    public $promptType = 'llamacpp';

    private $endpointUri = '';

    /**
     * @var Response
     */
    private $response;

    public function getEndpointUri(): string
    {
        return $this->endpointUri;
    }

    public function setEndpointUri(string $endpointUri): OpenAICompatibleEndpointConnection
    {
        $this->endpointUri = $endpointUri;
        return $this;
    }

    private $bearedToken = '';

    /**
     * Sets the Bearer token for API authentication.
     *
     * @param string $bearedToken The Bearer token string
     * @return self Chainable instance
     */
    public function setBearedToken(string $bearedToken): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $bearedToken;
        return $this;
    }

    /**
     * Initializes the OpenAICompatibleEndpointConnection with configuration and dependencies.
     *
     * @param ConfigObjects|null $config Configuration object (default: new instance)
     */
    function __construct(ConfigObjects $config = NULL)
    {
        if (is_null($config)) {
            $this->configuration = new ConfigObjects();
        }
    
        $this->createGuzzleConnection();
    
        $this->request = new Request($this->configuration);
    
        $this->rolesManager = new RolesManager();
    }

    /**
     * Initializes the Guzzle HTTP client for API requests.
     */
    public function createGuzzleConnection()
    {
        $this->guzzleObject = new GuzzleClient();
    }

    /**
     * Sets a custom Guzzle HTTP client instance for API requests.
     *
     * @param GuzzleClient $guzzleObject Pre-configured Guzzle client instance
     * @return self Chainable instance
     */
    public function setConnection(GuzzleClient $guzzleObject)
    {
        $this->guzzleObject = $guzzleObject;
        return $this;
    }

    /**
     * Retrieves the configured Guzzle HTTP client instance.
     *
     * @return GuzzleClient The Guzzle HTTP client used for API requests
     */
    public function getConnection(): GuzzleClient
    {
        return $this->guzzleObject;
    }

    /**
     * Retrieves the configured Request instance.
     *
     * @return Request The Request object used for API interaction
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Retrieves the roles manager instance.
     *
     * @return RolesManager The roles manager responsible for message management.
     */
    public function getRolesManager(): RolesManager
    {
        return $this->rolesManager;
    }

    /**
     * Tokenizes a given sentence by sending a POST request to the API's tokenization endpoint.
     *
     * @param string $sentence The sentence to tokenize.
     * @return bool|array Returns an array of tokens if successful, or false on failure.
     */
    public function tokenize(string $sentence): bool|array
    {
        $uri = $this->getServerUri('tokenize');
    
        try {
    
            $guzzleOptions = [
                'json' => ['content' => $sentence],
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 0,
            ];
    
            $guzzleOptions = array_merge($guzzleOptions, $this->getGuzzleCustomOptions());
    
    
            $response = $this->guzzleObject->post($uri, $guzzleOptions);
        } catch (Exception $e) {
            return FALSE;
        }
    
        $tokensJsonResponse = $response->getBody()->getContents();
    
        $tokens = json_decode($tokensJsonResponse)->tokens;
    
        return $tokens;
    }

    /**
     * Constructs the server URI based on configuration and the provided verb.
     *
     * If an explicit endpoint URI is set, it is returned directly.
     * Otherwise, constructs the URI using host, port, and the verb's path from configuration.
     *
     * @param string $verb The API endpoint verb (e.g., 'tokenize', 'completions')
     * @return string The fully constructed API endpoint URI
     */
    private function getServerUri(string $verb): string
    {
        if (!empty($this->endpointUri)) {
            return $this->endpointUri;
        }
    
        $uri = $this->getConfiguration()->getServerConfigKey('host');
        $uri .= ':' . $this->getConfiguration()->getServerConfigKey('port');
        $uri .= $this->getConfiguration()->getServerConfigKey($verb);
    
        return $uri;
    }

    public function getConfiguration(): ConfigObjects
    {
        return $this->configuration;
    }

    public function setConfiguration(ConfigObjects $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return array Returns detailed health status including:
     *               - status: bool Overall health status
     *               - latency: float Response time in ms
     *               - endpoints: array Status of individual endpoints
     *               - error: string|null Error message if any
     */

    public function detokenize(array $promptJson): string|bool
    {
        if (empty($promptJson)) {
            $promptJson = $this->getDefaultParameters();
        }

        $uri = $this->getServerUri('detokenize');

        try {

            $guzzleOptions = [
                'json' => ['tokens' => $promptJson],
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 0,
            ];

            $guzzleOptions = array_merge($guzzleOptions, $this->getGuzzleCustomOptions());

            $response = $this->guzzleObject->post($uri, $guzzleOptions);
        } catch (Exception $e) {
            return FALSE;
        }

        $tokensJsonResponse = $response->getBody()->getContents();

        return json_decode($tokensJsonResponse)->content;
    }

    public function getDefaultParameters(): array
    {
        $configManager = new ConfigManager($this->configuration);

        $promptJson = $configManager->getJsonPrompt($this->promptType);

        $promptJson['messages'] = $this->rolesManager->getMessages($this->promptType);

        if (!empty($this->model)) {
            $promptJson['model'] = $this->model;
        }

        return $promptJson;
    }

public function queryPost(array $promptJson = []): Response
{
    if (empty($promptJson)) {
        $promptJson = $this->getDefaultParameters();
    }

    $uri = $this->getServerUri('completions');

    $guzzleRequest = [
        'json' => $promptJson,
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 0,
    ];

    $guzzleRequest = array_merge($guzzleRequest, $this->getGuzzleCustomOptions());

    if (!empty($this->bearedToken)) {
        $guzzleRequest['headers']['Authorization'] = 'Bearer ' . $this->bearedToken;
    }

    if (is_callable($this->guzzleParametersFormationCallback)) {
        [$uri, $guzzleRequest] = call_user_func($this->guzzleParametersFormationCallback, $uri, $guzzleRequest);
    }

    $timer = microtime(TRUE);
    try {
        $response = $this->guzzleObject->post($uri, $guzzleRequest);
        $this->queryTime = microtime(TRUE) - $timer;
    } catch (GuzzleException $e) {
        $this->queryTime = NULL;
        throw new RuntimeException("Guzzle request failed: " . $e->getMessage());
    }

    $this->response = new Response($response);

    return $this->response;
}

    public function getLastQueryMicrotime()
    {
        return round($this->queryTime, 4);
    }

    public function getParameterValue($param)
    {

    }

    public function clear()
    {
        $this->getRolesManager()->clearMessages();
    }

    public function setSystemMessage($systemMessage)
    {
        $this->getRolesManager()->setSystemMessage($systemMessage);
    }

    public function query($query)
    {
        $this->getRolesManager()->addUserMessage($query);
        $this->response = $this->queryPost();
        $this->getRolesManager()->addAssistantMessage($this->response->getLlmResponse());
        return $this->response->getLlmResponse();
    }

    public function getResponse() {
        return $this->response;
    }

    public function getThinkContent(): string
    {
        return $this->response->getThinkContent();
    }


    public function setLLMmodelName($modelName)
    {
        $this->model = $modelName;
    }

    public function setGuzzleCustomOptions(array $guzzleCustomOptions): OpenAICompatibleEndpointConnection
    {
        $this->guzzleCustomOptions = $guzzleCustomOptions;
        return $this;
    }

    public function getGuzzleCustomOptions(): array
    {
        return $this->guzzleCustomOptions;
    }

    public function setGuzzleConnectionTimeout(int $timeout) {
        $currentOptions = $this->getGuzzleCustomOptions();
        $this->setGuzzleCustomOptions(array_merge($currentOptions, ['timeout' => $timeout]));
        return $this;
    }

    public function setApiKey(string $apiKey): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $apiKey;
        return $this;
    }

    public function getAvailableModels() {
        $uri = $this->getServerUri('models') ?? '/v1/models';

        try {

            $guzzleOptions = [
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 0,
            ];

            $guzzleOptions = array_merge($guzzleOptions, $this->getGuzzleCustomOptions());


            $response = $this->guzzleObject->get($uri, $guzzleOptions);
        } catch (Exception $e) {
            return FALSE;
        }

        $models = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($models['data'])) {
            $data = $models['data'];
            $models = [];
            foreach ($data as $model) {
                $models[] = $model['id'];
            }
        }

        return $models;

    }

}
