# WebPageToMarkdownTool Documentation

## Overview

The `WebPageToMarkdownTool` is a sophisticated Viceroy tool designed to fetch web pages and convert them to clean, readable Markdown format. It implements intelligent content extraction, removes clutter, handles various content types, and provides robust error handling for reliable web content processing.

## Features

- **Intelligent Content Extraction**: Uses heuristic algorithms to identify and extract main content from web pages
- **Multiple Content Type Support**: Handles HTML, plain text, JSON, and XML responses
- **Markdown Conversion**: Converts HTML to clean, readable Markdown format
- **Clutter Removal**: Automatically removes navigation, ads, scripts, and other non-content elements
- **URL Resolution**: Converts relative URLs to absolute URLs for proper linking
- **Character Encoding Handling**: Detects and properly handles various character encodings
- **Debug Mode**: Optional debug logging for troubleshooting
- **Error Handling**: Comprehensive error handling for network and parsing issues
- **Configurable Timeouts**: Adjustable timeout settings for different use cases

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| url | string | Yes | The URL of the webpage to visit |
| timeout | number | No | Connection timeout in seconds. Default: 10 |
| raw | boolean | No | If true, return raw webpage content instead of converted Markdown. Default: false |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "markdown_content_or_raw_html"
    }
  ],
  "isError": boolean
}
```

**Successful Response Example (Markdown):**
```json
{
  "content": [
    {
      "type": "text",
      "text": "# Article Title\n\nThis is the main content of the article converted to clean Markdown format..."
    }
  ],
  "isError": false
}
```

**Successful Response Example (Raw):**
```json
{
  "content": [
    {
      "type": "text",
      "text": "<!DOCTYPE html><html><head><title>Page Title</title></head><body>...</body></html>"
    }
  ],
  "isError": false
}
```

**Error Response Example:**
```json
{
  "content": [
    {
      "type": "text",
      "text": "Error: Network or HTTP issue when fetching webpage - Connection timeout"
    }
  ],
  "isError": true
}
```

## Content Processing Pipeline

### HTML Processing Stages

1. **Content Fetching**: Downloads webpage with proper HTTP headers and timeout handling
2. **Encoding Detection**: Detects character encoding and converts to UTF-8 if needed
3. **DOM Parsing**: Parses HTML into DOM structure for processing
4. **Content Cleaning**: Removes unwanted elements (ads, navigation, scripts, etc.)
5. **Main Content Detection**: Identifies primary content areas using heuristics
6. **URL Resolution**: Converts relative URLs to absolute URLs
7. **Markdown Conversion**: Converts cleaned HTML to Markdown format
8. **Post-processing**: Final cleanup of Markdown output

### Content Type Handling

The tool handles different response types appropriately:

| Content Type | Processing Method |
|---------------|------------------|
| `text/html` or `application/xhtml+xml` | Full HTML to Markdown conversion |
| `text/plain` | Direct return as Markdown |
| `application/json` | Formatted as code block with syntax highlighting |
| `application/xml` | Formatted as XML code block |
| Other types | Raw content returned |

## Usage Examples

### Basic Usage

```php
use Viceroy\Tools\WebPageToMarkdownTool;

$tool = new WebPageToMarkdownTool();
$result = $tool->execute([
    'url' => 'https://example.com/article'
], null);

