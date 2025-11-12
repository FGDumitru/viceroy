# GetRedditHot Documentation

## Overview

The `GetRedditHot` tool is an advanced Reddit content fetching and analysis tool that provides comprehensive information about Reddit posts including content analysis, comment summarization, and sentiment analysis. It supports multiple sorting types, subreddits, and various analysis options with intelligent caching for performance optimization.

## Features

- **Multiple Sort Types**: Supports hot, new, top, and rising post sorting
- **Content Analysis**: Uses LLM to analyze linked content from posts
- **Comment Analysis**: Fetches and summarizes comments from Reddit threads
- **Sentiment Analysis**: Provides sentiment analysis for both content and comments
- **Intelligent Caching**: Built-in caching system with configurable TTL to reduce API calls
- **Direct RSS Parsing**: Uses Reddit's RSS feeds for reliable data extraction without LLM dependency
- **Comprehensive Error Handling**: Robust error handling with detailed error messages
- **Flexible Configuration**: Optional parameters for different analysis levels

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| subreddit | string | No | The subreddit name (without r/ prefix). Defaults to front page |
| sort_type | string | No | Sort type for posts: hot, new, top, rising. Default: hot |
| limit | integer | No | Maximum number of posts to fetch and analyze. Default: 5, Range: 1-25 |
| include_content_analysis | boolean | No | Whether to fetch and analyze linked content. Default: true |
| include_comment_analysis | boolean | No | Whether to fetch and analyze comments. Default: true |
| include_sentiment_analysis | boolean | No | Whether to perform sentiment analysis on content and comments. Default: true |

### Valid Sort Types

| Sort Type | Description |
|------------|-------------|
| hot | Posts ranked by popularity and recency (default) |
| new | Most recently submitted posts |
| top | Highest scoring posts (all time) |
| rising | Posts with rapidly increasing scores |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "JSON string containing posts and metadata"
    }
  ],
  "isError": boolean
}
```

The text content contains a JSON object with:

```json
{
  "posts": [
    {
      "title": "post_title",
      "link": "post_url",
      "score": vote_score,
      "comments": comment_count,
      "comments_link": "comments_url",
      "author": "username",
      "subreddit": "subreddit_name",
      "category": "content_category",
      "published_at": "ISO_8601_timestamp",
      "content": "post_content_text",
      "content_summary": "content_analysis_summary",
      "content_sentiment": "positive|negative|neutral",
      "comments_summary": "comments_analysis_summary",
      "comments_sentiment": "positive|negative|neutral"
    }
  ],
  "metadata": {
    "subreddit": "target_subreddit_or_frontpage",
    "sort_type": "used_sort_type",
    "limit": "requested_limit",
    "total_posts": "actual_post_count",
    "fetched_at": "ISO_8601_timestamp"
  }
}
```

## Usage Examples

### Basic Usage (Front Page Hot Posts)

```php
use Viceroy\Tools\GetRedditHot;

$tool = new GetRedditHot();
$result = $tool->execute([], null);

if (!$result['isError']) {
    $data = json_decode($result['content'][0]['text'], true);
    echo "Fetched {$data['metadata']['total_posts']} posts from Reddit front page\n\n";
    
    foreach ($data['posts'] as $index => $post) {
        echo ($index + 1) . ". {$post['title']}\n";
        echo "   Subreddit: r/{$post['subreddit']}\n";
        echo "   Score: {$post['score']} | Comments: {$post['comments']}\n";
        echo "   Author: {$post['author']}\n";
        echo "   Sentiment: Content: {$post['content_sentiment']}, Comments: {$post['comments_sentiment']}\n\n";
    }
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Specific Subreddit with Custom Sort

```php
$tool = new GetRedditHot();
$result = $tool->execute([
    'subreddit' => 'technology',
    'sort_type' => 'new',
    'limit' => 8,
    'include_sentiment_analysis' => true,
    'include_content_analysis' => true,
    'include_comment_analysis' => true
], null);

if (!$result['isError']) {
    $data = json_decode($result['content'][0]['text'], true);
    
    echo "New posts from r/technology:\n";
    foreach ($data['posts'] as $post) {
        echo "Title: {$post['title']}\n";
        echo "URL: {$post['link']}\n";
        echo "Content Summary: {$post['content_summary']}\n";
        echo "Comments Summary: {$post['comments_summary']}\n";
        echo "---\n";
    }
}
```

### Minimal Analysis for Faster Results

```php
$tool = new GetRedditHot();
$result = $tool->execute([
    'subreddit' => 'science',
    'limit' => 10,
    'include_content_analysis' => false,
    'include_comment_analysis' => false,
    'include_sentiment_analysis' => false
], null);

// This will only fetch basic post metadata without analysis
// Much faster but less detailed information
```

### Custom Cache TTL

```php
// Create tool with custom cache TTL (10 minutes instead of default 5)
$tool = new GetRedditHot(
    $httpClient = null,
    $cacheTtl = 600  // 10 minutes in seconds
);

$result = $tool->execute(['subreddit' => 'programming'], null);
```

### Error Handling

```php
$tool = new GetRedditHot();

// Test invalid subreddit
$result = $tool->execute(['subreddit' => 'invalid#subreddit'], null);
if ($result['isError']) {
    echo "Expected error for invalid subreddit: " . $result['content'][0]['text'];
}

// Test invalid sort type
$result = $tool->execute(['sort_type' => 'invalid_sort'], null);
if ($result['isError']) {
    echo "Expected error for invalid sort type: " . $result['content'][0]['text'];
}

// Test invalid limit
$result = $tool->execute(['limit' => 30], null);  // Over maximum of 25
if ($result['isError']) {
    echo "Expected error for invalid limit: " . $result['content'][0]['text'];
}
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new GetRedditHot());

// Execute tool
$result = $toolManager->executeTool('get_reddit_hot', [
    'subreddit' => 'MachineLearning',
    'sort_type' => 'hot',
    'limit' => 5,
    'include_sentiment_analysis' => true
], $configuration);
```

### Using with MCP (Model Context Protocol)

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'get_reddit_hot',
    'arguments' => [
        'subreddit' => 'php',
        'sort_type' => 'new',
        'limit' => 3
    ]
]);
```

### Integration in LLM Workflows

The tool is particularly useful for LLM applications that need:

```php
// Example LLM system prompt that uses the tool
$systemPrompt = "You are a helpful assistant that can analyze Reddit trends and discussions. 
When users ask about Reddit content, use the get_reddit_hot tool to fetch current posts and analyze them.";

