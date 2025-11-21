# SearchTool Documentation

## Overview

The `SearchTool` is a Viceroy tool that provides basic web search functionality using SearXNG search engine. It offers a simple interface for performing web searches and returning structured results with titles, content snippets, URLs, and metadata.

## Features

- **SearXNG Integration**: Uses SearXNG metasearch engine for comprehensive results
- **Configurable Endpoint**: Supports custom SearXNG instances
- **Structured Results**: Returns standardized JSON response with rich metadata
- **Error Handling**: Comprehensive error handling for network and parsing issues
- **Debug Mode**: Optional debug logging for troubleshooting
- **Flexible Limits**: Configurable result limits with sensible defaults
- **Response Format Detection**: Handles both JSON and non-JSON search responses

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| query | string | Yes | The search query string to look up on the web |
| limit | integer | No | Maximum number of results to return. Default: 10 |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "JSON string containing search results"
    }
  ],
  "isError": boolean
}
```

The text content contains a JSON object with:

```json
{
  "query": "search_query_string",
  "total": number_of_results,
  "results": [
    {
      "title": "result_title",
      "content": "content_snippet",
      "url": "result_url",
      "score": relevance_score,
      "engine": "source_engine",
      "category": "result_category"
    }
  ]
}
```

**Successful Response Example:**
```json
{
  "query": "php frameworks",
  "total": 10,
  "results": [
    {
      "title": "Popular PHP Frameworks in 2024",
      "content": "A comprehensive overview of the most popular PHP frameworks including Laravel, Symfony, and others...",
      "url": "https://example.com/php-frameworks",
      "score": 0.95,
      "engine": "google",
      "category": "general"
    }
  ]
}
```

**Error Response Example:**
```json
{
  "content": [
    {
      "type": "text",
      "text": "SearchNX connection error: Connection refused"
    }
  ],
  "isError": true
}
```

## Usage Examples

### Basic Search

```php
use Viceroy\Tools\SearchTool;

$tool = new SearchTool();
$result = $tool->execute([
    'query' => 'machine learning tutorials'
], null);

if (!$result['isError']) {
    $data = json_decode($result['content'][0]['text'], true);
    echo "Found {$data['total']} results for: {$data['query']}\n\n";
    
    foreach ($data['results'] as $index => $item) {
        echo ($index + 1) . ". {$item['title']}\n";
        echo "   URL: {$item['url']}\n";
        echo "   Content: " . substr($item['content'], 0, 150) . "...\n";
        echo "   Engine: {$item['engine']} | Score: {$item['score']}\n\n";
    }
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Custom Result Limit

```php
$tool = new SearchTool();
$result = $tool->execute([
    'query' => 'artificial intelligence trends',
    'limit' => 5
], null);

$data = json_decode($result['content'][0]['text'], true);

echo "Top {$data['total']} results:\n";
foreach ($data['results'] as $result) {
    echo "- {$result['title']}\n";
    echo "  {$result['url']}\n\n";
}
```

### Custom Search Endpoint

```php
$tool = new SearchTool(
    $searchEndpoint = 'https://my-searxng-instance.com',  // Custom endpoint
    $debugMode = true                                      // Enable debug logging
);

$result = $tool->execute([
    'query' => 'quantum computing',
    'limit' => 3
], null);
```

### Debug Mode Usage

```php
// Enable debug mode to see detailed request information
$tool = new SearchTool(null, true);

$result = $tool->execute([
    'query' => 'renewable energy',
    'limit' => 2
], null);

// Check debug output in error logs for:
// - Search endpoint used
// - Query parameters
// - HTTP response status
// - Raw response body
// - JSON parsing attempts
```

### Error Handling

```php
$tool = new SearchTool();

// Test with empty query
$result = $tool->execute([], null);
if ($result['isError']) {
    echo "Expected error for missing query: " . $result['content'][0]['text'];
}

// Test with invalid endpoint
$tool = new SearchTool('http://invalid-endpoint.com');
$result = $tool->execute(['query' => 'test'], null);
if ($result['isError']) {
    echo "Expected connection error: " . $result['content'][0]['text'];
}
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new SearchTool());

// Execute tool
$result = $toolManager->executeTool('search', [
    'query' => 'blockchain technology',
    'limit' => 8
], $configuration);

if (!$result['isError']) {
    $data = json_decode($result['content'][0]['text'], true);
    // Process search results
}
```

### Using with MCP (Model Context Protocol)

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'search',
    'arguments' => [
        'query' => 'climate change solutions',
        'limit' => 5
    ]
]);
```

### Integration in LLM Workflows

The tool is particularly useful for LLM applications that need current web information:

```php
// Example LLM system prompt that uses the tool
$systemPrompt = "You are a helpful assistant that can search the web for current information. 
When users ask about recent events, current trends, or need up-to-date information, use the search tool.";