if (!$result['isError']) {
    echo $result['content'][0]['text'];
    // Output: Clean Markdown content of the webpage
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Raw Content Fetching

```php
$tool = new WebPageToMarkdownTool();
$result = $tool->execute([
    'url' => 'https://api.example.com/data',
    'raw' => true  // Return raw HTML/JSON/XML instead of Markdown
], null);

if (!$result['isError']) {
    $rawContent = $result['content'][0]['text'];
    // Process raw content (e.g., parse JSON, handle XML, etc.)
}
```

### Custom Timeout

```php
$tool = new WebPageToMarkdownTool();
$result = $tool->execute([
    'url' => 'https://slow-website.com/content',
    'timeout' => 30  // 30 second timeout instead of default 10
], null);
```

### Debug Mode

```php
$tool = new WebPageToMarkdownTool(
    $httpClient = null,
    $htmlConverter = null,
    $debugMode = true  // Enable debug output
);

$result = $tool->execute([
    'url' => 'https://example.com'
], null);

// Debug information will be output to error log
```

### Error Handling

```php
$tool = new WebPageToMarkdownTool();

// Test invalid URL
$result = $tool->execute(['url' => 'invalid-url'], null);
if ($result['isError']) {
    echo "Expected error for invalid URL: " . $result['content'][0]['text'];
}

// Test non-existent URL
$result = $tool->execute(['url' => 'https://nonexistent-domain-12345.com'], null);
if ($result['isError']) {
    echo "Expected error for non-existent domain: " . $result['content'][0]['text'];
}

// Test HTTP error
$result = $tool->execute(['url' => 'https://httpbin.org/status/404'], null);
if ($result['isError']) {
    echo "Expected error for 404 status: " . $result['content'][0]['text'];
}
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new WebPageToMarkdownTool());

// Execute tool
$result = $toolManager->executeTool('visit_webpage', [
    'url' => 'https://example.com/article',
    'timeout' => 15
], $configuration);

if (!$result['isError']) {
    $markdown = $result['content'][0]['text'];
    // Process Markdown content
}
```

### Using with MCP (Model Context Protocol)

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'visit_webpage',
    'arguments' => [
        'url' => 'https://news.example.com/article'
    ]
]);
```

### Integration in LLM Workflows

The tool is essential for LLM applications that need to access web content:

```php
// Example LLM system prompt that uses the tool
$systemPrompt = "You are a helpful assistant that can access and analyze web content. 
When users provide URLs or ask about content from specific websites, use the visit_webpage tool to fetch and analyze the content.";

$connection->setSystemMessage($systemPrompt);
$connection->addToolDefinition(new WebPageToMarkdownTool());

// LLM can now access web content
$response = $connection->query("Please summarize the content at https://example.com/article");
```

## Technical Implementation

### Dependencies

- **Guzzle HTTP Client**: For web requests and content fetching
- **PHP DOM Extension**: For HTML parsing and manipulation
- **PHP XML Extension**: For character encoding detection
- **League HTML to Markdown**: For HTML to Markdown conversion
- **PSR-7 URI Components**: For URL resolution and manipulation

### HTTP Configuration

The tool uses optimized HTTP settings:

```php
$client = new Client([
    'timeout' => $timeout,
    'allow_redirects' => true,
    'http_errors' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'DNT' => '1'
    ]
]);
```

### Content Extraction Algorithm

The tool implements a sophisticated content extraction process:

1. **Semantic Element Detection**: Prioritizes `<main>`, `<article>`, and elements with `itemprop="articleBody"`
2. **ID/Class Heuristics**: Looks for common content identifiers like `content`, `main`, `article-body`
3. **Scoring Algorithm**: Scores potential content nodes based on text length, element types, and attributes
4. **Link Density Analysis**: Penalizes nodes with high link-to-text ratios
5. **Structural Analysis**: Rewards nodes with proper heading structure

### HTML Cleaning Process

The tool removes unwanted elements using XPath selectors:

```php
$unwantedSelectors = [
    '//comment()',           // HTML comments
    '//script', '//style',    // Scripts and styles
    '//nav', '//footer',     // Navigation and footers
    '//aside', '//header',    // Sidebars and headers
    '//iframe', '//noscript',  // Embedded content
    '//form', '//button',      // Interactive elements
    '//*[contains(@class, "ad") or contains(@id, "ad")]',  // Ads
    '//*[contains(@class, "cookie") or contains(@id, "cookie")]',  // Cookie notices
    '//*[contains(@class, "modal") or contains(@id, "modal")]',  // Modals
    '//*[contains(@class, "sidebar") or contains(@id, "sidebar")]'  // Sidebars
];
```

### URL Resolution

The tool properly resolves relative URLs to absolute URLs:

```php
private function resolveRelativeUrls(DOMNode $node, UriInterface $baseUrl): void {
    $xpath = new DOMXPath($node->ownerDocument);
    
    // Handle images
    foreach ($xpath->query('.//img', $node) as $img) {
        if ($img->hasAttribute('src') && $this->isRelativeUrl($img->getAttribute('src'))) {
            $absoluteUrl = UriResolver::resolve($baseUrl, new Uri($img->getAttribute('src')));
            $img->setAttribute('src', $absoluteUrl);
        }
    }
    
    // Handle links
    foreach ($xpath->query('.//a', $node) as $a) {
        if ($a->hasAttribute('href') && $this->isRelativeUrl($a->getAttribute('href'))) {
            $absoluteUrl = UriResolver::resolve($baseUrl, new Uri($a->getAttribute('href')));
            $a->setAttribute('href', $absoluteUrl);
        }
    }
}
```

## Performance Considerations

### Timeout Management

```php
// Configure timeouts based on use case
$quickTool = new WebPageToMarkdownTool(null, null, false);
$result = $quickTool->execute([
    'url' => $url,
    'timeout' => 5   // Fast timeout for quick checks
], null);

