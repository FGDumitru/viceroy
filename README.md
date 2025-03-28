# Viceroy PHP Library for OpenAI-Compatible LLM APIs

## Comprehensive Documentation

### Architecture Overview

The Viceroy library provides a structured interface for working with OpenAI-compatible LLM APIs through several key components:

1. **Connection Layer**:
   - `OpenAICompatibleEndpointInterface`: Core interface defining LLM operations
   - `TraitableConnectionAbstractClass`: Base implementation using proxy pattern
   - Connection implementations for various providers

2. **Core Messaging**:
   - `Request`: Handles configuration and request preparation
   - `Response`: Processes LLM responses with advanced features like:
     - Raw response access
     - Content processing
     - Think tag extraction (`<think>...</think>`)
     - Role identification

3. **Conversation Management**:
   - `RolesManager`: Manages message history with strict ordering
   - Supports system/user/assistant roles

4. **Configuration System**:
   - JSON-based configuration
   - Endpoint management
   - Debug settings

### Installation

```bash
composer require fgdumitru/viceroy
```

### Core Concepts

#### Connection Types

1. **Basic Connection**:
```php
use Viceroy\Connections\Simple\simpleLlamaCppOAICompatibleConnection;

$llm = new simpleLlamaCppOAICompatibleConnection();
$llm->setLLMmodelName('Llama-3.3-70B-Instruct');
$response = $llm->query('Explain quantum computing');
```

2. **Advanced Connection with Dynamic Parameters**:
```php
use Viceroy\Connections\SelfDynamicParametersConnection;

$llm = new SelfDynamicParametersConnection();
$llm->setDebugMode(true);
$llm->setConnectionTimeout(30);
```

#### Configuration

Create `config.json`:
```json
{
  "server": {
    "host": "http://127.0.0.1",
    "port": "8855",
    "endpoints": {
      "completions": "/v1/chat/completions",
      "tokenize": "/tokenize",
      "models": "/v1/models"
    },
    "server_type": "llamacpp"
  }
}
```

### Advanced Features

#### Custom Function Definitions

```php
$llm->addNewFunction('analyzeSentiment', 
    'Analyze sentiment of the provided text. Return POSITIVE, NEUTRAL, or NEGATIVE.');

$sentiment = $llm->analyzeSentiment('I love this library!'); // Returns "POSITIVE"
```

#### Chaining Operations

```php
// Define some dynamic functions for chaining
$llm->addNewFunction('add', 'Add all numeric values provided in the parameters. Return the total sum.');
$llm->addNewFunction('multiply', 'Multiply all numeric values provided in the parameters. Return the product.');
$llm->addNewFunction('reverseString', 'Reverse the string in the first parameter.');
$llm->addNewFunction('numberToLiteral', 'Convert a numeric value to its literal form (e.g., 10 to "ten").');

// Set up chaining mode
$chain = $llm->setChainMode();

// Execute the entire chain in one go
$finalResult = $chain->add(5, 3)         // Returns 8
                 ->multiply(2)           // Returns 16
                 ->numberToLiteral();    // Converts 16 to 'sixteen'

// Display the final result
echo "Final result after entire chain execution: " . $finalResult . PHP_EOL; // 'sixteen'
```

#### Conversation Management

```php
$llm->setSystemMessage('You are a helpful coding assistant.');
$llm->addUserMessage('How do I implement a singleton in PHP?');
$response = $llm->query(); // Uses conversation context

// Add the response as an assistant message and display it as a comment
$llm->addAssistantMessage($response->getLlmResponse());
echo "// Assistant's response: " . $response->getLlmResponse() . PHP_EOL;

// Continue the conversation with multiple role swaps
$llm->addUserMessage('Can you provide an example?');
$response = $llm->query(); // Continues the conversation

// Add the response as an assistant message and display it as a comment
$llm->addAssistantMessage($response->getLlmResponse());
echo "// Assistant's response: " . $response->getLlmResponse() . PHP_EOL;

// Clear conversation history
$llm->clearMessages();
```

**Explanation**:

In the conversation management example, the `RolesManager` class is used to manage the message history with strict ordering and support for system/user/assistant roles. Here's a detailed breakdown of why the code is formatted as such:

