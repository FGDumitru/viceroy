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
 * Implements OpenAICompatibleEndpointInterface to provide methods for
 * interacting with the API.
 */
class OpenAICompatibleEndpointConnection implements OpenAICompatibleEndpointInterface {

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
   * @var callable $guzzleParametersFormationCallback Callback for custom
   *   Guzzle parameter formation
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


  private $reasoningParameters = [
    'reasoning' => [
      'effort' => 'high',
      'max_tokens' => 16384,
      'exclude' => FALSE,
      'enabled' => TRUE,
    ],
  ];

  private $includeReasoning = FALSE;

  /**
   * @var string $bearedToken Bearer token for authentication
   */
  private string|null $bearedToken = NULL;

  private int $currentTokensPerSecond;

  private ?array $queryStats;

  private string $completionPath = '/v1/chat/completions';

  private string $modelsPath = '/v1/models';

  private PluginManager $pluginManager;

  private array $chainStack = [];

  /**
   * @var bool $toolSupportEnabled Whether tool support is enabled
   */
  private bool $toolSupportEnabled = FALSE;

  /**
   * @var string $toolPromptPlacement Where to place tool definitions in prompt
   *   ('system' or 'user')
   */
  private string $toolPromptPlacement = 'system';

  /**
   * @var array $toolDefinitions Tool definitions to be included in prompts
   */
  private array $toolDefinitions = [];

  /**
   * Enable tool support for this connection
   *
   * @return self
   */
  public function enableToolSupport(): self {
    $this->toolSupportEnabled = TRUE;
    return $this;
  }

  /**
   * Disable tool support for this connection
   *
   * @return self
   */
  public function disableToolSupport(): self {
    $this->toolSupportEnabled = FALSE;
    return $this;
  }

  /**
   * Set where to place tool definitions in the prompt
   *
   * @param string $placement Where to place tool definitions ('system' or
   *   'user')
   *
   * @return self
   */
  public function setToolPromptPlacement(string $placement): self {
    $this->toolPromptPlacement = $placement;
    return $this;
  }

  /**
   * Add a tool definition to the list of tools
   *
   * @param array $toolDefinition The tool definition
   *
   * @return self
   */
  public function addToolDefinition(array $toolDefinition): self {
    $this->toolDefinitions[] = $toolDefinition;
    return $this;
  }

  /**
   * Get all tool definitions
   *
   * @return array
   */
  public function getToolDefinitions(): array {
    return $this->toolDefinitions;
  }

  /**
   * Clear all tool definitions
   *
   * @return self
   */
  public function clearToolDefinitions(): self {
    $this->toolDefinitions = [];
    return $this;
  }

  /**
   * Process tool calls from the LLM response
   *
   * @param array $toolCalls The tool calls from the LLM
   *
   * @return array The results from executing the tools
   */
  private function processToolCalls(array $toolCalls): array {
    $results = [];

    // Get the ToolManager if available
    $toolManager = NULL;
    foreach ($this->pluginManager->getAll() as $plugin) {
      if (method_exists($plugin, 'getToolManager')) {
        $toolManager = $plugin->getToolManager();
        break;
      }
    }

    // If no ToolManager is available, try to create one
    if ($toolManager === NULL) {
      try {
        $toolManager = new \Viceroy\ToolManager();
        $toolManager->discoverTools();
      } catch (\Exception $e) {
        // If we can't create ToolManager, we can't execute tools
        return $results;
      }
    }

    foreach ($toolCalls as $toolCall) {
      $toolName = $toolCall['function']['name'] ?? '';
      $arguments = $toolCall['function']['arguments'] ?? [];

      if (!empty($toolName)) {
        try {
          // Execute the tool using the ToolManager
          $toolResult = $toolManager->executeTool($toolName, $arguments);
          $results[] = [
            'tool_call_id' => $toolCall['id'] ?? '',
            'name' => $toolName,
            'content' => json_encode($toolResult),
          ];
        } catch (\Exception $e) {
          $results[] = [
            'tool_call_id' => $toolCall['id'] ?? '',
            'name' => $toolName,
            'content' => json_encode(['error' => $e->getMessage()]),
          ];
        }
      }
    }

    return $results;
  }

  /**
   * Gets the endpoint URI
   *
   * @return string The current endpoint URI
   */
  public function getEndpointUri(): string {
    return $this->endpointUri;
  }