$thoroughTool = new WebPageToMarkdownTool(null, null, false);
$result = $thoroughTool->execute([
    'url' => $url,
    'timeout' => 30  // Longer timeout for complex pages
], null);
```

### Memory Usage

The tool implements memory-efficient processing:

- **DOM Loading**: Uses `LIBXML_COMPACT` flag to reduce memory usage
- **Content Limits**: Processes content in chunks when very large
- **Node Cleanup**: Removes unnecessary DOM nodes during processing

### Caching Strategy

While the tool doesn't implement built-in caching, you can add external caching:

```php
class CachedWebPageTool extends WebPageToMarkdownTool {
    private $cache = [];
    private $cacheTtl = 3600; // 1 hour
    
    public function execute(array $arguments, $configuration): array {
        $cacheKey = md5($arguments['url']);
        
        if (isset($this->cache[$cacheKey]) && 
            (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTtl) {
            return $this->cache[$cacheKey]['result'];
        }
        
        $result = parent::execute($arguments, $configuration);
        
        if (!$result['isError']) {
            $this->cache[$cacheKey] = [
                'result' => $result,
                'timestamp' => time()
            ];
        }
        
        return $result;
    }
}
```

## Troubleshooting

### Common Issues

1. **Connection Timeout**:
   ```
   Error: Network or HTTP issue when fetching webpage - Connection timeout
   ```
   **Solution**: Increase timeout parameter or check network connectivity

2. **Invalid URL Format**:
   ```
   Error: Invalid URL provided. Please provide a valid HTTP or HTTPS URL.
   ```
   **Solution**: Ensure URL includes protocol (http:// or https://)

3. **Character Encoding Issues**:
   ```
   Garbled text output
   ```
   **Solution**: Enable debug mode to check detected encoding

4. **No Content Found**:
   ```
   No significant content found at URL
   ```
   **Solution**: Check if page is accessible or requires JavaScript

5. **HTTP Error Codes**:
   ```
   Error: Failed to fetch webpage content. HTTP Status Code: 403
   ```
   **Solution**: Check access restrictions, user agent, or authentication requirements

### Debug Information

Enable debug mode for detailed troubleshooting:

```php
$tool = new WebPageToMarkdownTool(null, null, true);
```

Debug output includes:
- HTTP request details and headers
- Response status and content type
- Character encoding detection
- DOM processing steps
- Content extraction decisions
- Final Markdown output

### Content Extraction Issues

```php
// Test content extraction on problematic pages
function testContentExtraction($url) {
    $tool = new WebPageToMarkdownTool(null, null, true);
    
    echo "Testing URL: $url\n";
    
    // Test with different timeouts
    foreach ([5, 10, 30] as $timeout) {
        echo "Timeout: {$timeout}s\n";
        
        $result = $tool->execute([
            'url' => $url,
            'timeout' => $timeout
        ], null);
        
        if (!$result['isError']) {
            $content = $result['content'][0]['text'];
            echo "Content length: " . strlen($content) . " characters\n";
            echo "First 200 chars: " . substr($content, 0, 200) . "...\n";
        } else {
            echo "Error: " . $result['content'][0]['text'] . "\n";
        }
        
        echo "---\n";
    }
}

// Usage
testContentExtraction('https://example.com/complex-page');
```

## Best Practices

1. **URL Handling**:
   - Always include protocol (http:// or https://)
   - Encode special characters properly
   - Validate URLs before processing

2. **Timeout Configuration**:
   - Use shorter timeouts (5-10s) for quick content checks
   - Use longer timeouts (20-30s) for complex or slow pages
   - Implement retry logic for network reliability

3. **Error Handling**:
   - Always check the `isError` flag in responses
   - Implement fallback strategies for failed requests
   - Handle different content types appropriately

4. **Content Processing**:
   ```php
   // Best practice for content processing
   function processWebPage($url, $options = []) {
       $tool = new WebPageToMarkdownTool();
       
       $args = [
           'url' => $url,
           'timeout' => $options['timeout'] ?? 10,
           'raw' => $options['raw'] ?? false
       ];
       
       $result = $tool->execute($args, null);
       
       if ($result['isError']) {
           throw new Exception("Failed to fetch content: " . $result['content'][0]['text']);
       }
       
       $content = $result['content'][0]['text'];
       
       // Additional processing based on content type
       if ($options['analyze_structure']) {
           return analyzeMarkdownStructure($content);
       }
       
       if ($options['extract_links']) {
           return extractLinksFromMarkdown($content);
       }
       
       return $content;
   }
   ```

5. **Performance Optimization**:
   - Implement caching for frequently accessed pages
   - Use appropriate timeout values for different use cases
   - Consider parallel processing for multiple pages

## Advanced Usage

### Custom Content Extraction

```php
class CustomWebPageTool extends WebPageToMarkdownTool {
    protected function findMainContentNode(DOMDocument $dom, DOMXPath $xpath): ?DOMNode {
        // Custom content detection logic
        $customSelectors = [
            '//div[@class="article-body"]',
            '//div[@id="main-content"]',
            '//section[contains(@class, "content")]'
        ];
        
        foreach ($customSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                if ($this->isPotentiallyMainContent($node)) {
                    return $node;
                }
            }
        }
        
