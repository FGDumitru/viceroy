<?php

namespace Viceroy\Connections\Definitions;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Viceroy\Configuration\ConfigManager;
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Core\PluginInterface;
use Viceroy\Core\PluginManager;
use Viceroy\Core\Response;
use Viceroy\Core\RolesManager;

/**
 * Represents a connection to an OpenAI-compatible API endpoint.
 * Implements OpenAICompatibleEndpointInterface to provide methods for interacting with the API.
 */
class OpenAICompatibleEndpointConnection implements OpenAICompatibleEndpointInterface
{
    /**
     * @var GuzzleClient|null $guzzleObject Guzzle HTTP client instance
     */
    private ?GuzzleClient $guzzleObject = NULL;

    /**
     * @var array $guzzleCustomOptions Custom options for Guzzle HTTP client
     */
    private array $guzzleCustomOptions = [];

    /**
     * @var ConfigObjects $configuration Configuration objects container
     */
    private ConfigObjects $configuration;

    /**
     * @var float|null $queryTime Time taken for last query in seconds
     */
    private ?float $queryTime = NULL;

    /**
     * @var string $model The LLM model name being used
     */
    protected string $model = '';

    /**
     * @var array $parameters LLM parameters (temperature, top_p, etc)
     */
    private array $parameters = [];

    /**
     * @var RolesManager $rolesManager Roles and messages manager
     */
    private RolesManager $rolesManager;

    /**
     * @var callable $guzzleParametersFormationCallback Callback for custom Guzzle parameter formation
     */
    public $guzzleParametersFormationCallback;

    /**
     * @var string $promptType Type of prompt being used (default: 'llamacpp')
     */
    public $promptType = 'llamacpp';

    private $defaultModelName = 'gpt-4o';

    /**
     * @var string $endpointUri Custom endpoint URI override
     */
    private $endpointUri = 'https://api.openai.com';

    /**
     * @var Response $response The last response received
     */
    private $response;

    /**
     * @var string $bearedToken Bearer token for authentication
     */
    private string|null $bearedToken = null;
    private int $currentTokensPerSecond;
    private array $queryStats;
    private string $completionPath = '/v1/chat/completions';
    private string $modelsPath = '/v1/models';
    private PluginManager $pluginManager;
    private array $chainStack = [];
    /**
     * Gets the endpoint URI
     *
     * @return string The current endpoint URI
     */
    public function getEndpointUri(): string
    {
        return $this->endpointUri;
    }

    /**
     * Sets the endpoint URI
     *
     * @param string $endpointUri The URI to set
     * @return OpenAICompatibleEndpointConnection Returns self for method chaining
     */
    public function setEndpointUri(string $endpointUri): OpenAICompatibleEndpointConnection
    {
        $this->endpointUri = $endpointUri;
        return $this;
    }

