<?php

namespace Viceroy\Tools;

use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use League\HTMLToMarkdown\Converter\TextConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Http\Message\UriInterface;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\Interfaces\ToolInterface;

class WebPageToMarkdownTool implements ToolInterface
{
    private Client $httpClient;
    private HtmlConverter $htmlConverter;
    private TextConverter $textConverter;
    private bool $debugMode = false;

    public function __construct(
        ?Client $httpClient = null,
        ?HtmlConverter $htmlConverter = null,
        bool $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 5.0,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Viceroy-WebPageToMarkdownTool/2.2', // Version bump
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'DNT' => '1',
            ]
        ]);

        $this->htmlConverter = $htmlConverter ?? new HtmlConverter([
            // CRITICAL CHANGE: Set strip_tags to true. This tells the converter to
            // remove any HTML tags it doesn't have a specific Markdown conversion for.
            'strip_tags' => true,
            // We manage node removal with DOMDocument, so this can remain empty.
            'remove_nodes' => '',
            'hard_break' => true,
            'strip_image_alt' => false, // Keep alt text for context
            'default_attributes' => [
                'img' => ['src', 'alt'],
                'a' => ['href'],
            ],
            // Add custom converters if needed, though 'strip_tags' true should handle most issues
            // 'converters' => [
            //     new \League\HTMLToMarkdown\Converter\ListItemConverter(),
            // ],
        ]);

        $this->textConverter = new TextConverter();
    }

    public function getName(): string
    {
        return 'visit_webpage';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'visit_webpage',
                'description' => 'Use the tool "visit_webpage" functionality to visit the page and browse its contents. This gives you url browsing and parsing capabilities in real-time. It prioritizes extracting the main readable content and converts it to pure Markdown text. Optionally, return the raw webpage content or generate a summary. Do not request a summary for JSON API calls, or for XML/RSS feeds.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The URL of the webpage to visit'
                        ],
                        'timeout' => [
                            'type' => 'number',
                            'description' => 'Connection timeout in seconds (default: 5)',
                            'default' => 10
                        ],
                        'raw' => [
                            'type' => 'boolean',
                            'description' => 'If true, return the raw webpage content instead of converted Markdown. This is useful for API calls that may return JSON objects',
                            'default' => false
                        ],
                        'summary' => [
                            'type' => 'boolean',
                            'description' => 'Whether to generate a summary of the webpage content. This should be the default behavior for standard webpages (unless the user specifically called for not a summary), but should be false for JSON API calls or XML/RSS feeds.',
                            'default' => false
                        ],
                        'summary_length' => [
                            'type' => 'integer',
                            'description' => 'Detail level for summary from 1-10 (1-2: very brief, 3-4: brief, 5-6: standard, 7-8: detailed, 9-10: very detailed)',
                            'default' => 5,
                            'minimum' => 1,
                            'maximum' => 10
                        ],
                        'summary_focus' => [
                            'type' => 'string',
                            'description' => 'Specific focus area for the summary (optional, e.g., "main points", "technical details", "conclusions")'
                        ]
                    ],
                    'required' => ['url']
                ]
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        if (!isset($arguments['url']) || !is_string($arguments['url'])) {
            return false;
        }

        $url = $arguments['url'];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
            return false;
        }

        if (isset($arguments['timeout']) && (!is_numeric($arguments['timeout']) || $arguments['timeout'] <= 0)) {
            return false;
        }

        if (isset($arguments['raw']) && !is_bool($arguments['raw'])) {
            return false;
        }

        if (isset($arguments['summary']) && !is_bool($arguments['summary'])) {
            return false;
        }

        if (isset($arguments['summary_length']) && (!is_int($arguments['summary_length']) || $arguments['summary_length'] < 1 || $arguments['summary_length'] > 10)) {
            return false;
        }

        if (isset($arguments['summary_focus']) && !is_string($arguments['summary_focus'])) {
            return false;
        }

        return true;
    }

    public function execute(array $arguments, $configuration): array
    {
        $url = $arguments['url'];
        $timeout = $arguments['timeout'] ?? 10;
        $raw = $arguments['raw'] ?? false;
        $summary = $arguments['summary'] ?? false;
        $summaryLength = $arguments['summary_length'] ?? 5;
        $summaryFocus = $arguments['summary_focus'] ?? null;

        if (!$this->validateArguments($arguments)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: Invalid URL provided. Please provide a valid HTTP or HTTPS URL.'
                    ]
                ],
                'isError' => true
            ];
        }

        try {
            $client = new Client([
                'timeout' => $timeout,
                'allow_redirects' => true,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Viceroy-WebPageToMarkdownTool/2.2', // Version bump
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'DNT' => '1',
                ]
            ]);
            $response = $client->get($url);

            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaderLine('content-type');
            $body = (string)$response->getBody();

            if ($statusCode >= 400) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Error: Failed to fetch webpage content. HTTP Status Code: {$statusCode}"
                        ]
                    ],
                    'isError' => true
                ];
            }

            if ($this->debugMode) {
                echo PHP_EOL . "Got Guzzle body of length " . strlen($body)  . PHP_EOL;
            }
            if ($raw) {
                $content = $body;
            } else {
                if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml')) {
                    $finalUri = (string)$response->getHeaderLine('X-Guzzle-Effective-Uri') ?: $url;
                    $content = $this->extractAndConvertMainContent($body, $finalUri);
                } elseif (str_contains($contentType, 'text/plain')) {
                    $content = $body;
                } elseif (str_contains($contentType, 'application/json') || str_contains($contentType, 'application/xml')) {
                    $content = "```" . (str_contains($contentType, 'json') ? 'json' : 'xml') . "\n"
                        . json_encode(json_decode($body, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        . "\n```";
                } else {
                  $content = $body;
    //                $content = $this->textConverter->convert($body);
                }
            }

            $content = trim($content);

            if (empty($content) || preg_match('/^\s*Error:/', $content)) {
                $content = "No significant content found at the URL: {$url}. Content-Type: {$contentType}";
            }

            // Generate summary if requested
            $summaryData = null;
            if ($summary && !$raw) {
                $summaryData = $this->generateSummary($content, $summaryLength, $summaryFocus, $configuration);
            }

            // Build response content
            $responseContent = [];
            
            // Add main content
            $responseContent[] = [
                'type' => 'text',
                'text' => $content
            ];

            // Add summary if generated
            if ($summaryData && !$summaryData['error']) {
                $responseContent[] = [
                    'type' => 'text',
                    'text' => "\n\n--- Summary ---\n" . $summaryData['content']
                ];

                if (!empty($summaryData['key_points'])) {
                    $keyPointsText = "\n\nKey Points:\n" . implode("\n", array_map(fn($point) => "• " . $point, $summaryData['key_points']));
                    $responseContent[] = [
                        'type' => 'text',
                        'text' => $keyPointsText
                    ];
                }
            } elseif ($summaryData && $summaryData['error']) {
                $responseContent[] = [
                    'type' => 'text',
                    'text' => "\n\n--- Summary Error ---\n" . $summaryData['content']
                ];
            }

            return [
                'content' => $responseContent,
                'isError' => false,
                'summary' => $summaryData
            ];
        } catch (GuzzleException $e) {
            if ($this->debugMode) {
                error_log("WebPageToMarkdownTool Guzzle error for URL {$url}: " . $e->getMessage());
            }
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: Network or HTTP issue when fetching webpage - ' . $e->getMessage()
                    ]
                ],
                'isError' => true
            ];
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("WebPageToMarkdownTool unexpected error for URL {$url}: " . $e->getMessage());
            }
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: An unexpected error occurred during processing - ' . $e->getMessage()
                    ]
                ],
                'isError' => true
            ];
        }
    }

   /**
   * Generates a summary of the provided content using LLM
   *
   * @param string $content The content to summarize
   * @param int $summaryLength Detail level from 1-10
   * @param string|null $summaryFocus Optional focus area for the summary
   * @param $configuration The configuration object for LLM connection
   * @return array Summary data with content and metadata
   */
