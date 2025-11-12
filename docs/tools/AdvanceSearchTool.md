# AdvanceSearchTool Documentation

## Overview

The `AdvanceSearchTool` is a comprehensive Viceroy tool designed for advanced web searching and content aggregation. It performs searches using SearXNG, fetches and cleans content from search results, and provides intelligent summarization using LLM analysis. The tool includes sophisticated filtering mechanisms, dynamic result processing, and detailed statistics tracking.

## Features

- **Multi-stage Search Pipeline**: Performs initial search, fetches content, and generates comprehensive summaries
- **Intelligent Content Filtering**: Dynamic filtering strictness based on results collected and content relevance
- **Listing Page Detection**: Identifies and processes listing pages differently, extracting relevant links
- **Retry Logic**: Built-in retry mechanisms for content fetching with configurable attempts
- **Performance Metrics**: Detailed timing and statistics tracking for debugging and optimization
- **Dynamic Search Expansion**: Automatically performs additional searches when needed results aren't found
- **LLM Integration**: Uses OpenAI-compatible endpoints for content summarization and relevance analysis

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| query | string | Yes | The search query string to look up on the web |
| limit | integer | No | Maximum number of results to return and summarize. Default: 10 |
| filtering_strictness | number | No | How strict to filter results (0.0 = very permissive, 1.0 = very strict). Default: 0.7, Range: 0.0-1.0 |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "JSON string containing query results, metadata, and statistics"
    }
  ],
  "isError": boolean
}
```

The text content contains a JSON object with:

```json
{
  "query": "search query string",
  "total": number_of_results,
  "results": [
    {
      "url": "result_url",
      "summary": "content_summary",
      "source_type": "search_result|listing_page|listing_link",
      "title": "result_title",
      "fetch_time": seconds,
      "summary_time": seconds
    }
  ],
  "metadata": {
    "query": "search_query",
    "limit": requested_limit,
    "start_time": timestamp,
    "search_time": seconds,
    "fetch_time": seconds,
    "summary_time": seconds,
    "total_time": seconds
  },
  "statistics": {
    "total_processed": number,
    "successful_fetches": number,
    "failed_fetches": number,
    "successful_summaries": number,
    "failed_summaries": number,
    "listing_pages_found": number,
    "listing_links_processed": number,
    "filtered_out_listing_pages": number,
    "filtered_out_irrelevant": number,
    "fallback_included": number,
    "additional_searches": number,
    "errors": []
  }
}
```

### Source Types

| Source Type | Description |
|-------------|-------------|
| search_result | Direct search result with summarized content |
| listing_page | Page that aggregates links (like news listings) |
| listing_link | Individual link extracted from a listing page |

## Processing Pipeline

### Search and Fetch Process

1. **Initial Search**: Performs search using SearXNG with configurable endpoint
2. **Content Fetching**: Downloads and cleans content from each search result
3. **LLM Analysis**: Uses LLM to summarize content and determine relevance
4. **Dynamic Filtering**: Applies intelligent filtering based on content quality and quantity
5. **Listing Processing**: For listing pages, extracts and processes relevant links
6. **Result Aggregation**: Combines results until the requested limit is reached

### Dynamic Filtering Behavior

The tool implements dynamic filtering that becomes more permissive as results are collected:

- **< 20% of target results**: Very permissive (30% of base strictness)
- **20-50% of target results**: Moderately permissive (50% of base strictness)
- **50-80% of target results**: Slightly permissive (80% of base strictness)
- **> 80% of target results**: Uses base filtering strictness

## Usage Examples

### Basic Usage

```php
use Viceroy\Tools\AdvanceSearchTool;

$tool = new AdvanceSearchTool();
$result = $tool->execute([
    'query' => 'artificial intelligence trends 2024',
    'limit' => 5
], $configuration);