  /**
   * Sets the endpoint URI
   *
   * @param string $endpointUri The URI to set
   *
   * @return OpenAICompatibleEndpointConnection Returns self for method chaining
   */
  public function setEndpointUri(string $endpointUri): OpenAICompatibleEndpointConnection {
    $this->endpointUri = $endpointUri;
    return $this;
  }

  /**
   * Sets the Bearer token for API authentication.
   *
   * @param string $bearedToken The Bearer token string
   *
   * @return self Chainable instance
   */
  public function setBearedToken(string|null $bearedToken): OpenAICompatibleEndpointConnection {
    $this->bearedToken = $bearedToken;
    return $this;
  }


  public function setReasoningEffort(bool|int $maxTokens = 16384, string $effort = 'high') {
    if ($maxTokens === FALSE) {
      $this->includeReasoning = FALSE;
      return;
    }

    $this->includeReasoning = TRUE;
    $this->reasoningParameters['reasoning']['max_tokens'] = $maxTokens;
    $this->reasoningParameters['reasoning']['effort'] = $effort;

  }

  /**
   * Initializes the OpenAICompatibleEndpointConnection with configuration and
   * dependencies.
   *
   * @param ConfigObjects|string|null $config Configuration object (default:
   *   new instance)
   */
  public function __construct(ConfigObjects|string|null $config = NULL) {
    if (is_null($config)) {
      $this->configuration = new ConfigObjects();
    }
    elseif (is_string($config)) {
      // The config is a path to a JSON config file.
      if (file_exists($config)) {
        $this->configuration = new ConfigObjects($config);
      }
      else {
        throw new RuntimeException('Config file doesn\'t exist: ' . $config);
      }
    }
    elseif ($config instanceof ConfigObjects) {
      $this->configuration = $config;
    }
    else {
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
  public function createGuzzleConnection() {
    $this->guzzleObject = new GuzzleClient();
    return $this;
  }

  /**
   * Sets a custom Guzzle HTTP client instance for API requests.
   *
   * @param GuzzleClient $guzzleObject Pre-configured Guzzle client instance
   *
   * @return self Chainable instance
   */
  public function setConnection(GuzzleClient $guzzleObject) {
    $this->guzzleObject = $guzzleObject;
    return $this;
  }

  /**
   * Retrieves the roles manager instance.
   *
   * @return RolesManager The roles manager responsible for message management.
   */
  public function getRolesManager(): RolesManager {
    return $this->rolesManager;
  }

  public function getModelsPath(): string {
    return $this->modelsPath;
  }

  /**
   * Gets the configuration object
   *
   * @return ConfigObjects The configuration object
   */
  public function getConfiguration(): ConfigObjects {
    return $this->configuration;
  }

  /**
   * Sets a parameter value
   *
   * @param string $key Parameter name (temperature, top_p, etc)
   * @param mixed $value Parameter value
   *
   * @return OpenAICompatibleEndpointConnection Returns self for method chaining
   */
  public function setParameter(string $key, $value): OpenAICompatibleEndpointConnection {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getParameter(string $key): mixed {
    return $this->parameters[$key] ?? ($this->getDefaultParameters()[$key] ?? NULL);
  }


  /**
   * Gets default parameters for API requests
   *
   * @return array Default parameters array with any custom parameters applied
   */
  public function getDefaultParameters(): array {
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
   * @param string|array|callable $promptJson The prompt data to send (default:
   *   empty array)
   * @param callable|null $streamCallback
   *
   * @return Response The response object
   * @throws Exception
   */
  public function queryPost(string|array|callable $promptJson = [], ?callable $streamCallback = NULL): Response {
    $runAgain = TRUE;
    $executionCount = 0;
    $maxExecutionCount = 10; // Prevent infinite loops

    while ($runAgain) {
      $toolsData = [];
      $executionCount++;
      $runAgain = FALSE; // Assume we're done unless tool calls appear

      // Handle callable-as-first-arg backward compatibility
      if ($executionCount === 1) {
        if (is_callable($promptJson)) {
          $streamCallback = $promptJson;
          $promptJson = [];
        }

        $promptJson = $this->buildPromptJson($promptJson);
      }
      else {
        // Subsequent loops: use updated conversation from RolesManager
        $promptJson['messages'] = $this->getRolesManager()->getMessages();
      }

      // Send request and process response
      $responseResult = $this->sendRequestAndProcessResponse($promptJson, $streamCallback);

      // Extract content and tool calls
      $streamedData = $responseResult['content'] ?? '';
      $thinkingData = $responseResult['thinking'] ?? '';
      $toolCalls = $responseResult['tool_calls'] ?? [];

      // Final response content
      $finalContent = $thinkingData . $streamedData;
      $this->response->setStreamedContent($finalContent);
      $this->response->setContent($finalContent);

      // Process any detected tool calls
      if (!empty($toolCalls)) {
        $this->handleToolCallsAndAppendToConversation($toolCalls);
        $runAgain = TRUE; // Trigger another loop
      }

      // Prevent infinite execution
      if ($executionCount >= $maxExecutionCount) {
        break;
      }
    }

    return $this->response;
  }

  private function sendRequestAndProcessResponse(array $promptJson, ?callable $streamCallback): array {
    $uri = $this->getEndpointUri() . $this->getCompletionPath();
    $guzzleRequest = [
      'json' => $promptJson,
      'headers' => ['Content-Type' => 'application/json'],
      'timeout' => 300,
      'stream' => TRUE,
    ];

    $guzzleRequest = array_merge($guzzleRequest, $this->getGuzzleCustomOptions());
    if (!empty($this->bearedToken)) {
      $guzzleRequest['headers']['Authorization'] = 'Bearer ' . trim($this->bearedToken);
    }

    if (is_callable($this->guzzleParametersFormationCallback)) {
      [
        $uri,
        $guzzleRequest,
      ] = call_user_func($this->guzzleParametersFormationCallback, $uri, $guzzleRequest);
    }

    $timer = microtime(TRUE);
    $llmQueryStartTime = time();
    $toolCalls = [];
    $streamedContent = '';
    $thinkingContent = '';
    $thinkingMode = NULL;

    try {
      if ($streamCallback) {
        $guzzleRequest['json']['stream'] = TRUE;
        $response = $this->guzzleObject->post($uri, $guzzleRequest);
        $body = $response->getBody();
        $buffer = '';
        $numberOfTokensReceived = 0;

        $this->response = new Response($response);
        $this->response->setWasStreamed();

        while (!$body->eof()) {
          $buffer .= $body->read(4096); // Efficient chunk reading
          $lines = explode("\n", $buffer);
          $buffer = array_pop($lines); // Save incomplete line

          foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ':')) {
              continue;
            }
            if (str_starts_with($line, 'data: ')) {
              $jsonString = substr($line, 6);
              if ($jsonString === '[DONE]') {
                break 2;
              }

              $decoded = json_decode($jsonString, TRUE);
              if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
              }

              $delta = $decoded['choices'][0]['delta'] ?? [];
              $finishReason = $decoded['choices'][0]['finish_reason'] ?? NULL;

              // Handle reasoning (thinking) mode
              if (!empty($delta['reasoning'])) {
                $toStream = $delta['reasoning'];
                if ($thinkingMode === NULL) {
                  $thinkingContent .= "<tool_call>\n$toStream";
                  $thinkingMode = TRUE;
                }
                else {
                  $thinkingContent .= $toStream;
                }
                call_user_func($streamCallback, $toStream, 0);
              }

              // Handle tool calls
              if (!empty($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCall) {
                  $idx = $toolCall['index'];
                  if (!isset($toolCalls[$idx])) {
                    $toolCalls[$idx] = $toolCall;
                    $toolCalls[$idx]['function']['arguments'] = '';
                  }
                  $toolCalls[$idx]['function']['arguments'] .= $toolCall['function']['arguments'] ?? '';
                }
                continue;
              }

              // Handle content
              if (!empty($delta['content'])) {
                $toStream = $delta['content'];
                if ($thinkingMode === TRUE) {
                  $thinkingContent .= $toStream;
                  $thinkingMode = FALSE;
                  $toStream = "\n<tool_call>\n$toStream";
                }
                $streamedContent .= $toStream;
                $numberOfTokensReceived++;
                $deltaTime = max(time() - $llmQueryStartTime, 1);
                $tps = (int) ($numberOfTokensReceived / $deltaTime);
                $this->setCurrentTokensPerSecond($tps);
                $this->currentTokensPerSecond = $tps;

                $streamResult = call_user_func($streamCallback, $toStream, $tps);
                if ($streamResult === FALSE) {
                  break 2;
                }
              }
            }
          }
        }

        $this->queryTime = microtime(TRUE) - $timer;
        if (isset($decoded['error'])) {
          var_dump($decoded);
          die;
        }
        $this->setQueryStats($decoded ?? []);

      }
      else {
        // Non-streaming mode
        unset($guzzleRequest['stream']);
        $response = $this->guzzleObject->post($uri, $guzzleRequest);
        $this->queryTime = microtime(TRUE) - $timer;
        $this->response = new Response($response);
        $raw = $this->response->getRawContent();
        $decoded = json_decode($raw, TRUE);

        if (isset($decoded['error'])) {
          var_dump($decoded);
          die;
        }

        $this->setQueryStats($decoded);

        // Extract content
        $streamedContent = $decoded['choices'][0]['message']['content'] ?? '';

        // Extract tool calls
        if (!empty($decoded['choices'][0]['message']['tool_calls'])) {
          $toolCalls = $decoded['choices'][0]['message']['tool_calls'];
        }
      }

    } catch (GuzzleException $e) {
      $this->queryTime = NULL;
      throw new RuntimeException("Guzzle request failed: " . $e->getMessage());
    }

    return [
      'content' => $streamedContent,
      'thinking' => $thinkingContent,
      'tool_calls' => $toolCalls,
    ];
  }

