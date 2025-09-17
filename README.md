# Viceroy LLM Library ![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue) ![License](https://img.shields.io/badge/License-MIT-green)

## Table of Contents
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Features](#features)  
- [Image Processing](#image-processing-examples)
- [Advanced Capabilities](#advanced-capabilities)
- [Configuration](#custom-configuration)
- [License](#license)

## Installation
```php
composer require viceroy/llm-library
```

## Basic Usage
```php
use Viceroy\Configuration\ConfigObjects;
use Viceroy\Configuration\ConfigManager;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Initialize configuration
$config = new ConfigObjects('config.json');
$configManager = new ConfigManager($config);

// Create connection  
$connection = new OpenAICompatibleEndpointConnection($config);
$connection->setSystemMessage("You are a helpful assistant.")
    ->setParameter('temperature', 0.7)
    ->setParameter('top_p', 0.9);

// Send query
$response = $connection->query("Explain quantum physics");

// Handle response
if ($response->wasStreamed()) {
    echo "Streamed response received";
} else {
    echo $response->getLlmResponse();
    echo "\nThink content: " . $response->getThinkContent();
}
```

## Features
- Dynamic function chaining
- Custom configuration inheritance  
- Multi-turn conversation support
- Vision capabilities

## Image Processing Examples
```php
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// describe_image.php  
$imagePath = 'examples/images/ocr.png';
$imageData = base64_encode(file_get_contents($imagePath));

$response = $connection->query("Describe this image", [
    'vision' => true,
    'image' => $imageData,
    'max_tokens' => 500
]);

echo $response->getLlmResponse();
```

Available images:
- Document analysis: `/examples/images/image_1.jpg`
- Diagram parsing: `/examples/images/image_2.jpg`
- OCR demonstration: `/examples/images/ocr.png`

## Advanced Capabilities
### Dynamic Function Chaining  
```php
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$connection->enableFunctionCalling()
    ->registerFunction('get_weather', 'Retrieves weather data')
    ->query("What's the weather in Berlin?");
```

## Custom Configuration
```php
use Viceroy\Configuration\ConfigObjects;

$customConfig = [
    'endpoint' => 'https://api.example.com/v1',
    'timeout' => 30,
    'vision_support' => true,
    'model_mappings' => [
        'gpt-4' => 'company-llm-v4'
    ]
];
```

## License  
MIT License  
Copyright (c) 2024 Viceroy Project