$connection->setSystemMessage($systemPrompt);
$connection->addToolDefinition(new SearchTool());

// LLM can now search for current information
$response = $connection->query("What are the latest developments in quantum computing?");
```

## Technical Implementation

### Dependencies

- **SearXNG Search Engine**: Requires access to a SearXNG instance
- **Guzzle HTTP Client**: For HTTP requests to search endpoint
- **PHP JSON Extension**: For parsing search responses

### Search Endpoint Configuration

The tool supports multiple ways to configure the SearXNG endpoint:

1. **Constructor Parameter**:
   ```php
   new SearchTool($searchEndpoint, $debugMode)
   ```

2. **Default Values**:
   - Default endpoint: `http://192.168.0.121:8080`
   - Fallback endpoint: `http://127.0.0.1:8080`

3. **Configuration Override**:
   ```php
   // Can be overridden via configuration
   $searchEndpoint = $configuration->getConfigKey('search_endpoint') ?: $this->searchEndpoint;
   ```

### HTTP Request Configuration

The tool uses the following HTTP request settings:

```php
$client = new Client([
    'base_uri' => $searchEndpoint,
    'timeout' => 30.0,           // 30 second timeout
    'http_errors' => false          // Manual error handling
]);

// Search parameters
$queryParams = [
    'q' => $query,
    'limit' => $limit,
    'format' => 'json',
    'categories' => 'general',
    'language' => 'en'
];
```

### Response Processing

The tool implements flexible response parsing:

1. **JSON Detection**: Checks if response starts with `{` or `[`
2. **JSON Parsing**: Attempts to parse as JSON if detected
3. **Fallback Handling**: Creates structured response for non-JSON content
4. **Error Handling**: Provides detailed error messages for failures

## Performance Considerations

### Timeout Settings

- **Connection Timeout**: 30 seconds for search requests
- **Request Timeout**: 30 seconds total request time
- **Recommended Limits**: 5-10 results for optimal performance

### Caching Strategy

The tool doesn't implement built-in caching, but can benefit from:

```php
// External caching example
class CachedSearchTool extends SearchTool {
    private $cache = [];
    private $cacheTtl = 300; // 5 minutes
    
    public function execute(array $arguments, $configuration): array {
        $cacheKey = md5($arguments['query'] . ($arguments['limit'] ?? 10));
        
        if (isset($this->cache[$cacheKey]) && 
            (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTtl) {
            return $this->cache[$cacheKey]['result'];
        }
        
        $result = parent::execute($arguments, $configuration);
        $this->cache[$cacheKey] = [
            'result' => $result,
            'timestamp' => time()
        ];
        
        return $result;
    }
}
```

### Rate Limiting

For high-volume usage, implement rate limiting:

```php
class RateLimitedSearchTool extends SearchTool {
    private $lastRequestTime = 0;
    private $minInterval = 1; // 1 second between requests
    
    public function execute(array $arguments, $configuration): array {
        $now = time();
        $timeSinceLast = $now - $this->lastRequestTime;
        
        if ($timeSinceLast < $this->minInterval) {
            sleep($this->minInterval - $timeSinceLast);
        }
        
        $this->lastRequestTime = time();
        return parent::execute($arguments, $configuration);
    }
}
```

## Troubleshooting

### Common Issues

1. **Search Endpoint Unavailable**:
   ```
   Error: SearchNX connection error: Connection refused
   ```
   **Solution**: Ensure SearXNG is running and accessible at the configured endpoint

2. **Invalid JSON Response**:
   ```
   Error: Invalid JSON from SearchNX: syntax error
   ```
   **Solution**: Check SearXNG configuration and response format

3. **HTTP Error Codes**:
   ```
   Error: SearchNX returned status 500: Internal Server Error
   ```
   **Solution**: Check SearXNG server status and logs

4. **Empty Results**:
   ```
   {"query": "test", "total": 0, "results": []}
   ```
   **Solution**: Try different query terms or check search engine configuration

### Debug Information

Enable debug mode for detailed troubleshooting:

```php
$tool = new SearchTool(null, true);
```

Debug output includes:
- Search endpoint used
- Query parameters sent
- HTTP response status code
- Raw response body
- JSON parsing attempts and errors
- Connection error details

### Network Diagnostics

```php
function diagnoseSearchConnection($endpoint) {
    $client = new \GuzzleHttp\Client([
        'timeout' => 5.0,
        'http_errors' => false
    ]);
    
    try {
        $response = $client->get($endpoint . '/search', [
            'query' => ['q' => 'test', 'format' => 'json']
        ]);
        
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Content-Type: " . $response->getHeaderLine('content-type') . "\n";
        echo "Response length: " . strlen($response->getBody()) . " bytes\n";
        
    } catch (\Exception $e) {
        echo "Connection error: " . $e->getMessage() . "\n";
    }
}

// Usage
diagnoseSearchConnection('http://127.0.0.1:8080');
```

