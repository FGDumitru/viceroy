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
use Viceroy\Plugins\MCPClientPlugin;

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


    private $reasoningParameters = ['reasoning' => [
            'effort' => 'high',
            'max_tokens' => 16384,
            'exclude' => false,
            'enabled' => true,
        ]
    ];

    private $includeReasoning = FALSE;

    /**
     * @var string $bearedToken Bearer token for authentication
     */
    private string|null $bearedToken = null;
    private int $currentTokensPerSecond;
    private ?array $queryStats;
    private string $completionPath = '/v1/chat/completions';
    private string $modelsPath = '/v1/models';
    private PluginManager $pluginManager;
    private array $chainStack = [];
    
    /**
     * @var bool $toolSupportEnabled Whether tool support is enabled
     */
    private bool $toolSupportEnabled = false;
    
    /**
     * @var string $toolPromptPlacement Where to place tool definitions in prompt ('system' or 'user')
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
    public function enableToolSupport(): self
    {
        $this->toolSupportEnabled = true;
        return $this;
    }
    
    /**
     * Disable tool support for this connection
     *
     * @return self
     */
    public function disableToolSupport(): self
    {
        $this->toolSupportEnabled = false;
        return $this;
    }
    
    /**
     * Set where to place tool definitions in the prompt
     *
     * @param string $placement Where to place tool definitions ('system' or 'user')
     * @return self
     */
    public function setToolPromptPlacement(string $placement): self
    {
        $this->toolPromptPlacement = $placement;
        return $this;
    }
    
    /**
     * Add a tool definition to the list of tools
     *
     * @param array $toolDefinition The tool definition
     * @return self
     */
    public function addToolDefinition(array $toolDefinition): self
    {
        $this->toolDefinitions[] = $toolDefinition;
        return $this;
    }
    
    /**
     * Get all tool definitions
     *
     * @return array
     */
    public function getToolDefinitions(): array
    {
        return $this->toolDefinitions;
    }
    
    /**
     * Clear all tool definitions
     *
     * @return self
     */
    public function clearToolDefinitions(): self
    {
        $this->toolDefinitions = [];
        return $this;
    }
    
    /**
     * Process tool calls from the LLM response
     *
     * @param array $toolCalls The tool calls from the LLM
     * @return array The results from executing the tools
     */
    private function processToolCalls(array $toolCalls): array
    {
        $results = [];
        
        // Get the ToolManager if available
        $toolManager = null;
        foreach ($this->pluginManager->getAll() as $plugin) {
            if (method_exists($plugin, 'getToolManager')) {
                $toolManager = $plugin->getToolManager();
                break;
            }
        }
        
        // If no ToolManager is available, try to create one
        if ($toolManager === null) {
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
                        'content' => json_encode($toolResult)
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'tool_call_id' => $toolCall['id'] ?? '',
                        'name' => $toolName,
                        'content' => json_encode(['error' => $e->getMessage()])
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
    public function queryPost(string|array|callable $promptJson = [], ?callable $streamCallback = null): Response
    {



        $runAgain = True;

        $executionCount = 0;

        while ($runAgain) {

            // LLM instruction: Reset all variables used in this while block
            $toolsData = [];
            $toolCalls = [];
            $arguments = [];
            $executionCount++;
            $decoded = '';
            $streamedData = null;
            $thinkingData = null;
            $thinkingModel = null;
            $chunk = null;
            $buffer = null;
            $jsonString = null;

            $runAgain = False; // By default, we don't need to repeat a call to LLM unless tool calls have been detected.

            if ($executionCount === 1) {
                if (is_callable($promptJson)) {
                    $streamCallback = $promptJson;
                    $promptJson = [];
                }


                if (empty($promptJson)) {
                    $promptJson = $this->getDefaultParameters();
                } elseif (is_string($promptJson)) {
                    $defaultParams = $this->getDefaultParameters();
                    $promptJson = array_merge($defaultParams, ['messages' => $this->getRolesManager()->addMessage('user', $promptJson)->getMessages()]);
                }


                if ($this->includeReasoning) {
                    $promptJson['include_reasoning'] = true;

                    $promptJson += $this->reasoningParameters;
                }

                // Add tool definitions to the prompt if tool support is enabled
                if ($this->toolSupportEnabled) {
                    if ('system' === $this->toolPromptPlacement) {
                        $idx = 0;
                    } else {
                        // will be put in the first User request
                        $idx = 1;
                    }

                    // If we have explicit tool definitions, use them
                    if (!empty($this->toolDefinitions)) {
                        $toolDefinitionsJson = $this->toolDefinitions;

                        // Add the tool message to the messages array
                        if (!isset($promptJson['messages'])) {
                            $promptJson['messages'] = [];
                        }

                        $promptJson['tools'] = $toolDefinitionsJson;

                    } else {
                        // Try to get tool definitions from ToolManager
                        $toolDefinitions = $this->listTools();
                        if (!empty($toolDefinitions)) {
                            $toolDefinitionsJson = json_encode($toolDefinitions);

                            $toolMessage =  <<<EOP
# Tool Usage Instructions

You are an expert assistant with access to external tools that can provide real-time information or perform actions. Your primary goal is to help users solve their problems efficiently while following these strict protocols:

## Tool Usage Protocol

1. **When to Use Tools**: Only use tools when the user's request requires information that's not in your knowledge base, or when you need to perform actions that only external systems can do.

2. **Tool Selection**: Review the available tools and select the one(s) most appropriate for the user's request. Choose only the tools that can actually help with the specific query.

3. **Tool Execution Format**: When you need to call a tool, respond with exactly one message containing only a tool_call object (no other content) in this format:
```
{
  "tool_calls": [
    {
      "function": {
        "name": "tool_name",
        "arguments": "{\"parameter1\": \"value1\", \"parameter2\": \"value2\"}"
      }
    }
  ]
}
```

4. **No Natural Language in Tool Calls**: Do not add any text content or explanation when making tool calls. The tool_call must be the only content in your response.

5. **Processing Tool Results**: When you receive tool results, analyze them carefully and provide a natural language response that answers the user's original question based on the tool information.

6. **Final Response Protocol**: When you have all the information needed to fully answer the user's request, provide a complete natural language response. This final response should be the only content in your message - no tool calls, no additional formatting.

7. **Error Handling**: If a tool fails or returns unexpected results, acknowledge the limitation and explain what you can do with the available information.

8. **Multiple Tools**: If your task requires multiple tools, call them sequentially or in parallel (if supported) but ensure you process each tool's result appropriately.

## Available Tools
[TOOL_DEFINITIONS_HERE]

## Important Rules

- Never call tools that aren't listed in the available tools
- Never explain your tool selection process in natural language
- Never provide tool call responses with natural language text
- Never ask the user to provide tool parameters
- If you cannot answer a question with available tools, explain the limitation clearly

## Response Format

- Tool calls: JSON object with tool_calls only
- Final responses: Natural language text only
- Do not mix formats in the same response

## Examples

**User:** "What's the weather in Paris?"
**Assistant:**
```
{
  "tool_calls": [
    {
      "function": {
        "name": "get_weather",
        "arguments": "{\"location\": \"Paris, France\"}"
      }
    }
  ]
}
```

**User:** "What's the current time in Tokyo?"
**Assistant:**
```
{
  "tool_calls": [
    {
      "function": {
        "name": "get_current_time",
        "arguments": "{\"timezone\": \"Asia/Tokyo\"}"
      }
    }
  ]
}
```

Remember: Your job is to seamlessly integrate tool usage with natural language responses to provide the best possible assistance to users.
```
EOP;

                            $toolMessage = "You have access to the following tools:\n\n" . $toolDefinitionsJson;

                            // Add the tool message to the messages array
                            if (!isset($promptJson['messages'])) {
                                $promptJson['messages'] = [];
                            }
                            $promptJson['messages'][$idx]['content'] = $promptJson['messages'][$idx]['content'] . "\n" . json_encode($toolMessage);
                        }
                    }
                }

            } else {
                // This is not the first loop, a tool call was detected.
                $promptJson['messages'] = $this->getRolesManager()->getMessages();
            }
            $uri = $this->getEndpointUri() . $this->getCompletionPath();

            $guzzleRequest = [
                'json' => $promptJson,
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 300, // default connection
                'stream' => TRUE
            ];

            // print_r($guzzleRequest);

            $guzzleRequest = array_merge($guzzleRequest, $this->getGuzzleCustomOptions());

            if (!empty($this->bearedToken)) {
                $guzzleRequest['headers']['Authorization'] = 'Bearer ' . trim($this->bearedToken);
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

                    $thinkingMode = NULL;
                    $thinkingData = '';

                    while (!$body->eof()) {
                        $chunk = $body->read(1);

                        //echo $chunk;

                        $buffer .= $chunk;
                        if (str_starts_with($buffer,'<function=')) {
                            $a = 1;
                        }

                        if (str_ends_with($buffer, "\n\n")) {
                            $jsonString = substr($buffer, 6);
                            $decoded = json_decode($jsonString, TRUE);

                            if (isset($decoded['choices'][0]['delta']['role'])
                            && 'assistant' == $decoded['choices'][0]['delta']['role']
                            && null == $decoded['choices'][0]['delta']['content']
                            ) {
                                $buffer = '';
                                continue;
                            }

                            if (isset($decoded['choices'][0]['delta']['tool_calls'])
                               // && null == $decoded['choices'][0]['delta']['role']
                            ) {


                                $toolCalls = $decoded['choices'][0]['delta']['tool_calls'];
                                $idx = $decoded['choices'][0]['delta']['tool_calls'][0]['index'];
                                $arguments = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'];

                                if (isset($toolsData[$idx])) {
                                    $toolsData[$idx]['function']['arguments'] .= $arguments;
                                } else {
                                    $toolsData[] = $toolCalls[0];
                                }

                                $a = 1;
                                $buffer = '';
                                continue;
                            }



                            $finish_reason = $decoded['choice'][0]['finish_reason'] ?? null;
                            $object = $decoded['object'] ?? null;

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

                                // Check for tool calls in the streaming response
                                $finish_reason = $decoded["choices"][0]["finish_reason"];
                                if (
                                    isset($decoded['choices'][0]['delta']['tool_calls'][0]['type']) && $decoded['choices'][0]['delta']['tool_calls'][0]['type'] === 'function'
                                ) {
                                    $toolCalls = $decoded['choices'][0]['delta']['tool_calls'];
                                    $toolsData[] = $toolCalls[0];
                                }

                                if (!isset($decoded['choices'][0]['delta']['content']) ) {
                                    $streamResult = NULL;
                                } else {
                                    $toStream = $decoded['choices'][0]['delta']['content'];

                                    if (empty($toStream) && !empty($decoded['choices'][0]['delta']['reasoning'])) {
                                        $toStream = $decoded['choices'][0]['delta']['reasoning'];

                                        if ($thinkingMode === NULL) {
                                            $toStream = "<tool_call>\n$toStream";
                                            $thinkingData = $toStream;
                                            $thinkingMode = TRUE;
                                        } else {
                                            $thinkingData .= $toStream;
                                        }
                                    }

                                    if ($thinkingMode === TRUE && !empty($decoded['choices'][0]['delta']['content'])
                                    && isset($decoded['choices'][0]['delta']['role'])
                                    ) {
                                        $toStream = "\n<tool_call>\n$toStream";
                                        $thinkingData .= $toStream;
                                        $thinkingMode = FALSE;
                                    }

                                    if ($toStream !== "<tool_call>\n" &&
                                        !str_starts_with($toStream,'<function=')
                                    ) {
                                        $streamResult = call_user_func($streamCallback, $toStream, $this->getCurrentTokensPerSecond());
                                    }


                                }


                                if (FALSE === $streamResult) {
                                    // If we receive a FALSE return value from the callback assume we want to break the streaming.
                                    break;
                                }

                                if (isset($decoded['choices'][0]['delta']['content'])) {
                                    $streamedData .= $decoded['choices'][0]['delta']['content'];
                                }

                                $finish_reason = $decoded['choices'][0]['finish_reason'];
                                if (isset($decoded['choices'][0]['delta']['tool_calls'])) {
                                    $idx = $decoded['choices'][0]['delta']['tool_calls'][0]['index'];
                                    $arguments = $decoded['choices'][0]['delta']['tool_calls'][0]['function']['arguments'];

                                    $toolsData[$idx]['function']['arguments'] .= $arguments;
                                    $a = 1;
                                }
                                $buffer = '';
                            } else {
                                if ('[DONE]' === $buffer) {
                                    break;
                                }
                                if (!str_starts_with($buffer, ':')) {

                                    if (isset($decoded['choices'][0]['delta']['content'])) { // some openrouter models include a last content data line here
                                        $toStream = $decoded['choices'][0]['delta']['content'];
                                        call_user_func($streamCallback, $toStream, $this->getCurrentTokensPerSecond());
                                        $streamedData .= $toStream;
                                    }

                                    break;
                                } else {
                                    $buffer = '';
                                }
                            }
                        }

                    }

                    if (isset($decoded['error'])) {
                        var_dump($decoded);
                        die;
                    }

                    $this->queryTime = microtime(TRUE) - $timer;
                    $this->response = new Response($response);
                    $this->response->setWasStreamed();
                    $jsonString = substr($buffer, 6);
                    $decoded = json_decode($jsonString, TRUE);

                    $this->setQueryStats($decoded);

                    $this->response->setStreamedContent($thinkingData . $streamedData);
                    $this->response->setContent($thinkingData . $streamedData);

                    // Check if the response contains tool calls and process them
                    if (isset($decoded['choices'][0]['delta']['tool_calls'])) {
                        $toolCalls = $decoded['choices'][0]['delta']['tool_calls'];
                        $toolResults = $this->processToolCalls($toolCalls);
                        // Add tool results to the conversation
                        if (!empty($toolResults)) {
                            // In a real implementation, we would send these results back to the LLM
                            // For now, we'll just log them and continue with the original response
                            //error_log("Tool results processed: " . json_encode($toolResults));
                        }
                    }
                } else {
                    $response = $this->guzzleObject->post($uri, $guzzleRequest);
                    $this->queryTime = microtime(TRUE) - $timer;
                    $this->response = new Response($response);

                    $fullResponseString = $this->response->getRawContent();
                    $fullResponse = json_decode($fullResponseString, TRUE);
                    $this->setQueryStats($fullResponse);

                    // Check if the response contains tool calls and process them
                    if (isset($fullResponse['choices'][0]['message']['tool_calls'])) {
                        $toolCalls = $fullResponse['choices'][0]['message']['tool_calls'];
                        $toolResults = $this->processToolCalls($toolCalls);
                        // Add tool results to the conversation
                        if (!empty($toolResults)) {
                            // This is a simplified approach - in a real implementation,
                            // we would need to send the tool results back to the LLM
                            // For now, we'll just return the original response
                        }
                    }
                }
            } catch (GuzzleException $e) {
                $this->queryTime = NULL;
                throw new RuntimeException("Guzzle request failed: " . $e->getMessage());
            }

            if (!empty($toolsData)) {

                $messages = $this->getRolesManager()->getMessages();

                $a = 1;
                $messages[] = [
                  'role' => 'assistant',
                  'content' => null,
                  'tool_calls' => $toolsData,
                ];

                $toolResults = $this->processToolCalls($toolsData);

                foreach ($toolResults as $toolResult) {
                    $messages[] = [
                        'role' => 'tool',
                        'content' => $toolResult['content'],
                        'tool_call_id' => $toolResult['tool_call_id'],
                        'name' => $toolResult['name'],
                    ];
                }

                $this->getRolesManager()->setMessages($messages);

                $runAgain = TRUE;
            }

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

    /**
     * Register an MCP client plugin with the given host
     *
     * @param string $host The streamable HTTP URL of the MCP server
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
     * @return bool True if MCP has been successfully registered and tools are available, false otherwise
     */
    public function hasToolSupport(): bool {
        // Check if MCP client plugin is registered
        $mcpPlugin = $this->pluginManager->get('mcp_client');

        // If MCP plugin is not registered, return false
        if (!$mcpPlugin) {
            return false;
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
        $toolManager = null;
        foreach ($this->pluginManager->getAll() as $plugin) {
            if (method_exists($plugin, 'getToolManager')) {
                $toolManager = $plugin->getToolManager();
                break;
            }
        }
        
        // If no ToolManager from plugins, create a new one
        if ($toolManager === null) {
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
        $toolManager = null;
        foreach ($this->pluginManager->getAll() as $plugin) {
            if (method_exists($plugin, 'getToolManager')) {
                $toolManager = $plugin->getToolManager();
                break;
            }
        }
        
        // If no ToolManager from plugins, create a new one
        if ($toolManager === null) {
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