1. **Setting the System Message**:
   ```php
   $llm->setSystemMessage('You are a helpful coding assistant.');
   ```
   This sets the system message, which defines the role and behavior of the assistant. The system message is crucial for guiding the assistant's responses and ensuring that it adheres to the desired behavior.

2. **Adding User Messages**:
   ```php
   $llm->addUserMessage('How do I implement a singleton in PHP?');
   ```
   User messages are added to the conversation context. These messages represent the queries or prompts from the user.

3. **Querying the LLM**:
   ```php
   $response = $llm->query(); // Uses conversation context
   ```
   The `query` method sends the conversation context to the LLM and retrieves a response. The response is an instance of the `Response` class, which contains the LLM's response.

4. **Adding Assistant Messages**:
   ```php
   $llm->addAssistantMessage($response->getLlmResponse());
   echo "// Assistant's response: " . $response->getLlmResponse() . PHP_EOL;
   ```
   The assistant's response is added to the conversation context. This is important for maintaining the conversation history and ensuring that the LLM has the full context of the conversation when generating subsequent responses. The response is also printed as a comment for reference.

5. **Continuing the Conversation**:
   ```php
   $llm->addUserMessage('Can you provide an example?');
   $response = $llm->query(); // Continues the conversation
   ```
   The conversation can be continued by adding more user messages and querying the LLM again. The LLM will use the entire conversation history to generate its response.

6. **Clearing Conversation History**:
   ```php
   $llm->clearMessages();
   ```
   The conversation history can be cleared to start a new conversation. This is useful for managing memory and ensuring that the LLM does not retain information from previous conversations.

**Conversation Example**:

Here is a more detailed conversation example to illustrate how the conversation management works:

```php
// Initialize the LLM connection
$llm = new simpleLlamaCppOAICompatibleConnection();
$llm->setLLMmodelName('Llama-3.3-70B-Instruct');

// Set the system message
$llm->setSystemMessage('You are a helpful coding assistant.');

// Add the first user message
$llm->addUserMessage('How do I implement a singleton in PHP?');

// Query the LLM and get the response
$response = $llm->query();

// Add the assistant's response to the conversation context
$llm->addAssistantMessage($response->getLlmResponse());
echo "// Assistant's response: " . $response->getLlmResponse() . PHP_EOL;

// Add the next user message
$llm->addUserMessage('Can you provide an example?');

// Query the LLM again with the updated conversation context
$response = $llm->query();

// Add the assistant's response to the conversation context
$llm->addAssistantMessage($response->getLlmResponse());
echo "// Assistant's response: " . $response->getLlmResponse() . PHP_EOL;

// Clear the conversation history
$llm->clearMessages();
```

In this example, the conversation is managed by adding user messages, querying the LLM, and adding the assistant's responses to the conversation context. This ensures that the LLM has the full context of the conversation when generating responses, leading to more coherent and contextually relevant answers.

### Response Handling

```php
$response = $llm->query('Explain with <think>internal reasoning</think>');

// Get processed response
echo $response->getLlmResponse();

// Access think-tag content
echo $response->getThinkContent();

// Raw response access
$raw = $response->getRawResponse();
```

### Best Practices

1. **Connection Management**:
```php
// Set appropriate timeouts
$llm->setConnectionTimeout(60);
```

2. **Error Handling**:
```php
try {
    $response = $llm->query('Invalid prompt');
} catch (Exception $e) {
    // Handle JSON parsing, timeouts, etc.
    error_log($e->getMessage());
}
```

### Troubleshooting

**Common Issues**:

1. **Connection Timeouts**:
   - Increase timeout: `$llm->setConnectionTimeout(120)`

2. **Invalid Responses**:
   - Enable debug mode: `$llm->setDebugMode(true)`
   - Check raw response: `$response->getRawResponse()`

3. **Function Calling Errors**:
   - Verify function definitions
   - Check parameter types

### Examples

See the `examples/` directory for complete implementations:
- `simple_query_llamacpp.php`: Basic querying
- `roles_llamacpp.php`: Conversation management
- `self_defined_functions_poc.php`: Custom functions
- `config.json`: Configuration reference