if (!$result['isError']) {
    $data = json_decode($result['content'][0]['text'], true);
    echo "Found {$data['total']} results for: {$data['query']}\n";
    
    foreach ($data['results'] as $index => $item) {
        echo ($index + 1) . ". {$item['title']}\n";
        echo "   URL: {$item['url']}\n";
        echo "   Type: {$item['source_type']}\n";
        echo "   Summary: " . substr($item['summary'], 0, 200) . "...\n\n";
    }
    
    echo "Statistics:\n";
    echo "- Processed: {$data['statistics']['total_processed']}\n";
    echo "- Successful fetches: {$data['statistics']['successful_fetches']}\n";
    echo "- Total time: {$data['metadata']['total_time']}s\n";
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Advanced Usage with Custom Filtering

```php
$tool = new AdvanceSearchTool(
    $searchEndpoint = 'http://localhost:8080',  // Custom SearXNG endpoint
    $debugMode = true,                        // Enable debug logging
    $filteringStrictness = 0.5                 // More permissive filtering
);

$result = $tool->execute([
    'query' => 'machine learning frameworks comparison',
    'limit' => 8,
    'filtering_strictness' => 0.3  // Override with very permissive filtering
], $configuration);

// Process results with detailed statistics
$data = json_decode($result['content'][0]['text'], true);

echo "Performance breakdown:\n";
echo "- Search time: {$data['metadata']['search_time']}s\n";
echo "- Fetch time: {$data['metadata']['fetch_time']}s\n";
echo "- Summary time: {$data['metadata']['summary_time']}s\n";

echo "\nListing pages found: {$data['statistics']['listing_pages_found']}\n";
echo "Links from listings: {$data['statistics']['listing_links_processed']}\n";
```

### Debug Mode Usage

```php
// Enable debug mode to see detailed processing information
$tool = new AdvanceSearchTool(null, true, 0.7);

$result = $tool->execute([
    'query' => 'quantum computing applications',
    'limit' => 3
], $configuration);

// Check debug output in error logs for:
// - Search query details
// - Content fetching attempts
// - LLM prompts and responses
// - Filtering decisions
// - Performance metrics
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new AdvanceSearchTool());

// Execute the tool
$result = $toolManager->executeTool('advance_search', [
    'query' => 'renewable energy technologies',
    'limit' => 10,
    'filtering_strictness' => 0.8
], $configuration);
```

### Using with MCP (Model Context Protocol)

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'advance_search',
    'arguments' => [
        'query' => 'blockchain scalability solutions',
        'limit' => 5
    ]
]);
```

## Technical Implementation

### Dependencies

- **SearXNG Search Engine**: Requires access to a SearXNG instance (default: http://127.0.0.1:8080)
- **OpenAI-Compatible LLM**: For content summarization and relevance analysis
- **Guzzle HTTP Client**: For web requests and content fetching
- **WebPageToMarkdownTool**: Used internally for content cleaning

### Configuration Options

The tool supports configuration through:

1. **Constructor Parameters**:
   ```php
   new AdvanceSearchTool($searchEndpoint, $debugMode, $filteringStrictness)
   ```

2. **Environment Variables**:
   - `SEARCH_ENDPOINT`: Default SearXNG endpoint URL

3. **Runtime Configuration**:
   - Parameters can be overridden per execution
   - Filtering strictness can be dynamically adjusted

### Performance Considerations

- **Timeout Settings**: 60-second timeout for search requests, 5-second timeout for content fetching
- **Retry Logic**: Up to 2 retries for failed content fetches
- **Content Limits**: Maximum 150,000 characters processed per page for summarization
- **Caching**: No built-in caching (relies on external caching if needed)

### Debug Mode

When debug mode is enabled, the tool logs detailed information to PHP error log:

- Search queries and endpoints
- Content fetching attempts and results
- LLM prompts and responses
- Filtering decisions and thresholds
- Performance timing breakdown
- Error details and stack traces

## Troubleshooting

### Common Issues

1. **Search Endpoint Not Available**:
   ```
   Error: SearchNX connection error: Connection refused
   ```
   **Solution**: Ensure SearXNG is running and accessible at the configured endpoint

2. **LLM Connection Issues**:
   ```
   Error: Summary failed: Connection timeout
   ```
   **Solution**: Check LLM endpoint configuration and network connectivity

3. **Content Filtering Too Strict**:
   ```
   Fewer results than expected (filtered_out_irrelevant: high)
   ```
   **Solution**: Reduce filtering_strictness parameter or enable debug mode to see filtering decisions

4. **JSON Parsing Errors**:
   ```
   Error: Invalid JSON from SearchNX
   ```
   **Solution**: Check SearXNG endpoint configuration and response format

### Debug Information

Enable debug mode to get detailed troubleshooting information:

```php
$tool = new AdvanceSearchTool(null, true, 0.7);
```

Debug output includes:
- Search request details and responses
- Content fetching attempts and results
- LLM interaction details
- Filtering thresholds and decisions
- Performance metrics and timing

### Performance Tuning

1. **Adjust Filtering Strictness**:
   ```php
   // For comprehensive results (lower quality, higher quantity)
   'filtering_strictness' => 0.3
   
   // For high-quality results (higher filtering)
   'filtering_strictness' => 0.9
   ```

2. **Optimize Result Limits**:
   ```php
   // For quick searches
   'limit' => 3
   
   // For comprehensive research
   'limit' => 15
   ```

3. **Monitor Statistics**:
   Check the statistics object to identify bottlenecks:
   - High `failed_fetches`: Network or content issues
   - High `filtered_out_irrelevant`: Filtering too strict
   - High `additional_searches`: Query too specific

## Best Practices

1. **Query Construction**:
   - Use specific, well-formed queries for better results
   - Include relevant keywords and context
   - Avoid overly broad queries that may return irrelevant results

2. **Filtering Configuration**:
   - Start with moderate filtering (0.7) and adjust based on results
   - Use lower strictness for exploratory searches
   - Use higher strictness for focused research

3. **Performance Optimization**:
   - Use appropriate result limits to balance quality and speed
   - Monitor statistics to identify processing bottlenecks
   - Consider caching frequent searches if needed

4. **Error Handling**:
   - Always check the `isError` flag in responses
   - Examine the statistics object for partial success scenarios
   - Use debug mode during development and troubleshooting

5. **Integration Patterns**:
   - Combine with other tools for comprehensive research workflows
   - Use the source type information to process different result types appropriately
   - Leverage the timing metadata for performance monitoring

## Version History

- **v2.1**: Added dynamic filtering strictness and improved listing page processing
- **v2.0**: Complete rewrite with LLM integration and enhanced content analysis
- **v1.0**: Initial implementation with basic search and content fetching