## Best Practices

1. **Query Construction**:
   - Use specific, well-formed queries for better results
   - Include relevant keywords and context
   - Avoid overly broad queries that may return irrelevant results

2. **Result Limits**:
   - Use appropriate limits for your use case (5-10 is typical)
   - Consider performance impact of large result sets
   - Implement pagination if needed for large result sets

3. **Error Handling**:
   - Always check the `isError` flag in responses
   - Implement retry logic for network-related failures
   - Provide fallback options when search fails

4. **Endpoint Management**:
   - Use reliable SearXNG instances
   - Configure backup endpoints for redundancy
   - Monitor endpoint health and performance

5. **Integration Patterns**:
   ```php
   // Best practice for search integration
   function performRobustSearch($query, $maxRetries = 3) {
       $tool = new SearchTool();
       
       for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
           $result = $tool->execute(['query' => $query, 'limit' => 5], null);
           
           if (!$result['isError']) {
               return $result;
           }
           
           if ($attempt < $maxRetries) {
               sleep(2); // Wait 2 seconds before retry
           }
       }
       
       return ['isError' => true, 'content' => [['text' => 'All search attempts failed']]];
   }
   ```

## Advanced Usage

### Custom Search Wrapper

```php
class EnhancedSearchTool extends SearchTool {
    private $cache = [];
    private $maxCacheAge = 300; // 5 minutes
    
    public function execute(array $arguments, $configuration): array {
        // Add caching
        $cacheKey = md5(json_encode($arguments));
        
        if (isset($this->cache[$cacheKey]) && 
            (time() - $this->cache[$cacheKey]['timestamp']) < $this->maxCacheAge) {
            return $this->cache[$cacheKey]['result'];
        }
        
        // Add query preprocessing
        $processedArgs = $this->preprocessQuery($arguments);
        
        // Execute parent method
        $result = parent::execute($processedArgs, $configuration);
        
        // Post-process results
        if (!$result['isError']) {
            $result = $this->postprocessResults($result);
        }
        
        // Cache result
        $this->cache[$cacheKey] = [
            'result' => $result,
            'timestamp' => time()
        ];
        
        return $result;
    }
    
    private function preprocessQuery(array $arguments): array {
        // Add search operators or modify query
        $query = $arguments['query'] ?? '';
        
        // Example: add site restriction for academic content
        if (strpos($query, 'research') !== false) {
            $arguments['query'] = $query . ' site:edu OR site:org';
        }
        
        return $arguments;
    }
    
    private function postprocessResults(array $result): array {
        if ($result['isError']) {
            return $result;
        }
        
        $data = json_decode($result['content'][0]['text'], true);
        
        // Add custom metadata or filtering
        foreach ($data['results'] as &$searchResult) {
            $searchResult['domain'] = parse_url($searchResult['url'], PHP_URL_HOST);
            $searchResult['trust_score'] = $this->calculateTrustScore($searchResult);
        }
        
        $result['content'][0]['text'] = json_encode($data, JSON_PRETTY_PRINT);
        return $result;
    }
    
    private function calculateTrustScore(array $result): float {
        $domain = parse_url($result['url'], PHP_URL_HOST);
        
        // Simple trust scoring based on domain
        $trustedDomains = ['wikipedia.org', 'github.com', 'stackoverflow.com'];
        $score = 0.5; // Base score
        
        if (in_array($domain, $trustedDomains)) {
            $score += 0.3;
        }
        
        // penalize content farms and low-quality sites
        $lowQualityDomains = ['example.com', 'test.com'];
        if (in_array($domain, $lowQualityDomains)) {
            $score -= 0.3;
        }
        
        return max(0, min(1, $score));
    }
}

// Usage
$enhancedTool = new EnhancedSearchTool();
$result = $enhancedTool->execute(['query' => 'machine learning research'], null);
```

### Multi-Endpoint Failover

```php
class FailoverSearchTool extends SearchTool {
    private $endpoints = [
        'http://127.0.0.1:8080',
        'https://searxng.example.com',
        'https://backup-searxng.com'
    ];
    
    public function execute(array $arguments, $configuration): array {
        foreach ($this->endpoints as $endpoint) {
            try {
                $this->searchEndpoint = $endpoint;
                $result = parent::execute($arguments, $configuration);
                
                if (!$result['isError']) {
                    return $result;
                }
            } catch (\Exception $e) {
                // Continue to next endpoint
                continue;
            }
        }
        
        return ['isError' => true, 'content' => [['text' => 'All search endpoints failed']]];
    }
}
```

## Version History

- **v1.2**: Enhanced error handling and debug mode
- **v1.1**: Added configurable endpoint and improved response parsing
- **v1.0**: Initial implementation with basic SearXNG integration