$connection->setSystemMessage($systemPrompt);
$connection->addToolDefinition(new GetRedditHot());

// LLM can now fetch and analyze Reddit content
$response = $connection->query("What are the trending topics in r/artificial right now?");
```

## Technical Implementation

### Dependencies

- **Guzzle HTTP Client**: For fetching RSS feeds and comment data
- **OpenAI-Compatible LLM**: For content and comment analysis
- **WebPageToMarkdownTool**: Used internally for content analysis
- **PHP XML Parser**: For processing Reddit RSS feeds
- **PHP JSON Extension**: For processing Reddit comment API responses

### Data Sources

1. **RSS Feeds**: Uses Reddit's old.reddit.com RSS feeds for reliable post data
   - Front page: `https://old.reddit.com/hot.rss`
   - Subreddit: `https://old.reddit.com/r/subreddit/hot.rss`

2. **Comment API**: Uses Reddit's JSON API for comment data
   - Format: `https://old.reddit.com/comments/thread_id.json`

### Caching Strategy

The tool implements a multi-level caching system:

```php
// Cache keys and prefixes
const CACHE_PREFIX_RSS = 'rss_';           // RSS feed data
const CACHE_PREFIX_CONTENT = 'content_';    // Content analysis results
const CACHE_PREFIX_COMMENTS = 'comments_';   // Comment analysis results
const CACHE_PREFIX_SENTIMENT = 'sentiment_'; // Sentiment analysis results

// Default TTL: 300 seconds (5 minutes)
// Customizable via constructor parameter
```

### Content Analysis Pipeline

1. **RSS Parsing**: Extracts post metadata from RSS feeds
2. **Content Fetching**: Downloads and analyzes linked content using WebPageToMarkdownTool
3. **Comment Processing**: Fetches comments via Reddit JSON API and analyzes sentiment
4. **LLM Analysis**: Uses LLM for content summarization and sentiment analysis
5. **Result Assembly**: Combines all analysis into structured response

## Performance Considerations

### Cache Optimization

```php
// Optimize for frequent requests
$tool = new GetRedditHot(null, 1800); // 30 minute cache

// For development/testing
$tool = new GetRedditHot(null, 0); // Disable caching
```

### Analysis Performance Impact

| Analysis Type | Performance Impact | Typical Time |
|---------------|-------------------|--------------|
| None (metadata only) | Minimal | < 1 second |
| Content Analysis | Moderate | 3-5 seconds per post |
| Comment Analysis | High | 5-10 seconds per post |
| Sentiment Analysis | Low-Moderate | < 1 second per text |
| All Analysis | High | 10-15 seconds for 5 posts |

### Recommended Settings

```php
// For quick updates
$quickConfig = [
    'include_content_analysis' => false,
    'include_comment_analysis' => false,
    'include_sentiment_analysis' => false
];

// For comprehensive analysis
$fullConfig = [
    'include_content_analysis' => true,
    'include_comment_analysis' => true,
    'include_sentiment_analysis' => true
];

// For balanced approach
$balancedConfig = [
    'include_content_analysis' => true,
    'include_comment_analysis' => false,
    'include_sentiment_analysis' => true
];
```

## Troubleshooting

### Common Issues

1. **Invalid Subreddit Names**:
   ```
   Error: Invalid arguments provided.
   ```
   **Solution**: Use valid subreddit names without special characters or spaces

2. **Network Connectivity Issues**:
   ```
   Error: Failed to fetch Reddit RSS feed.
   ```
   **Solution**: Check network connection and Reddit accessibility

