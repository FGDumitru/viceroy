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
     * @var ConfigManager $configManager Configuration manager instance
     */
    private ConfigManager $configManager;

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
     * @var Request $request Request handler instance
     */
    private Request $request;

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
    private $endpointUri = '';

    /**
     * @var Response $response The last response received
     */
    private $response;

    /**
     * @var string $bearedToken Bearer token for authentication
     */
    private $bearedToken = '';
    private int $llmQueryStartTime;
    private int $currentTokensPerSecond;
    private array $queryStats;

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
    public function __construct(?ConfigObjects $config = NULL)
    {
        if (is_null($config)) {
            $this->configuration = new ConfigObjects();

            // If no model is specified by the calling class then we'll try to use the preferred one if it's specified.
            $this->model = $this->configuration->getServerConfigKey('preferredModel') ?? '';

            // No default model name found in config-server-preferredKey, use the class default.
            if (empty($this->model)) {
                $this->model = $this->defaultModelName;
            }

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
     * Sets the configuration object
     *
     * @param ConfigObjects $configuration The configuration to set
     * @return void
     */
    public function setConfiguration(ConfigObjects $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Detokenizes a JSON prompt array into a string
     *
     * @param array $promptJson The prompt in JSON format
     * @return string|bool Detokenized string on success, false on failure
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

    public function queryPostStreamable(?callable $streamCallback = null): Response {
        return $this->queryPost([], $streamCallback);
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
    
        $uri = $this->getServerUri('completions');
    
        $guzzleRequest = [
            'json' => $promptJson,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 0,
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
        $this->llmQueryStartTime = time();
        try {
            if ($streamCallback) {

                $guzzleRequest['json']['stream'] = TRUE;
                $numberOfTokensReceived = 0;
                $response = $this->guzzleObject->post($uri, $guzzleRequest);
                $body = $response->getBody();
                $buffer = ''; $streamedData = '';
                while (!$body->eof()) {
                    $chunk = $body->read(1);

                    //DEBUG
//                    echo $chunk;

                    //echo $chunk;
                    $buffer .= $chunk;

                    if (str_ends_with($buffer, "\n\n")) {
                      $jsonString = substr($buffer, 6);
                        $decoded = json_decode($jsonString, TRUE);

                        if ( is_array($decoded) &&
                            array_key_exists('choices',$decoded) &&
                            !empty($decoded['choices']) &&
                            array_key_exists('finish_reason',$decoded['choices'][0]) &&
                          null === $decoded['choices'][0]['finish_reason']) {

                            $numberOfTokensReceived++;
                            $deltaTimeFloat = time() - $this->llmQueryStartTime;
                            if ($deltaTimeFloat > 0) {
                                $tps = round($numberOfTokensReceived / $deltaTimeFloat);
                                $this->setCurrentTokensPerSecond($tps);
                                $this->currentTokensPerSecond = $tps;
                            } else {
                                $this->setCurrentTokensPerSecond($numberOfTokensReceived);
                            }
                          call_user_func($streamCallback, $decoded['choices'][0]['delta']['content'], $this->getCurrentTokensPerSecond());
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

    private function setQueryStats($data) {
        $this->queryStats = $data;
        unset($this->queryStats['choices']);
    }

    public function getQueryStats() {
        return $this->queryStats;
    }

    public function getQuerytimings() {
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
    public function getResponse() {
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

    public function getLLMmodelName(): string {
        return $this->model;
    }

    /**
     * Sets custom Guzzle options
     *
     * @param array $guzzleCustomOptions Custom Guzzle options
     * @return OpenAICompatibleEndpointConnection Returns self for method chaining
     */
    public function setGuzzleCustomOptions(array $guzzleCustomOptions): OpenAICompatibleEndpointConnection
    {
        $this->guzzleCustomOptions = $guzzleCustomOptions;
        return $this;
    }

    /**
     * Gets custom Guzzle options
     *
     * @return array The custom Guzzle options
     */
    public function getGuzzleCustomOptions(): array
    {
        return $this->guzzleCustomOptions;
    }

    /**
     * Sets Guzzle connection timeout
     *
     * @param int $timeout Timeout in seconds
     * @return OpenAICompatibleEndpointConnection Returns self for method chaining
     */
    public function setGuzzleConnectionTimeout(int $timeout) {
        $currentOptions = $this->getGuzzleCustomOptions();
        $this->setGuzzleCustomOptions(array_merge($currentOptions, ['timeout' => $timeout]));
        return $this;
    }

    /**
     * Sets the API key
     *
     * @param string $apiKey The API key to set
     * @return OpenAICompatibleEndpointConnection Returns self for method chaining
     */
    public function setApiKey(string $apiKey): OpenAICompatibleEndpointConnection
    {
        $this->bearedToken = $apiKey;
        return $this;
    }

    /**
     * Gets available models from the API
     *
     * @return array|bool Array of available models or false on failure
     */
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

    public function getCurrentTokensPerSecond(): int
    {
        return $this->currentTokensPerSecond;
    }

    private function setCurrentTokensPerSecond(int $currentTokensPerSecond): void
    {
        $this->currentTokensPerSecond = $currentTokensPerSecond;
    }
}