        // Fall back to parent implementation
        return parent::findMainContentNode($dom, $xpath);
    }
    
    protected function scoreContentNode(DOMNode $node): float {
        $score = parent::scoreContentNode($node);
        
        // Custom scoring logic
        $text = strtolower(trim($node->textContent));
        
        // Boost score for content-rich nodes
        $wordCount = str_word_count($text);
        if ($wordCount > 200) {
            $score += 10;
        }
        
        // Penalize nodes with high link density
        $links = $node->getElementsByTagName('a');
        if ($links->length > 0 && $wordCount > 0) {
            $linkRatio = $links->length / $wordCount;
            if ($linkRatio > 0.1) {
                $score -= 15;
            }
        }
        
        return max(0, $score);
    }
}
```

### Content Analysis Pipeline

```php
class AnalyzingWebPageTool extends WebPageToMarkdownTool {
    public function execute(array $arguments, $configuration): array {
        $result = parent::execute($arguments, $configuration);
        
        if ($result['isError']) {
            return $result;
        }
        
        $content = $result['content'][0]['text'];
        $analysis = $this->analyzeContent($content, $arguments['url']);
        
        $result['content'][0]['text'] = $content . "\n\n---\n\n" . $analysis;
        
        return $result;
    }
    
    private function analyzeContent(string $markdown, string $url): string {
        $lines = explode("\n", $markdown);
        
        $analysis = "## Content Analysis\n\n";
        $analysis .= "**URL:** $url\n\n";
        $analysis .= "**Statistics:**\n";
        $analysis .= "- Lines: " . count($lines) . "\n";
        $analysis .= "- Characters: " . strlen($markdown) . "\n";
        $analysis .= "- Words: " . str_word_count($markdown) . "\n\n";
        
        // Extract headings
        $headings = [];
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $title = trim($matches[2]);
                $headings[] = ['level' => $level, 'title' => $title];
            }
        }
        
        if (!empty($headings)) {
            $analysis .= "**Structure:**\n";
            foreach ($headings as $heading) {
                $indent = str_repeat('  ', $heading['level'] - 1);
                $analysis .= "{$indent}- {$heading['title']}\n";
            }
        }
        
        return $analysis;
    }
}
```

## Version History

- **v2.2**: Enhanced content extraction algorithm and improved URL resolution
- **v2.1**: Added debug mode and improved error handling
- **v2.0**: Complete rewrite with intelligent content detection
- **v1.5**: Added character encoding detection and raw content option
- **v1.0**: Initial implementation with basic HTML to Markdown conversion