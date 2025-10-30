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
                'description' => 'Use the tool "visit_webpage" functionality to visit the page and browse its contents. This gives you url browsing and parsing capabilities in real-time. It prioritizes extracting the main readable content and converts it to pure Markdown text. Optionally, return the raw webpage content.',
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

        return true;
    }

    public function execute(array $arguments, $configuration): array
    {

        var_dump($arguments);
        $url = $arguments['url'];
        $timeout = $arguments['timeout'] ?? 10;
        $raw = $arguments['raw'] ?? false;

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
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $body
                        ]
                    ],
                    'isError' => false
                ];
            }

            if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml')) {
                $finalUri = (string)$response->getHeaderLine('X-Guzzle-Effective-Uri') ?: $url;
                $markdownContent = $this->extractAndConvertMainContent($body, $finalUri);
            } elseif (str_contains($contentType, 'text/plain')) {
                $markdownContent = $body;
            } elseif (str_contains($contentType, 'application/json') || str_contains($contentType, 'application/xml')) {
                $markdownContent = "```" . (str_contains($contentType, 'json') ? 'json' : 'xml') . "\n"
                    . json_encode(json_decode($body, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    . "\n```";
            } else {
              $markdownContent = $body;
//                $markdownContent = $this->textConverter->convert($body);
            }

            $markdownContent = trim($markdownContent);

            if (empty($markdownContent) || preg_match('/^\s*Error:/', $markdownContent)) {
                $markdownContent = "No significant content found at the URL: {$url}. Content-Type: {$contentType}";
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $markdownContent
                    ]
                ],
                'isError' => false
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