  private function buildPromptJson(string|array $promptJson): array
  {
    if (empty($promptJson)) {
      $promptJson = $this->getDefaultParameters();
    } elseif (is_string($promptJson)) {
      $defaultParams = $this->getDefaultParameters();
      $promptJson = array_merge($defaultParams, [
        'messages' => $this->getRolesManager()->addMessage('user', $promptJson)->getMessages()
      ]);
    }

    // Include reasoning if enabled
    if ($this->includeReasoning) {
      $promptJson['include_reasoning'] = true;
      $promptJson += $this->reasoningParameters;
    }

    // Add tool definitions if enabled
    if ($this->toolSupportEnabled) {
      $toolDefinitions = !empty($this->toolDefinitions)
        ? $this->toolDefinitions
        : $this->listTools();

      if (!empty($toolDefinitions)) {
        $promptJson['tools'] = $toolDefinitions;

        $toolMessage = "You have access to the following tools:\n\n" . json_encode($toolDefinitions);

        $idx = 'system' === $this->toolPromptPlacement ? 0 : 1;
        if (!isset($promptJson['messages'])) {
          $promptJson['messages'] = [];
        }

        if (!isset($promptJson['messages'][$idx])) {
          $promptJson['messages'][$idx] = ['role' => 'user', 'content' => ''];
        }

        $promptJson['messages'][$idx]['content'] .= "\n" . $toolMessage;
      }
    }

    return $promptJson;
  }

