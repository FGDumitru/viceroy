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

    public function setBearedToken(string $bearedToken): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $bearedToken;
        return $this;
    }

    function __construct(ConfigObjects $config = NULL)
    {
        if (is_null($config)) {
            $this->configuration = new ConfigObjects();
        }

        $this->createGuzzleConnection();

        $this->request = new Request($this->configuration);

        $this->rolesManager = new RolesManager();
    }

    function createGuzzleConnection()
    {
        $this->guzzleObject = new GuzzleClient();
    }

    public function setConnection(GuzzleClient $guzzleObject)
    {
        $this->guzzleObject = $guzzleObject;
    }

    public function getConnection()
    {
        return $this->guzzleObject;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getRolesManager(): RolesManager
    {
        return $this->rolesManager;
    }

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

    private function getServerUri(string $verb)
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
    public function health(): array
    {
        $healthCheck = [
            'status' => true,  // Assume healthy by default
            'latency' => 0,
            'endpoints' => [],
            'error' => null
        ];

        // Check base health endpoint
        $startTime = microtime(true);
        try {
            $uri = $this->getServerUri('health');
            $response = $this->guzzleObject->get($uri, ['http_errors' => false]);
            $healthCheck['latency'] = round((microtime(true) - $startTime) * 1000, 2);
            $healthCheck['endpoints']['health'] = [
                'status_code' => $response->getStatusCode(),
                'reachable' => true
            ];
        } catch (GuzzleException $e) {
            $healthCheck['status'] = false;
            $healthCheck['error'] = $e->getMessage();
            $healthCheck['endpoints']['health'] = [
                'reachable' => false,
                'error' => $e->getMessage()
            ];
            return $healthCheck;
        }

        // Check additional critical endpoints
        $endpointsToCheck = ['models', 'completions'];
        foreach ($endpointsToCheck as $endpoint) {
            try {
                $uri = $this->getServerUri($endpoint);
                $response = $this->guzzleObject->get($uri, ['http_errors' => false]);
            $statusCode = $response->getStatusCode();
            $isCritical = in_array($endpoint, ['completions', 'health']);
            $healthCheck['endpoints'][$endpoint] = [
                'status_code' => $statusCode,
                'reachable' => true,
                'healthy' => !$isCritical || $statusCode === 200
            ];
            
            if ($isCritical && $statusCode !== 200) {
                $healthCheck['status'] = false;
                $healthCheck['error'] = "Critical endpoint '$endpoint' returned $statusCode";
            }
            } catch (GuzzleException $e) {
                $healthCheck['endpoints'][$endpoint] = [
                    'reachable' => false,
                    'error' => $e->getMessage()
                ];
                $healthCheck['status'] = false;
                $healthCheck['error'] = $e->getMessage();
            }
        }

        return $healthCheck;
    }

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

    public function queryPost(array $promptJson = []): Response|bool
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

        if (!empty($this->model)) {
            $guzzleRequest['model'] = $this->model;
        }

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
            return FALSE;
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

    public function setEndpointTypeToLlamaCpp()
    {
        $this->promptType = 'llamacpp';
        $this->setEndpointUri('');
    }

    public function setEndpointTypeToGroqAPI()
    {
        $this->promptType = 'groqApi';
        $this->setEndpointUri('https://api.groq.com/openai/v1/chat/completions');
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