3. **Content Analysis Failures**:
   ```
   Content analysis failed: Connection timeout
   ```
   **Solution**: Check LLM endpoint configuration or disable content analysis

4. **Rate Limiting**:
   ```
   HTTP 429: Too Many Requests
   ```
   **Solution**: Implement proper caching and reduce request frequency

### Debug Information

The tool includes built-in error handling and logging. For debugging:

```php
// Check cache effectiveness
$tool = new GetRedditHot();
$result1 = $tool->execute(['subreddit' => 'test'], null);
$result2 = $tool->execute(['subreddit' => 'test'], null); // Should use cache

// Monitor performance
$startTime = microtime(true);
$result = $tool->execute(['subreddit' => 'test'], null);
$duration = microtime(true) - $startTime;
echo "Request completed in {$duration} seconds";
```

### Error Recovery Strategies

```php
function robustRedditFetch($subreddit, $maxRetries = 3) {
    $tool = new GetRedditHot();
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = $tool->execute([
            'subreddit' => $subreddit,
            'limit' => 5,
            'include_content_analysis' => false  // Faster for retries
        ], null);
        
        if (!$result['isError']) {
            return $result;
        }
        
        if ($attempt < $maxRetries) {
            sleep(2); // Wait 2 seconds before retry
        }
    }
    
    return ['isError' => true, 'content' => [['text' => 'All retries failed']]];
}
```

## Best Practices

1. **Subreddit Selection**:
   - Use specific, relevant subreddit names for targeted content
   - Consider community size and activity levels
   - Respect subreddit rules and content policies

2. **Performance Optimization**:
   - Enable caching for frequently requested subreddits
   - Disable unnecessary analysis features for faster results
   - Use appropriate limits for your use case

3. **Error Handling**:
   - Always check the `isError` flag in responses
   - Implement retry logic for network-related failures
   - Provide fallback options when analysis fails

4. **Rate Limiting**:
   - Implement request throttling for high-volume usage
   - Use caching to reduce redundant requests
   - Monitor Reddit API rate limits

5. **Content Analysis**:
   ```php
   // Best practice for analysis configuration
   function getAnalysisConfig($priority) {
       switch ($priority) {
           case 'speed':
               return [
                   'include_content_analysis' => false,
                   'include_comment_analysis' => false,
                   'include_sentiment_analysis' => false
               ];
           case 'balance':
               return [
                   'include_content_analysis' => true,
                   'include_comment_analysis' => false,
                   'include_sentiment_analysis' => true
               ];
           case 'comprehensive':
               return [
                   'include_content_analysis' => true,
                   'include_comment_analysis' => true,
                   'include_sentiment_analysis' => true
               ];
       }
   }
   ```

## Advanced Usage

### Custom Analysis Pipeline

```php
class RedditAnalyzer {
    private $tool;
    
    public function __construct($cacheTtl = 300) {
        $this->tool = new GetRedditHot(null, $cacheTtl);
    }
    
    public function getTrendingTopics($subreddit, $days = 7) {
        // Get hot posts with sentiment analysis
        $result = $this->tool->execute([
            'subreddit' => $subreddit,
            'sort_type' => 'hot',
            'limit' => 20,
            'include_sentiment_analysis' => true,
            'include_content_analysis' => true,
            'include_comment_analysis' => false
        ], null);
        
        if ($result['isError']) {
            return null;
        }
        
        $data = json_decode($result['content'][0]['text'], true);
        $topics = [];
        
        // Analyze sentiment distribution
        $sentimentCounts = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        
        foreach ($data['posts'] as $post) {
            $sentiment = $post['content_sentiment'];
            if (isset($sentimentCounts[$sentiment])) {
                $sentimentCounts[$sentiment]++;
            }
            
            // Extract topics from titles (simplified)
            $topics[] = [
                'title' => $post['title'],
                'sentiment' => $sentiment,
                'score' => $post['score']
            ];
        }
        
        return [
            'topics' => $topics,
            'sentiment_distribution' => $sentimentCounts,
            'metadata' => $data['metadata']
        ];
    }
    
    public function compareSubreddits($subreddits) {
        $results = [];
        
        foreach ($subreddits as $subreddit) {
            $result = $this->tool->execute([
                'subreddit' => $subreddit,
                'limit' => 5,
                'include_sentiment_analysis' => true,
                'include_content_analysis' => false,
                'include_comment_analysis' => false
            ], null);
            
            if (!$result['isError']) {
                $data = json_decode($result['content'][0]['text'], true);
                $results[$subreddit] = $data;
            }
        }
        
        return $results;
    }
}

// Usage
$analyzer = new RedditAnalyzer(600); // 10 minute cache
$trending = $analyzer->getTrendingTopics('technology');
$comparison = $analyzer->compareSubreddits(['php', 'python', 'javascript']);
```

## Version History

- **v2.1**: Enhanced caching system and improved error handling
- **v2.0**: Complete rewrite with LLM integration and sentiment analysis
- **v1.5**: Added comment analysis and sentiment detection
- **v1.0**: Initial implementation with basic RSS parsing