  private function handleToolCallsAndAppendToConversation(array $toolCalls): void {
    $messages = $this->getRolesManager()->getMessages();

    // Add assistant's tool call
    $messages[] = [
      'role' => 'assistant',
      'content' => NULL,
      'tool_calls' => $toolCalls,
    ];

    // Execute tools
    $toolResults = $this->processToolCalls($toolCalls);
    foreach ($toolResults as $result) {
      $messages[] = [
        'role' => 'tool',
        'content' => $result['content'],
        'tool_call_id' => $result['tool_call_id'],
        'name' => $result['name'],
      ];
    }

    $this->getRolesManager()->setMessages($messages);
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
  public function getLastQueryMicrotime(): float {
    return round($this->queryTime, 4);
  }

  /**
   * Clears all messages in the roles manager
   */
  public function clear(): static {
    $this->getRolesManager()->clearMessages();
    return $this;
  }

  /**
   * Sets the system message
   *
   * @param string $systemMessage The system message to set
   */
  public function setSystemMessage($systemMessage): static {
    $this->getRolesManager()->setSystemMessage($systemMessage);
    return $this;
  }

  /**
   * Executes a query and manages conversation flow.
   * It handles roles management by default.
   *
   * @param string $query The query to send
   *
   * @return mixed The LLM response
   * @throws Exception
   */
  public function query(string $query, ?callable $streamCallback = NULL): string {
    $this->getRolesManager()->addUserMessage($query);
    $this->response = $this->queryPost([], $streamCallback);
    $this->getRolesManager()
      ->addAssistantMessage($this->response->getLlmResponse());
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
  public function getThinkContent(): string {
    return $this->response->getThinkContent();
  }

  /**
   * Sets the LLM model name
   *
   * @param string $modelName The model name to set
   */
  public function setLLMmodelName($modelName): static {
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
   */
  public function setGuzzleCustomOptions(array $guzzleCustomOptions): OpenAICompatibleEndpointConnection {
    $this->guzzleCustomOptions = $guzzleCustomOptions;
    return $this;
  }

  /**
   * Gets custom Guzzle options
   *
   * @return array Custom Guzzle options
   */
  public function getGuzzleCustomOptions(): array {
    return $this->guzzleCustomOptions;
  }

  /**
   * Sets the Guzzle connection timeout
   *
   * @param int $timeout Timeout in seconds
   */
  public function setConnectionTimeout(int $timeout): static {
    $this->guzzleCustomOptions['timeout'] = $timeout;
    return $this;
  }

  /**
   * Sets the API key
   *
   * @param string $apiKey The API key string
   *
   * @return self instance
   */
  public function setApiKey(string $apiKey): OpenAICompatibleEndpointConnection {
    $this->bearedToken = $apiKey;
    return $this;
  }

  /**
   * Gets available models from the API
   *
   * @return array Available models
   * @throws GuzzleException
   */
  public function getAvailableModels(): array {
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
  public function getCurrentTokensPerSecond(): int {
    return $this->currentTokensPerSecond;
  }

  /**
   * Sets the current tokens per second
   *
   * @param int $currentTokensPerSecond Tokens per second
   */
  private function setCurrentTokensPerSecond(int $currentTokensPerSecond): void {
    $this->currentTokensPerSecond = $currentTokensPerSecond;
  }

  public function setCompletionPath(string $completionPath): OpenAICompatibleEndpointConnection {
    $this->completionPath = $completionPath;
    return $this;
  }

  public function getCompletionPath(): string {
    return $this->completionPath;
  }

  public function getBearedToken(): string {
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

  public function getLastResponse(): Response|null {
    return $this->response;
  }

  public function readBearerTokenFromFile(string $bearerTokenFile): OpenAICompatibleEndpointConnection {
    if (!file_exists($bearerTokenFile)) {
      throw new \InvalidArgumentException("Bearer token file does not exist");
    }

    $this->setBearedToken(file_get_contents($bearerTokenFile));

    return $this;
  }

  /**
   * Register an MCP client plugin with the given host
   *
   * @param string $host The streamable HTTP URL of the MCP server
   *
   * @return self
   */
  public function registerMCP(string $host): self {
    $mcpClient = new \Viceroy\Plugins\MCPClientPlugin($host);
    $this->registerPlugin($mcpClient);
    return $this;
  }

  /**
   * Check if MCP tool support is available
   *
   * @return bool True if MCP has been successfully registered and tools are
   *   available, false otherwise
   */
  public function hasToolSupport(): bool {
    // Check if MCP client plugin is registered
    $mcpPlugin = $this->pluginManager->get('mcp_client');

    // If MCP plugin is not registered, return false
    if (!$mcpPlugin) {
      return FALSE;
    }

    // Check if tools have been identified and registered
    // We assume tools are registered if the plugin can handle 'tools/list' method
    return $mcpPlugin->canHandle('tools/list');
  }

  /**
   * List all available tools and their definitions
   *
   * @return array Array of tools with their definitions
   */
  public function listTools(): array {
    // Try to get ToolManager from plugins first
    $toolManager = NULL;
    foreach ($this->pluginManager->getAll() as $plugin) {
      if (method_exists($plugin, 'getToolManager')) {
        $toolManager = $plugin->getToolManager();
        break;
      }
    }

    // If no ToolManager from plugins, create a new one
    if ($toolManager === NULL) {
      try {
        $toolManager = new \Viceroy\ToolManager();
        $toolManager->discoverTools();
      } catch (\Exception $e) {
        return [];
      }
    }

    // Get tool definitions from ToolManager
    return $toolManager->getToolDefinitions();
  }

  /**
   * Get all available tools from the ToolManager
   *
   * @return array Array of tool names
   */
  public function getAvailableTools(): array {
    // Try to get ToolManager from plugins first
    $toolManager = NULL;
    foreach ($this->pluginManager->getAll() as $plugin) {
      if (method_exists($plugin, 'getToolManager')) {
        $toolManager = $plugin->getToolManager();
        break;
      }
    }

    // If no ToolManager from plugins, create a new one
    if ($toolManager === NULL) {
      try {
        $toolManager = new \Viceroy\ToolManager();
        $toolManager->discoverTools();
      } catch (\Exception $e) {
        return [];
      }
    }

    // Get tool names from ToolManager
    return $toolManager->getToolNames();
  }

}
