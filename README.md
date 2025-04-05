# Viceroy LLM Library

## Table of Contents
1. [Introduction](#introduction)
2. [Core Components](#core-components)
   - [Configuration Management](#configuration-management)
   - [Connection Handling](#connection-handling)
   - [Request/Response Handling](#requestresponse-handling)
   - [Conversation Management](#conversation-management)
3. [Usage Example](#usage-example)
4. [Key Features](#key-features)
5. [Class Relationships](#class-relationships)
6. [Configuration Details](#configuration-details)
7. [Advanced Features](#advanced-features)
8. [Troubleshooting](#troubleshooting)
9. [Example Files](#example-files)

## Introduction
Viceroy provides a PHP framework for interacting with OpenAI-compatible LLM APIs. The library handles configuration management, API communication, conversation state, and response processing.


## Installation

To integrate the Viceroy LLM Library into your project, you can use Composer. Run the following command in your project directory:

```bash
composer require fgdumitru/viceroy
```

## Core Components

### Classes:
1. **Configuration Classes**:
   - **ConfigManager.php**: Manages configuration settings and provides methods for accessing configuration data.
   - **ConfigObjects.php**: Container for configuration objects, used by other classes for configuration management.

2. **Connections Classes**:
   - **OpenAICompatibleEndpointConnection.php**: Represents a connection to an OpenAI-compatible API endpoint, providing methods for interacting with the API, managing configuration, and handling API requests and responses.
   - **SelfDynamicParametersConnection.php**: Handles dynamic function execution against OpenAI-compatible endpoints, supporting chaining and complex JSON response parsing.
   - **TraitableConnectionAbstractClass.php**: Abstract class providing common functionality for connection classes through traits.
   - **LLMDefaultParametersTrait.php**: Trait for setting default parameters for LLM requests.
   - **setSystemMessageTrait.php**: Trait for setting system messages in connections.

3. **Core Classes**:
   - **Request.php**: Core HTTP request handler, responsible for formatting and executing HTTP requests, managing headers and authentication, and constructing payloads for API calls.
   - **Response.php**: Core HTTP response processor, responsible for processing raw HTTP responses, extracting think-tags, handling streamed responses, and providing cleaned, processed content.
   - **RolesManager.php**: Manages conversation roles and messages, enforcing strict role-based message organization and conversation history tracking.

4. **Testing Classes**:
   - **SelfDynamicParametersConnectionTest.php**: Test class for the `SelfDynamicParametersConnection` class, ensuring its functionality is correct through unit tests.

### Key Functionalities and Interconnections:
- **Configuration Management**: The `ConfigManager` and `ConfigObjects` classes manage configuration settings, providing a centralized way to access and modify configuration data across the system.
- **API Interaction**: The `OpenAICompatibleEndpointConnection` and `SelfDynamicParametersConnection` classes handle interactions with OpenAI-compatible APIs, managing API requests, responses, and dynamic function execution.
- **Request and Response Handling**: The `Request` and `Response` classes manage HTTP requests and responses, providing a standardized interface for interacting with external APIs.
- **Conversation Management**: The `RolesManager` class manages conversation roles and messages, ensuring strict role-based message organization and conversation history tracking.
- **Testing**: The `SelfDynamicParametersConnectionTest` class provides unit tests for the `SelfDynamicParametersConnection` class, ensuring its functionality is correct.

## Usage Example
```php
// Create connection. It defaults to OpenAI servers.
$connection = new OpenAICompatibleEndpointConnection();
$connection->setBearedToken('YOUR OPENAI API TOKEN HERE');

$connection->setSystemMessage("You are a helpful assistant.")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9);

// Send query
$response = $connection->query("Explain quantum physics");

echo $response->getLlmResponse();
echo "\nThink content: " . $response->getThinkContent(); // If this was a reasoning model.
}
```


## Usage Example - Custom endpoint.
```php
// Create connection. It defaults to OpenAI servers.
$connection = new OpenAICompatibleEndpointConnection();
$connection->setEndpointUri('http://127.0.0.1:5000');

$connection->setBearedToken('YOUR API TOKEN HERE'); // OPTIONAL

$connection->setSystemMessage("You are a helpful assistant.")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9);

// Send query
$response = $connection->query("Explain quantum physics");

echo $response->getLlmResponse();
echo "\nThink content: " . $response->getThinkContent(); // If this was a reasoning model.
}
```


## Key Features
- **Streaming Support**: Real-time processing of LLM responses
- **Think-Tag Processing**: Extracts and processes `<think>` tags from responses
- **Conversation State**: Maintains context across multiple messages
- **Configuration**: Flexible JSON-based configuration

## Class Relationships
1. ConfigManager uses ConfigObjects for configuration storage
2. OpenAICompatibleEndpointConnection coordinates:
   - ConfigManager for parameters
   - Request for request handling
   - Response for processing outputs
   - RolesManager for conversation state
3. Response processes data from OpenAICompatibleEndpointConnection

## Configuration Details
Configuration is managed via JSON files. The `config.json` file is the primary configuration file. Here is an example of what the `config.json` might look like:

```json
  "apiEndpoint": "https://api.openai.com",
  "preferredModel": "gpt-4o",
  "bearer": "Your API key here"
}
```

## Advanced Features
- **Custom Parameters**: You can set custom parameters for the LLM using the `setParameter()` method.
- **Fluent Interface**: Methods like `setParameter()` return the object itself, allowing for method chaining.
- **Error Handling**: The library includes robust error handling for API requests and response processing.

## Troubleshooting
- **API Errors**: Ensure that your bearer token is correct and that the API URL is accessible.
- **Configuration Issues**: Verify that your `config.json` file is correctly formatted and contains all necessary fields.
- **Debug Mode**: Enable debug mode in the configuration to get more detailed logs and error messages.

## Example Files
The `examples` directory contains several PHP scripts demonstrating different usage scenarios:
- `benchmark_multi.php`: Demonstrates sending multiple queries in a loop.
- `benchmark_simple.php`: Demonstrates a simple query.
- `chat_sample.php`: Demonstrates a basic chat interaction.
- `config.localhost.json`: Example configuration for local development.
- `config.openai.json`: Example configuration for OpenAI API.
- `query_llamacpp.php`: Example using a different LLM provider.
- `roles_llamacpp.php`: Example managing roles with a different LLM provider.
- `self_defined_functions_poc.php`: Example of using self-defined functions.
- `simple_query_llamacpp.php`: Simple query example with a different LLM provider.
- `stream_realtime_example.php`: Example of streaming responses in real-time.