    /**
     * Sets the Bearer token for API authentication.
     *
     * @param string $bearedToken The Bearer token string
     * @return self Chainable instance
     */
    public function setBearedToken(string|null $bearedToken): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $bearedToken;
        return $this;
    }

    /**
     * Initializes the OpenAICompatibleEndpointConnection with configuration and dependencies.
     *
     * @param ConfigObjects|string|null $config Configuration object (default: new instance)
     */
    public function __construct(ConfigObjects|string|null $config = NULL)
    {
        if (is_null($config)) {
            $this->configuration = new ConfigObjects();
        } elseif (is_string($config)) {
            // The config is a path to a JSON config file.
            if (file_exists($config)) {
                $this->configuration = new ConfigObjects($config);
            } else {
                throw new RuntimeException('Config file doesn\'t exist: ' . $config);
            }
        } elseif ($config instanceof ConfigObjects) {
            $this->configuration = $config;
        } else {
            throw new RuntimeException('Specified config type is not valid. Provided object type: ' . gettype($config));
        }

        // If no model is specified by the calling class then we'll try to use the preferred one if it's specified.
        $this->setLLMmodelName($this->configuration->getConfigKey('preferredModel') ?? $this->defaultModelName);
        $this->setEndpointUri($this->configuration->getConfigKey('apiEndpoint') ?? $this->endpointUri);
        $this->setBearedToken($this->configuration->getConfigKey('bearer'));

        $this->createGuzzleConnection();

        $this->rolesManager = new RolesManager();
        $this->pluginManager = new PluginManager();
    }
    /**
     * Initializes the Guzzle HTTP client for API requests.
     */
    public function createGuzzleConnection()
    {
        $this->guzzleObject = new GuzzleClient();
        return $this;
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
     * Retrieves the roles manager instance.
     *
     * @return RolesManager The roles manager responsible for message management.
     */
    public function getRolesManager(): RolesManager
    {
        return $this->rolesManager;
    }

    public function getModelsPath(): string
    {
        return $this->modelsPath;
    }

    /**
     * Gets the configuration object
     *
     * @return ConfigObjects The configuration object
     */
    public function getConfiguration(): ConfigObjects
    {
        return $this->configuration;
    }

    /**
     * Sets a parameter value
     *
     * @param string $key Parameter name (temperature, top_p, etc)
     * @param mixed $value Parameter value
     * @return OpenAICompatibleEndpointConnection Returns self for method chaining
     */
    public function setParameter(string $key, $value): OpenAICompatibleEndpointConnection
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    public function getParameter(string $key): mixed
    {
        return $this->parameters[$key] ?? ($this->getDefaultParameters()[$key] ?? null);
    }


    /**
     * Gets default parameters for API requests
     *
     * @return array Default parameters array with any custom parameters applied
     */
    public function getDefaultParameters(): array
    {
        $configManager = new ConfigManager($this->configuration);
        $promptJson = $configManager->getJsonPrompt($this->promptType);
        $promptJson['messages'] = $this->rolesManager->getMessages($this->promptType);

        if (!empty($this->model)) {
            $promptJson['model'] = $this->model;
        }

        // Apply any custom parameters
        foreach ($this->parameters as $key => $value) {
            $promptJson[$key] = $value;
        }

        return $promptJson;
    }

    /**
     * Executes a POST query to the API endpoint
     *
     * @param string|array $promptJson The prompt data to send (default: empty array)
     * @param callable|null $streamCallback
     * @return Response The response object
     * @throws Exception
     */
    public function queryPost(string|array $promptJson = [], ?callable $streamCallback = null): Response
    {
        if (empty($promptJson)) {
            $promptJson = $this->getDefaultParameters();
        } elseif (is_string($promptJson)) {
            $defaultParams = $this->getDefaultParameters();
            $promptJson = array_merge($defaultParams, ['messages' => $this->getRolesManager()->addMessage('user', $promptJson)->getMessages()]);
        }

        $uri = $this->getEndpointUri() . $this->getCompletionPath();

        $guzzleRequest = [
            'json' => $promptJson,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 300, // default connection
            'stream' => TRUE
        ];

        $guzzleRequest = array_merge($guzzleRequest, $this->getGuzzleCustomOptions());

        if (!empty($this->bearedToken)) {
            $guzzleRequest['headers']['Authorization'] = 'Bearer ' . $this->bearedToken;
        }

        if (is_callable($this->guzzleParametersFormationCallback)) {
            [$uri, $guzzleRequest] = call_user_func($this->guzzleParametersFormationCallback, $uri, $guzzleRequest);
        }

        $timer = microtime(TRUE);
        $llmQueryStartTime = time();
        try {
            if ($streamCallback) {

                $guzzleRequest['json']['stream'] = TRUE;
                $numberOfTokensReceived = 0;
                $response = $this->guzzleObject->post($uri, $guzzleRequest);
                $body = $response->getBody();
                $buffer = '';
                $streamedData = '';
                while (!$body->eof()) {
                    $chunk = $body->read(1);

                    //echo $chunk;
                    $buffer .= $chunk;

                    if (str_ends_with($buffer, "\n\n")) {
                        $jsonString = substr($buffer, 6);
                        $decoded = json_decode($jsonString, TRUE);

                        if (is_array($decoded) &&
                            array_key_exists('choices', $decoded) &&
                            !empty($decoded['choices']) &&
                            array_key_exists('finish_reason', $decoded['choices'][0]) &&
                            null === $decoded['choices'][0]['finish_reason']) {

                            $numberOfTokensReceived++;
                            $deltaTimeFloat = time() - $llmQueryStartTime;
                            if ($deltaTimeFloat > 0) {
                                $tps = round($numberOfTokensReceived / $deltaTimeFloat);
                                $this->setCurrentTokensPerSecond($tps);
                                $this->currentTokensPerSecond = $tps;
                            } else {
                                $this->setCurrentTokensPerSecond($numberOfTokensReceived);
                            }
                            $streamResult = call_user_func($streamCallback, $decoded['choices'][0]['delta']['content'], $this->getCurrentTokensPerSecond());

                            if (FALSE === $streamResult) {
                                // If we receive a FALSE return value from the callback assume we want to break the streaming.
                                break;
                            }

                            $streamedData .= $decoded['choices'][0]['delta']['content'];
                            $buffer = '';
                        } else {
                            if ('[DONE]' === $buffer) {
                                break;
                            }
                            break;
                        }
                    }

                }
                $this->queryTime = microtime(TRUE) - $timer;
                $this->response = new Response($response);
                $this->response->setWasStreamed();
                $jsonString = substr($buffer, 6);
                $decoded = json_decode($jsonString, TRUE);

                $this->setQueryStats($decoded);

                $this->response->setStreamedContent($streamedData);
            } else {
                $response = $this->guzzleObject->post($uri, $guzzleRequest);
                $this->queryTime = microtime(TRUE) - $timer;
                $this->response = new Response($response);

                $fullResponseString = $this->response->getRawContent();
                $fullResponse = json_decode($fullResponseString, TRUE);
                $this->setQueryStats($fullResponse);

            }
        } catch (GuzzleException $e) {
            $this->queryTime = NULL;
            throw new RuntimeException("Guzzle request failed: " . $e->getMessage());
        }

        return $this->response;
    }

    private function setQueryStats($data)
    {
        $this->queryStats = $data;
        unset($this->queryStats['choices']);
    }

    public function getQueryStats()
    {
        return $this->queryStats;
    }

    public function getQuerytimings()
    {
        return $this->queryStats['timings'] ?? [];
    }

    /**
     * Gets the time taken for the last query
     *
     * @return float Time in seconds, rounded to 4 decimal places
     */
    public function getLastQueryMicrotime(): float
    {
        return round($this->queryTime, 4);
    }

    /**
     * Clears all messages in the roles manager
     */
    public function clear(): static
    {
        $this->getRolesManager()->clearMessages();
        return $this;
    }

    /**
     * Sets the system message
     *
     * @param string $systemMessage The system message to set
     */
    public function setSystemMessage($systemMessage): static
    {
        $this->getRolesManager()->setSystemMessage($systemMessage);
        return $this;
    }

    /**
     * Executes a query and manages conversation flow.
     * It handles roles management by default.
     *
     * @param string $query The query to send
     * @return mixed The LLM response
     * @throws Exception
     */
    public function query(string $query, ?callable $streamCallback = null): string
    {
        $this->getRolesManager()->addUserMessage($query);
        $this->response = $this->queryPost([], $streamCallback);
        $this->getRolesManager()->addAssistantMessage($this->response->getLlmResponse());
        return $this->response->getLlmResponse();
    }

    /**
     * Gets the last response
     *
     * @return Response The last response received
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the think content from the last response
     *
     * @return string The think content
     */
    public function getThinkContent(): string
    {
        return $this->response->getThinkContent();
    }

    /**
     * Sets the LLM model name
     *
     * @param string $modelName The model name to set
     */
    public function setLLMmodelName($modelName): static
    {
        $this->model = $modelName;
        return $this;
    }

    public function getLLMmodelName(): string
    {
        return $this->model;
    }

    /**
     * Sets custom Guzzle options
     *
     * @param array $guzzleCustomOptions Custom Guzzle options
     */
    public function setGuzzleCustomOptions(array $guzzleCustomOptions): OpenAICompatibleEndpointConnection
    {
        $this->guzzleCustomOptions = $guzzleCustomOptions;
        return $this;
    }

    /**
     * Gets custom Guzzle options
     *
     * @return array Custom Guzzle options
     */
    public function getGuzzleCustomOptions(): array
    {
        return $this->guzzleCustomOptions;
    }

    /**
     * Sets the Guzzle connection timeout
     *
     * @param int $timeout Timeout in seconds
     */
    public function setConnectionTimeout(int $timeout): static
    {
        $this->guzzleCustomOptions['timeout'] = $timeout;
        return $this;
    }

    /**
     * Sets the API key
     *
     * @param string $apiKey The API key string
     * @return self instance
     */
    public function setApiKey(string $apiKey): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $apiKey;
        return $this;
    }

    /**
     * Gets available models from the API
     *
     * @return array Available models
     * @throws GuzzleException
     */
    public function getAvailableModels(): array
    {
        $uri = $this->getEndpointUri() . $this->getModelsPath();
        $response = $this->guzzleObject->get($uri, $this->getGuzzleCustomOptions());
        $body = $response->getBody();
        $models = json_decode($body, TRUE);
        return $models['data'];
    }

    /**
     * Gets the current tokens per second
     *
     * @return int Tokens per second
     */
    public function getCurrentTokensPerSecond(): int
    {
        return $this->currentTokensPerSecond;
    }

    /**
     * Sets the current tokens per second
     *
     * @param int $currentTokensPerSecond Tokens per second
     */
    private function setCurrentTokensPerSecond(int $currentTokensPerSecond): void
    {
        $this->currentTokensPerSecond = $currentTokensPerSecond;
    }

    public function setCompletionPath(string $completionPath): OpenAICompatibleEndpointConnection
    {
        $this->completionPath = $completionPath;
        return $this;
    }

    public function getCompletionPath(): string
    {
        return $this->completionPath;
    }

    public function getBearedToken(): string
    {
        return $this->bearedToken;
    }


    public function registerPlugin(PluginInterface $plugin): self {
        $plugin->initialize($this);
        $this->pluginManager->add($plugin);
        return $this;
    }

    public function __call(string $method, array $arguments) {
        foreach ($this->pluginManager->getAll() as $plugin) {
            if ($plugin->canHandle($method)) {
                $result = $plugin->handleMethodCall($method, $arguments);
                return $result;
            }
        }
        throw new \BadMethodCallException("Method $method does not exist");
    }

    public function getLastResponse(): Response|null
    {
        return $this->response;
    }

    public function readBearerTokenFromFile(string $bearerTokenFile): OpenAICompatibleEndpointConnection {
        if (!file_exists($bearerTokenFile)) {
            throw new \InvalidArgumentException("Bearer token file does not exist");
        }

        $this->setBearedToken(file_get_contents($bearerTokenFile));

        return $this;
    }
}