private function generateSummary(string $content, int $summaryLength, ?string $summaryFocus, $configuration): array
{
    try {
        // Create a new connection for summarization
        $summaryConnection = new OpenAICompatibleEndpointConnection($configuration);

        // Determine summary scope based on length parameter
        $scopeDescription = $this->getSummaryScopeDescription($summaryLength);
        
        // Build the focus part of the prompt
        $focusPart = $summaryFocus ? " Focus particularly on: {$summaryFocus}." : '';

        $prompt = "Please analyze the following webpage content and create a summary with the following characteristics:\n\n" .
                 "Detail Level: {$summaryLength}/10 ({$scopeDescription})\n" .
                 "Content to summarize:\n\n---\n{$content}\n---\n\n" .
                 "Create a {$scopeDescription} summary that captures the essence and key information.{$focusPart}\n\n" .
                 "Return a JSON object with the following structure:\n" .
                 "{\n" .
                 "  \"content\": \"Your summary here\",\n" .
                 "  \"word_count\": approximate word count of your summary,\n" .
                 "  \"key_points\": [\"key point 1\", \"key point 2\", \"key point 3\"]\n" .
                 "}\n\n" .
                 "Ensure your summary is well-structured, informative, and matches the requested detail level. " .
                 "Always respond with valid JSON only, no markdown formatting or extra text.";

        $systemMessage = "You are an expert content analyzer and summarizer. Create clear, concise, and accurate summaries based on the specified detail level. Always respond with valid JSON objects only, without any markdown formatting or additional text.";
        $summaryConnection->setSystemMessage($systemMessage);

        if ($this->debugMode) {
            error_log("=== WEBPAGE TO MARKDOWN TOOL DEBUG: LLM PROMPT (generateSummary) ===");
            error_log("System Message: " . $systemMessage);
            error_log("Prompt: " . $prompt);
            error_log("Content length: " . strlen($content) . " characters");
        }

        $response = $summaryConnection->queryPost($prompt);
        $llmContent = $response->getLlmResponse();

        if ($this->debugMode) {
            error_log("=== WEBPAGE TO MARKDOWN TOOL DEBUG: LLM RESPONSE (generateSummary) ===");
            error_log("LLM Response: " . $llmContent);
        }

        // Parse JSON response
        $parsed = json_decode($llmContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON if it's wrapped in code blocks
            $llmContent = preg_replace('/```json\s*|\s*```/', '', $llmContent);
            $parsed = json_decode(trim($llmContent), true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'content' => 'Error: Failed to parse summary response',
                'word_count' => 0,
                'key_points' => [],
                'error' => 'JSON parsing failed: ' . json_last_error_msg()
            ];
        }

        return [
            'content' => $parsed['content'] ?? 'No summary available',
            'word_count' => $parsed['word_count'] ?? 0,
            'key_points' => $parsed['key_points'] ?? [],
            'error' => null
        ];

    } catch (\Exception $e) {
        if ($this->debugMode) {
            error_log("WebPageToMarkdownTool summary generation error: " . $e->getMessage());
        }
        
        return [
            'content' => 'Error: Failed to generate summary - ' . $e->getMessage(),
            'word_count' => 0,
            'key_points' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
   * Get description for summary scope based on length parameter
   *
   * @param int $summaryLength Detail level from 1-10
   * @return string Description of the summary scope
   */
private function getSummaryScopeDescription(int $summaryLength): string
{
    if ($summaryLength <= 2) {
        return 'very brief (1-2 sentences)';
    } elseif ($summaryLength <= 4) {
        return 'brief (1 paragraph)';
    } elseif ($summaryLength <= 6) {
        return 'standard (2-3 paragraphs)';
    } elseif ($summaryLength <= 8) {
        return 'detailed (multiple paragraphs with key points)';
    } else {
        return 'very detailed (comprehensive overview with structure)';
    }
}

   /**
   * Extracts main content from HTML, resolves relative URLs, and converts to Markdown.
   *
   * @param string $html Input HTML content.
   * @param string $baseUrl The base URL of the page (after redirects).
   * @return string Markdown representation of the main content.
   */
private function extractAndConvertMainContent(string $html, string $baseUrl): string
{
    if (empty($html)) {
        return '';
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    $charset = $this->detectCharset($html);
    $encodedHtml = $this->ensureHtmlEncoding($html, $charset);
    $dom->loadHTML($encodedHtml, LIBXML_NOCDATA | LIBXML_COMPACT);

    $xpath = new DOMXPath($dom);

    // STEP 1: Remove obvious non-content elements
    $unwantedSelectors = [
        '//comment()',
        '//script', '//style', '//nav', '//footer', '//header', '//aside',
        '//iframe', '//noscript', '//form', '//ins', '//del', '//button',
        '//input', '//select', '//textarea', '//svg', '//canvas',
        '//*[contains(@class, "ad") or contains(@id, "ad")]',
        '//*[contains(@class, "cookie") or contains(@id, "cookie")]',
        '//*[contains(@class, "modal") or contains(@id, "modal")]',
        '//*[contains(@class, "dialog") or contains(@id, "dialog")]',
        '//*[contains(@class, "sidebar") or contains(@id, "sidebar")]',
        '//*[contains(@class, "menu") or contains(@id, "menu")]',
        '//*[contains(@class, "social") or contains(@id, "social")]',
        '//*[contains(@class, "related") or contains(@id, "related")]',
        '//*[contains(@class, "share") or contains(@id, "share")]',
        '//*[contains(@class, "hero") or contains(@id, "hero")]',
        '//*[contains(@class, "promo") or contains(@id, "promo")]',
        '//*[contains(@class, "skip-link") or contains(@id, "skip-link")]'
    ];
    foreach ($unwantedSelectors as $selector) {
        foreach ($xpath->query($selector) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    // STEP 2: Remove invisible or JS-based links
    foreach ($xpath->query('//a') as $a) {
        $href = $a->getAttribute('href');
        if (stripos($href, 'javascript:') === 0 || trim($a->textContent) === '') {
            $a->parentNode?->removeChild($a);
        } else {
            // Clean up tracking parameters
            $cleanHref = preg_replace('/([?&])(utm_[^&]+)/i', '', $href);
            $a->setAttribute('href', $cleanHref);
        }
    }

    // STEP 3: Detect main content
    $mainContentNode = $this->findMainContentNode($dom, $xpath)
        ?? $dom->getElementsByTagName('body')->item(0);

    if (!$mainContentNode) {
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        return '';
    }

    // STEP 4: Resolve relative URLs
    $this->resolveRelativeUrls($mainContentNode, new Uri($baseUrl));

    // STEP 5: Extract clean inner HTML
    $innerHtmlFragment = '';
    foreach ($mainContentNode->childNodes as $childNode) {
        $innerHtmlFragment .= $dom->saveHTML($childNode);
    }

    // STEP 6: Convert to Markdown
    $markdown = $this->htmlConverter->convert($innerHtmlFragment);

    // STEP 7: Post-process Markdown to remove any remaining junk
    $markdown = $this->cleanerMarkdown($markdown);

    libxml_clear_errors();
    libxml_use_internal_errors(false);

    return $markdown;
}

/**
 * Remove leftover HTML or JS artifacts from Markdown.
 */
private function cleanerMarkdown(string $markdown): string
{
    // Remove any remaining HTML tags
    $markdown = preg_replace('/<[^>]+>/', '', $markdown);

    // Remove markdown links with javascript or empty URLs
    $markdown = preg_replace('/\[[^\]]*\]\((?:javascript:[^)]+|#|)\)/i', '', $markdown);

    // Remove redundant whitespace, slashes, and escape characters
    $markdown = preg_replace('/\\\\+/', '', $markdown);
    $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);
    $markdown = trim($markdown);

    return $markdown;
}


    private function detectCharset(string $html): ?string
    {
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'>\s\/]+)/i', $html, $matches)) {
            $charset = trim($matches[1]);
            if (!empty($charset)) {
                return strtoupper($charset);
            }
        }
        return null;
    }

    private function ensureHtmlEncoding(string $html, ?string $detectedCharset): string
    {
        $originalCharset = $detectedCharset ?: 'UTF-8';

        if ($originalCharset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            try {
                return mb_convert_encoding($html, 'UTF-8', $originalCharset);
            } catch (\ValueError $e) {
                // Invalid charset, return as is
                return $html;
            }
        }

        return $html;
    }

    /**
     * Finds the most likely main content DOMNode using a heuristic approach.
     *
     * @param DOMDocument $dom
     * @param DOMXPath $xpath
     * @return DOMNode|null
     */
    private function findMainContentNode(DOMDocument $dom, DOMXPath $xpath): ?DOMNode
    {
        // Priority 1: Semantic HTML5 elements
        $semanticSelectors = [
            "//main",
            "//article",
            "//div[contains(@itemprop, 'articleBody')]",
            "//div[contains(@role, 'main')]",
        ];
        foreach ($semanticSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                if ($nodes->item(0)->hasChildNodes()) {
                    return $nodes->item(0);
                }
            }
        }

        // Priority 2: Common IDs/classes for main content
        // Expanded selectors for general content areas, common on listing sites like Reddit
        $commonSelectors = [
            "//div[@id='content']",
            "//div[@class='content']",
            "//div[@id='main']",
            "//div[@class='main']",
            "//div[@id='primary']",
            "//div[@class='primary']",
            "//div[@id='article']",
            "//div[@class='article-body']",
            "//div[@class='post-content']",
            "//div[@class='entry-content']",
            "//div[@class='page-content']",
            "//div[contains(@class, 'main-content')]",
            "//div[contains(@class, 'article-content')]",
            "//section[contains(@class, 'main-content')]",
            "//section[contains(@class, 'article-content')]",
            "//div[@id='siteTable']", // Specific for Reddit's main content area
            "//div[contains(@class, 'sitetable')]", // Also for Reddit
        ];
        foreach ($commonSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                if ($this->isPotentiallyMainContent($node)) {
                    return $node;
                }
            }
        }

        // Priority 3: Simplified Readability-like heuristic (general content blocks)
        $candidateNodes = $xpath->query("//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //li | //blockquote | //div | //article | //main | //section");

        $bestCandidate = null;
        $maxScore = 0;

        foreach ($candidateNodes as $node) {
            $score = $this->scoreContentNode($node);
            if ($score > $maxScore) {
                $maxScore = $score;
                $bestCandidate = $node;
            }
        }

        if ($bestCandidate && $maxScore > 5) {
            return $bestCandidate;
        }

        // Priority 4: If all else fails, take the body (after initial cleaning)
        return $dom->getElementsByTagName('body')->item(0);
    }

    /**
     * Scores a DOMNode based on its text content and other characteristics to identify main content.
     */
    private function scoreContentNode(DOMNode $node): float
    {
        $textLength = strlen(trim($node->textContent));
        if ($textLength === 0) {
            return 0;
        }

        $score = 1.0;

        if (in_array(strtolower($node->nodeName), ['article', 'main', 'section'])) {
            $score += 20;
        } elseif (in_array(strtolower($node->nodeName), ['div', 'p'])) {
            $score += 5;
        }

        $score += min($textLength / 100, 10);

        $linkCount = 0;
        foreach ($node->getElementsByTagName('a') as $link) {
            $linkCount++;
        }
        if ($linkCount > 0) {
            $linkTextLength = 0;
            foreach ($node->getElementsByTagName('a') as $link) {
                $linkTextLength += strlen(trim($link->textContent));
            }
            if ($textLength > 0 && $linkTextLength / $textLength > 0.3) {
                $score -= 5;
            }
        }

        $headingCount = 0;
        foreach ($node->getElementsByTagName('h1') as $h) $headingCount++;
        foreach ($node->getElementsByTagName('h2') as $h) $headingCount++;
        foreach ($node->getElementsByTagName('h3') as $h) $headingCount++;
        if ($headingCount > 0 && $textLength > 200) {
            $score += 5;
        }

        $id = $node->attributes->getNamedItem('id');
        $class = $node->attributes->getNamedItem('class');

        if ($id && preg_match('/(content|article|post|body|main)/i', $id->nodeValue)) {
            $score += 25;
        }
        if ($class && preg_match('/(content|article|post|body|main)/i', $class->nodeValue)) {
            $score += 15;
        }
        if ($class && preg_match('/(comment|meta|footer|sidebar|nav|ad|promo)/i', $class->nodeValue)) {
            $score -= 10;
        }
        if ($id && preg_match('/(comment|meta|footer|sidebar|nav|ad|promo)/i', $id->nodeValue)) {
            $score -= 15;
        }

        return max(0, $score);
    }

    private function isPotentiallyMainContent(DOMNode $node): bool
    {
        $textLength = strlen(trim($node->textContent));
        if ($textLength < 100) {
            return false;
        }
        return true;
    }

    /**
     * Resolves relative URLs within a DOMNode to absolute URLs.
     *
     * @param DOMNode $node The node to process (e.g., the main content node).
     * @param UriInterface $baseUrl The base URL for resolving relative links.
     */
    private function resolveRelativeUrls(DOMNode $node, UriInterface $baseUrl): void
    {
        $xpath = new DOMXPath($node->ownerDocument);

        foreach ($xpath->query('.//img', $node) as $img) {
            if ($img->hasAttribute('src')) {
                $originalSrc = $img->getAttribute('src');
                if ($this->isRelativeUrl($originalSrc)) {
                    $absoluteUrl = (string)UriResolver::resolve($baseUrl, new Uri($originalSrc));
                    $img->setAttribute('src', $absoluteUrl);
                }
            }
            if ($img->hasAttribute('data-src') && !$img->hasAttribute('src')) {
                $originalSrc = $img->getAttribute('data-src');
                if ($this->isRelativeUrl($originalSrc)) {
                    $absoluteUrl = (string)UriResolver::resolve($baseUrl, new Uri($originalSrc));
                    $img->setAttribute('src', $absoluteUrl);
                } else {
                    $img->setAttribute('src', $originalSrc);
                }
            }
        }

        foreach ($xpath->query('.//a', $node) as $a) {
            if ($a->hasAttribute('href')) {
                $originalHref = $a->getAttribute('href');
                if ($this->isRelativeUrl($originalHref)) {
                    $absoluteUrl = (string)UriResolver::resolve($baseUrl, new Uri($originalHref));
                    $a->setAttribute('href', $absoluteUrl);
                }
            }
        }
    }

    private function isRelativeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        return !isset($parsed['scheme']) && !isset($parsed['host']);
    }

    /**
     * Clean up the markdown content.
     *
     * @param string $markdown Input markdown
     * @return string Cleaned markdown
     */
    private function cleanMarkdown(string $markdown): string
    {
        // Remove excessive blank lines, but preserve formatting for code blocks
        $markdown = preg_replace("/(\n[ \t]*){2,}/S", "\n\n", $markdown);

        // Trim leading/trailing whitespace from the entire content
        $markdown = trim($markdown);

        // Trim each line individually (rtrim to preserve potential leading indents for code blocks)
        $lines = array_map('rtrim', explode("\n", $markdown));
        $markdown = implode("\n", $lines);

        // Remove redundant horizontal rules (more than one blank line between them)
        $markdown = preg_replace("/\n\n(-{3,}|\*{3,}|\_{3,})\n\n(-{3,}|\*{3,}|\_{3,})\n\n/", "\n\n$1\n\n", $markdown);

        return $markdown;
    }
}
