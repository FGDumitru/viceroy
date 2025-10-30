<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Configuration\ConfigManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AdvanceSearchTool implements ToolInterface
{
    private Client $httpClient;
    private string $searchEndpoint;
    private $debugMode = false;
    private $filteringStrictness = 0.7; // 0.0 = very permissive, 1.0 = very strict

    public function __construct(?string $searchEndpoint = null, bool $debugMode = false, float $filteringStrictness = 0.7)
    {
        // Use configurable default search endpoint
        $defaultEndpoint = $_ENV['SEARCH_ENDPOINT'] ?? 'http://127.0.0.1:8080';
        $this->searchEndpoint = $searchEndpoint ?? $defaultEndpoint;
        $this->debugMode = $debugMode;
        $this->filteringStrictness = max(0.0, min(1.0, $filteringStrictness));
        $this->httpClient = new Client([
            'base_uri' => $this->searchEndpoint,
            'timeout' => 60.0, // Increased timeout
            'http_errors' => false
        ]);
    }

    public function getName(): string
    {
        return 'advance_search';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'advance_search',
                'description' => 'Performs an advanced search using SearXNG, fetches cleaned content for each result, and summarizes it. Use this tool for advanced searches and research tasks that require results aggregation usually.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query string to look up on the web.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return and summarize.',
                            'default' => 10
                        ],
                        'filtering_strictness' => [
                            'type' => 'number',
                            'description' => 'How strict to filter results (0.0 = very permissive, 1.0 = very strict).',
                            'default' => 0.7,
                            'minimum' => 0.0,
                            'maximum' => 1.0
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }

    public function validateArguments($arguments): bool
    {
        return isset($arguments['query']) && is_string($arguments['query']);
    }

    public function execute(array $arguments, $configuration): array
    {
        $startTime = microtime(true);
        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 3;
        $this->filteringStrictness = $arguments['filtering_strictness'] ?? $this->filteringStrictness;

        // Initialize statistics tracking
        $stats = [
            'total_processed' => 0,
            'successful_fetches' => 0,
            'failed_fetches' => 0,
            'successful_summaries' => 0,
            'failed_summaries' => 0,
            'listing_pages_found' => 0,
            'listing_links_processed' => 0,
            'filtered_out_listing_pages' => 0,
            'filtered_out_irrelevant' => 0,
            'fallback_included' => 0,
            'additional_searches' => 0,
            'errors' => []
        ];

        // Initialize metadata tracking
        $metadata = [
            'query' => $query,
            'limit' => $limit,
            'start_time' => $startTime,
            'search_time' => 0,
            'fetch_time' => 0,
            'summary_time' => 0,
            'total_time' => 0
        ];

        if ($this->debugMode) {
            error_log("AdvanceSearchTool: Starting execution with query: $query, limit: $limit");
        }

        // Get search endpoint from config or fallback
        $searchEndpoint = $configuration->getConfigKey('search_endpoint') ?: $this->searchEndpoint;

        $summarizedResults = [];
        $fetchStartTime = microtime(true);
        $searchOffset = 0;
        $maxSearchAttempts = 3; // Maximum number of additional search attempts
        
        // Main processing loop - continue until we have enough results or exhaust attempts
        while (count($summarizedResults) < $limit && $stats['additional_searches'] <= $maxSearchAttempts) {
            // Calculate how many results we still need
            $resultsNeeded = $limit - count($summarizedResults);
            
            // Fetch more search results if needed
            $searchStartTime = microtime(true);
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: Performing search with endpoint: $searchEndpoint, offset: $searchOffset, results needed: $resultsNeeded");
            }
            
            // Request more results than needed to account for filtering
            $searchLimit = max($resultsNeeded * 2, 10); // Get at least 10 results or double what we need
            $searchResults = $this->performSearch($query, $searchLimit, $searchEndpoint, $searchOffset);
            $metadata['search_time'] += microtime(true) - $searchStartTime;

            if ($searchResults['isError']) {
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: Search failed: " . json_encode($searchResults));
                }
                $stats['errors'][] = 'Search failed: ' . $searchResults['content'][0]['text'];
                break; // Exit loop on search failure
            }

            // Parse search results with better error handling
            $searchData = $this->parseJsonWithFallback($searchResults['content'][0]['text']);
            if (!$searchData || !isset($searchData['results'])) {
                $stats['errors'][] = 'Failed to parse search results';
                break; // Exit loop on parse failure
            }

            $results = $searchData['results'];
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: Found " . count($results) . " results in search batch " . ($stats['additional_searches'] + 1));
            }
            
            if (empty($results)) {
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: No more results available, stopping search");
                }
                break; // No more results available
            }

            // Process each result in this batch
            foreach ($results as $index => $result) {
                if (count($summarizedResults) >= $limit) {
                    break 2; // Exit both loops if we have enough results
                }

                $stats['total_processed']++;
                $url = $result['url'];
                $title = $result['title'];
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: Processing result " . ($index + 1) . " (batch " . ($stats['additional_searches'] + 1) . "): $title -> $url");
                }

                // Fetch and clean content with retry logic
                $fetchResult = $this->fetchCleanedContentWithRetry($url, 2);
                if ($fetchResult['success']) {
                    $stats['successful_fetches']++;
                    $cleanedContent = $fetchResult['content'];
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: Fetched content, length: " . strlen($cleanedContent));
                    }
                } else {
                    $stats['failed_fetches']++;
                    $stats['errors'][] = "Failed to fetch content from $url: " . $fetchResult['error'];
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: Failed to fetch content from $url: " . $fetchResult['error']);
                    }
                    continue;
                }

                // Summarize content with validation
                $summaryStartTime = microtime(true);
                $summaryData = $this->summarizeContent($cleanedContent, $query, $configuration);
                $summaryTime = microtime(true) - $summaryStartTime;

                if ($this->validateSummary($summaryData)) {
                    $stats['successful_summaries']++;
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: Summarized content in " . round($summaryTime, 2) . "s: " . strlen($summaryData['content']) . ' chars. Relevant: ' . ($summaryData['relevant_info_contained'] ? 'yes' : 'no') . ", Listing: " . ($summaryData['is_listing'] ? 'yes' : 'no'));
                    }
                    
                    // Additional check to filter out "No significant content found" even if it passes validation
                    if (strpos(strtolower($summaryData['content']), 'no significant content found') !== false) {
                        $stats['filtered_out_irrelevant']++;
                        if ($this->debugMode) {
                            error_log("AdvanceSearchTool: Filtered out result with 'No significant content found': $url");
                        }
                        continue;
                    }
                    
                    // Calculate dynamic filtering strictness based on how many results we have
                    $resultsRatio = count($summarizedResults) / $limit;
                    $dynamicStrictness = $this->calculateDynamicStrictness($this->filteringStrictness, $resultsRatio);
                    
                    // Add debug logging for filtering values
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: DEBUG - Results collected: " . count($summarizedResults) . "/$limit (" . round($resultsRatio * 100, 1) . "%)");
                        error_log("AdvanceSearchTool: DEBUG - Base filtering strictness: " . $this->filteringStrictness);
                        error_log("AdvanceSearchTool: DEBUG - Dynamic filtering strictness: " . $dynamicStrictness);
                        error_log("AdvanceSearchTool: DEBUG - is_listing: " . ($summaryData['is_listing'] ? 'true' : 'false'));
                        error_log("AdvanceSearchTool: DEBUG - relevant_info_contained: " . ($summaryData['relevant_info_contained'] ? 'true' : 'false'));
                    }

                    if ($summaryData['is_listing']) {
                        $stats['listing_pages_found']++;
                        
                        // Include listing page itself with dynamic filtering
                        $includeListingPage = $dynamicStrictness < 0.8 || $summaryData['relevant_info_contained'];
                        if ($includeListingPage && count($summarizedResults) < $limit) {
                            if ($this->debugMode) {
                                error_log("AdvanceSearchTool: Including listing page as result: $url");
                            }
                            $summarizedResults[] = [
                                'url' => $url,
                                'summary' => $summaryData['content'],
                                'source_type' => 'listing_page',
                                'title' => $title,
                                'fetch_time' => $fetchResult['fetch_time'],
                                'summary_time' => $summaryTime
                            ];
                        } elseif (!$includeListingPage) {
                            $stats['filtered_out_listing_pages']++;
                            if ($this->debugMode) {
                                error_log("AdvanceSearchTool: Filtered out listing page due to strictness: $url");
                            }
                        }
                        
                        // Get relevant links from LLM
                        $links = $this->getRelevantLinks($cleanedContent, $query, $configuration);
                        foreach ($links as $link) {
                            if (count($summarizedResults) >= $limit) {
                                break 2; // Exit both loops if we have enough results
                            }
                            $stats['listing_links_processed']++;
                            if ($this->debugMode) {
                                error_log("AdvanceSearchTool: Crawling relevant link from listing: $link");
                            }
                            
                            $linkFetchResult = $this->fetchCleanedContentWithRetry($link, 2);
                            if ($linkFetchResult['success']) {
                                $linkCleaned = $linkFetchResult['content'];
                                $linkSummary = $this->summarizeContent($linkCleaned, $query, $configuration);
                                if ($this->validateSummary($linkSummary)) {
                                    // Additional check to filter out "No significant content found" from listing links
                                    if (strpos(strtolower($linkSummary['content']), 'no significant content found') !== false) {
                                        $stats['filtered_out_irrelevant']++;
                                        if ($this->debugMode) {
                                            error_log("AdvanceSearchTool: Filtered out listing link with 'No significant content found': $link");
                                        }
                                        continue;
                                    }
                                    
                                    // More permissive filtering for listing links with dynamic strictness
                                    $includeLink = $linkSummary['relevant_info_contained'] ||
                                                 $dynamicStrictness < 0.5 ||
                                                 (count($summarizedResults) < ($limit * 0.8) && strlen($linkSummary['content']) > 200); // Increased length requirement
                                    
                                    if ($includeLink) {
                                        $summarizedResults[] = [
                                            'url' => $link,
                                            'summary' => $linkSummary['content'],
                                            'source_type' => 'listing_link',
                                            'parent_url' => $url,
                                            'fetch_time' => $linkFetchResult['fetch_time'],
                                            'summary_time' => $summaryTime
                                        ];
                                    } else {
                                        $stats['filtered_out_irrelevant']++;
                                        if ($this->debugMode) {
                                            error_log("AdvanceSearchTool: Filtered out irrelevant listing link: $link");
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // Non-listing page handling with improved fallback mechanism
                        $includeResult = $summaryData['relevant_info_contained'];
                        
                        // Improved fallback: include if we have too few results and content seems substantial
                        if (!$includeResult) {
                            $fallbackThreshold = max(0.8, $limit - count($summarizedResults)); // Higher threshold
                            if (count($summarizedResults) < $fallbackThreshold && strlen($summaryData['content']) > 300) { // Increased length requirement
                                $includeResult = $dynamicStrictness < 0.6;
                                if ($includeResult) {
                                    $stats['fallback_included']++;
                                    if ($this->debugMode) {
                                        error_log("AdvanceSearchTool: Fallback inclusion for potentially useful content: $url");
                                    }
                                }
                            }
                        }
                        
                        if ($includeResult) {
                            $summarizedResults[] = [
                                'url' => $url,
                                'summary' => $summaryData['content'],
                                'source_type' => 'search_result',
                                'title' => $title,
                                'fetch_time' => $fetchResult['fetch_time'],
                                'summary_time' => $summaryTime
                            ];
                        } else {
                            $stats['filtered_out_irrelevant']++;
                            if ($this->debugMode) {
                                error_log("AdvanceSearchTool: Filtered out irrelevant content: $url");
                            }
                        }
                    }
                } else {
                    $stats['failed_summaries']++;
                    $stats['errors'][] = "Invalid summary for $url";
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: Invalid summary for $url");
                    }
                }
            }

            // If we still don't have enough results, prepare for next search batch
            if (count($summarizedResults) < $limit) {
                $stats['additional_searches']++;
                $searchOffset += count($results); // Update offset for pagination
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: Still need " . ($limit - count($summarizedResults)) . " more results, performing additional search");
                }
            }
        }

        $metadata['fetch_time'] = microtime(true) - $fetchStartTime;
        $metadata['total_time'] = microtime(true) - $startTime;

        // Log final statistics
        $this->logFinalStatistics($stats, $metadata, $summarizedResults);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'query' => $query,
                        'total' => count($summarizedResults),
                        'results' => $summarizedResults,
                        'metadata' => $metadata,
                        'statistics' => $stats
                    ], JSON_PRETTY_PRINT)
                ]
            ],
            'isError' => false
        ];
    }

    private function performSearch(string $query, int $limit, string $searchEndpoint, int $offset = 0): array
    {
        try {
            if ($this->debugMode) {
                error_log("Attempting to connect to SearchNX with query: " . $query . " at " . $searchEndpoint . " (offset: $offset)");
            }

            $client = new Client([
                'base_uri' => $searchEndpoint,
                'timeout' => 30.0,
                'http_errors' => false
            ]);
            
            $queryParams = [
                'q' => $query,
                'limit' => $limit,
                'format' => 'json',
                'categories' => 'general',
                'language' => 'en'
            ];
            
            // Add offset parameter if supported by the search engine
            if ($offset > 0) {
                $queryParams['offset'] = $offset;
            }
            
            $searchResponse = $client->get('/search', [
                'query' => $queryParams,
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest'
                ]
            ]);

            $statusCode = $searchResponse->getStatusCode();
            $body = $searchResponse->getBody()->getContents();

            if ($this->debugMode) {
                error_log("SearchNX response status: " . $statusCode);
                error_log("SearchNX raw response body: " . var_export($body, TRUE));
            }

            if ($statusCode !== 200) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'SearchNX returned status ' . $statusCode . ': ' . $body
                        ]
                    ],
                    'isError' => true
                ];
            }

            $firstChar = substr(trim($body), 0, 1);
            if ($firstChar === '{' || $firstChar === '[') {
                $results = $this->parseJsonWithFallback($body);
                if (!$results) {
                    if ($this->debugMode) {
                        error_log("JSON decode error: " . json_last_error_msg());
                        error_log("Problematic JSON string: " . $body);
                    }
                    return [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Invalid JSON from SearchNX: ' . json_last_error_msg()
                            ]
                        ],
                        'isError' => true
                    ];
                }
            } else {
                $results = [
                    'results' => [
                        [
                            'title' => 'Search Result',
                            'content' => $body,
                            'url' => $searchEndpoint . '/search?q=' . urlencode($query),
                            'score' => 1.0,
                            'engine' => 'searxng',
                            'category' => 'general'
                        ]
                    ]
                ];
            }

            $structuredContent = [
                'query' => $query,
                'total' => count($results['results'] ?? []),
                'results' => array_map(function($result) {
                    return [
                       'title' => $result['title'] ?? '',
                       // 'content' => $result['content'] ?? '',
                        'url' => $result['url'] ?? '',
                        //'score' => $result['score'] ?? 0.0,
                        //'engine' => $result['engine'] ?? '',
                       // 'category' => $result['category'] ?? ''
                    ];
                }, $results['results'] ?? [])
            ];

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($structuredContent, JSON_PRETTY_PRINT)
                    ]
                ],
                'isError' => false
            ];
        } catch (GuzzleException $e) {
            if ($this->debugMode) {
                if ($this->debugMode) {
                    error_log("SearchNX connection error: " . $e->getMessage());
                }
            }
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'SearchNX connection error: ' . $e->getMessage()
                    ]
                ],
                'isError' => true
            ];
        } catch (\Exception $e) {
            if ($this->debugMode) {
                if ($this->debugMode) {
                    error_log("Unexpected error: " . $e->getMessage());
                }
            }
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Internal server error: ' . $e->getMessage()
                    ]
                ],
                'isError' => true
            ];
        }
    }

    private function fetchCleanedContent(string $url): ?string
    {
        try {
            $webpageTool = new \Viceroy\Tools\WebPageToMarkdownTool(null, null, $this->debugMode);
            $result = $webpageTool->execute(['url' => $url], null);
            if ($result['isError']) {
                return null;
            }
            return $result['content'][0]['text'];
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: Error fetching content from $url: " . $e->getMessage());
            }
            return null;
        }
    }

    private function summarizeContent(string $content, string $query, $configuration): array
    {
        // Create a new connection for summarization
        $summaryConnection = new \Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection($configuration);

        // Enhanced content validation before processing
        if (empty($content) || strlen(trim($content)) < 50) {
            return ['content' => 'No significant content found', 'relevant_info_contained' => false, 'is_listing' => false];
        }

        // Check for common empty content indicators
        $emptyContentIndicators = [
            'no content found',
            'page not found',
            '404',
            'access denied',
            'forbidden',
            'no results',
            'empty page',
            'nothing to display',
            'no significant content found'
        ];
        
        $contentLower = strtolower($content);
        foreach ($emptyContentIndicators as $indicator) {
            if (strpos($contentLower, $indicator) !== false) {
                return ['content' => 'No significant content found', 'relevant_info_contained' => false, 'is_listing' => false];
            }
        }

        // Limit content to avoid overflow but keep more for better summaries
        $maxLength = 150000; // Increased for comprehensive summaries
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        $prompt = "Create a comprehensive, detailed summary of this webpage content in 3-4 paragraphs. The summary should be thorough and include all important details, key information, and substantive content from the page. Focus on providing valuable insights rather than brief descriptions. Additionally, determine if the content contains relevant information about the query and if it's a listing page (aggregates or lists articles/news/other pages). Return a JSON object with 'content' (your comprehensive 3-4 paragraph summary), 'relevant_info_contained' (boolean), and 'is_listing' (boolean). Do not output anything else than the JSON object (no ```). Start directly with \"{...\". \n# Content: <content_body>" . $content . '</content_body> /nothink';

        try {
            $systemMessage = "You are a helpful assistant that creates comprehensive, detailed summaries of webpage content. Your summaries must be 3-4 paragraphs long and include all important details and substantive information. Always respond with valid JSON. Do not output anything else than the JSON object (no ```). Start directly with \"{...\". Be generous in determining relevance - when in doubt, mark content as relevant rather than irrelevant.";
            $summaryConnection->setSystemMessage($systemMessage);
            
            // Debug logging for LLM interaction
            if ($this->debugMode) {
                error_log("=== ADVANCE SEARCH TOOL DEBUG: LLM PROMPT (summarizeContent) ===");
                error_log("System Message: " . $systemMessage);
                error_log("User Prompt: " . $prompt);
                error_log("Prompt length: " . strlen($prompt) . " characters");
                error_log("=== END DEBUG PROMPT ===");
            }
            
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: About to query with prompt length: " . strlen($prompt));
            }
            $response = $summaryConnection->queryPost($prompt);
            $llmContent = $response->getLlmResponse();
            $thinkContent = $response->getThinkContent();
            $responseText = $llmContent ?: $thinkContent ?: '{"content": "No content", "relevant_info_contained": false, "is_listing": false}';
            
            // Debug logging for LLM response
            if ($this->debugMode) {
                error_log("=== ADVANCE SEARCH TOOL DEBUG: LLM RESPONSE (summarizeContent) ===");
                error_log("LLM Response: " . $llmContent);
                error_log("Think Content: " . $thinkContent);
                error_log("Final Response Text: " . $responseText);
                error_log("Response length: " . strlen($responseText) . " characters");
                error_log("=== END DEBUG RESPONSE ===");
            }
            
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: LLM response length: " . strlen($llmContent) . ", Think length: " . strlen($thinkContent));
            }
            
            // Parse JSON with fallback
            $parsed = $this->parseJsonWithFallback($responseText);
            if (!$parsed) {
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: JSON decode error: " . json_last_error_msg());
                }
                return ['content' => 'Invalid JSON response', 'relevant_info_contained' => false, 'is_listing' => false];
            }
            
            return [
                'content' => $parsed['content'] ?? 'No summary',
                'relevant_info_contained' => $parsed['relevant_info_contained'] ?? false,
                'is_listing' => $parsed['is_listing'] ?? false
            ];
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: Query failed: " . $e->getMessage());
            }
            return ['content' => "Summary failed: " . $e->getMessage(), 'relevant_info_contained' => false, 'is_listing' => false];
        }
    }

    private function extractLinks(string $markdown): array
    {
        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $markdown, $matches);
        return $matches[2]; // urls
    }

    private function getRelevantLinks(string $content, string $query, $configuration): array
    {
        // Create a new connection for link extraction
        $linkConnection = new \Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection($configuration);

        // Limit content to avoid overflow
        $maxLength = 100000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        $prompt = "This is a listing page. Extract the top 5 most relevant links related to the query '{$query}' from the content. Return a JSON object with 'links' as array of strings (urls). Content: " . $content . '/nothink';

        try {
            $systemMessage = "You are a helpful assistant that extracts relevant links from webpage content. Always respond with valid JSON.";
            $linkConnection->setSystemMessage($systemMessage);
            
            // Debug logging for LLM interaction
            if ($this->debugMode) {
                error_log("=== ADVANCE SEARCH TOOL DEBUG: LLM PROMPT (getRelevantLinks) ===");
                error_log("System Message: " . $systemMessage);
                error_log("User Prompt: " . $prompt);
                error_log("Prompt length: " . strlen($prompt) . " characters");
                error_log("=== END DEBUG PROMPT ===");
            }
            
            $response = $linkConnection->queryPost($prompt);
            $llmContent = $response->getLlmResponse();
            $thinkContent = $response->getThinkContent();
            $responseText = $llmContent ?: $thinkContent ?: '{"links": []}';
            $responseText = str_replace(['```json','```'], '', $responseText);
            
            // Debug logging for LLM response
            if ($this->debugMode) {
                error_log("=== ADVANCE SEARCH TOOL DEBUG: LLM RESPONSE (getRelevantLinks) ===");
                error_log("LLM Response: " . $llmContent);
                error_log("Think Content: " . $thinkContent);
                error_log("Final Response Text: " . $responseText);
                error_log("Response length: " . strlen($responseText) . " characters");
                error_log("=== END DEBUG RESPONSE ===");
            }
            
            // Parse JSON with fallback
            $parsed = $this->parseJsonWithFallback($responseText);
            if (!$parsed) {
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: JSON decode error in getRelevantLinks: " . json_last_error_msg());
                }
                return [];
            }
            return $parsed['links'] ?? [];
        } catch (\Exception $e) {
            if ($this->debugMode) {
                error_log("AdvanceSearchTool: getRelevantLinks failed: " . $e->getMessage());
            }
            return [];
        }
    }
    /**
     * Parse JSON with multiple fallback strategies
     */
    private function parseJsonWithFallback(string $jsonString): ?array
    {
        // First attempt: direct parsing
        $parsed = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsed;
        }

        // Second attempt: clean common JSON issues
        $cleanedJson = trim($jsonString);
        $cleanedJson = preg_replace('/^```json\s*/', '', $cleanedJson);
        $cleanedJson = preg_replace('/```\s*$/', '', $cleanedJson);
        $cleanedJson = trim($cleanedJson);

        $parsed = json_decode($cleanedJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsed;
        }

        // Third attempt: extract JSON from text using regex
        if (preg_match('/\{.*\}/s', $cleanedJson, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        // Fourth attempt: try to fix common syntax errors
        $fixedJson = str_replace(['"', "\n", "\r", "\t"], ['\"', '\n', '\r', '\t'], $cleanedJson);
        $fixedJson = preg_replace('/,\s*}/', '}', $fixedJson); // Remove trailing commas
        $fixedJson = preg_replace('/,\s*]/', ']', $fixedJson); // Remove trailing commas in arrays

        $parsed = json_decode($fixedJson, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $parsed;
        }

        return null;
    }

    /**
     * Fetch cleaned content with retry logic
     */
    private function fetchCleanedContentWithRetry(string $url, int $maxRetries = 2): array
    {
        $startTime = microtime(true);
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $webpageTool = new \Viceroy\Tools\WebPageToMarkdownTool(null, null, $this->debugMode);
                $result = $webpageTool->execute(['url' => $url], null);
                
                if (!$result['isError'] && isset($result['content'][0]['text'])) {
                    return [
                        'success' => true,
                        'content' => $result['content'][0]['text'],
                        'fetch_time' => microtime(true) - $startTime,
                        'attempt' => $attempt
                    ];
                }
                
                if ($attempt < $maxRetries) {
                    if ($this->debugMode) {
                        error_log("AdvanceSearchTool: Attempt $attempt failed for $url, retrying...");
                    }
                    usleep(500000); // Wait 0.5 seconds before retry
                }
            } catch (\Exception $e) {
                if ($this->debugMode) {
                    error_log("AdvanceSearchTool: Attempt $attempt failed for $url: " . $e->getMessage());
                }
                if ($attempt < $maxRetries) {
                    usleep(500000); // Wait 0.5 seconds before retry
                }
            }
        }

        return [
            'success' => false,
            'error' => "Failed after $maxRetries attempts",
            'fetch_time' => microtime(true) - $startTime,
            'attempt' => $maxRetries
        ];
    }

    /**
     * Validate that summary contains relevant information
     */
    private function validateSummary(array $summaryData): bool
    {
        if (!isset($summaryData['content']) || !is_string($summaryData['content'])) {
            return false;
        }

        // Check if summary is too short (likely failed) - increased minimum length for comprehensive summaries
        if (strlen($summaryData['content']) < 200) {
            return false;
        }

        // Enhanced check for empty/meaningless content indicators
        $emptyContentIndicators = [
            'no significant content found',
            'no content found',
            'page not found',
            '404',
            'access denied',
            'forbidden',
            'no results',
            'empty page',
            'nothing to display',
            'no meaningful content',
            'content unavailable',
            'insufficient content',
            'no substantial information',
            'no relevant information',
            'no summary available',
            'unable to summarize',
            'summary unavailable'
        ];
        
        $errorIndicators = [
            'error',
            'failed',
            'invalid',
            'unable',
            'cannot',
            'no content',
            'summary failed',
            'processing error',
            'generation failed'
        ];
        
        $summaryLower = strtolower($summaryData['content']);
        
        // Check for empty content indicators first (most important)
        foreach ($emptyContentIndicators as $indicator) {
            if (strpos($summaryLower, $indicator) !== false) {
                return false;
            }
        }
        
        // Check for other error indicators
        foreach ($errorIndicators as $indicator) {
            if (strpos($summaryLower, $indicator) !== false) {
                return false;
            }
        }

        // Validate boolean fields
        if (!isset($summaryData['relevant_info_contained']) || !is_bool($summaryData['relevant_info_contained'])) {
            return false;
        }

        if (!isset($summaryData['is_listing']) || !is_bool($summaryData['is_listing'])) {
            return false;
        }

        // Additional validation: ensure content has some substance
        // Check for minimum number of sentences (basic indicator of comprehensive summary)
        $sentences = preg_split('/[.!?]+/', $summaryData['content'], -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) < 3) {
            return false;
        }

        return true;
    }

    /**
     * Format error response with metadata and statistics
     */
    private function formatErrorResponse(array $errorResponse, array $stats, array $metadata): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'error' => $errorResponse['content'][0]['text'],
                        'metadata' => $metadata,
                        'statistics' => $stats
                    ], JSON_PRETTY_PRINT)
                ]
            ],
            'isError' => true
        ];
    }

    /**
     * Log final statistics and debug information
     */
    private function logFinalStatistics(array $stats, array $metadata, array $results): void
    {
        if ($this->debugMode) {
            error_log("AdvanceSearchTool: === EXECUTION SUMMARY ===");
            error_log("Query: " . $metadata['query']);
            error_log("Total time: " . round($metadata['total_time'], 2) . "s");
            error_log("Search time: " . round($metadata['search_time'], 2) . "s");
            error_log("Fetch time: " . round($metadata['fetch_time'], 2) . "s");
            error_log("Results processed: " . $stats['total_processed']);
            error_log("Successful fetches: " . $stats['successful_fetches']);
            error_log("Failed fetches: " . $stats['failed_fetches']);
            error_log("Successful summaries: " . $stats['successful_summaries']);
            error_log("Failed summaries: " . $stats['failed_summaries']);
            error_log("Listing pages found: " . $stats['listing_pages_found']);
            error_log("Listing links processed: " . $stats['listing_links_processed']);
            error_log("Filtered out listing pages: " . $stats['filtered_out_listing_pages']);
            error_log("Filtered out irrelevant content: " . $stats['filtered_out_irrelevant']);
            error_log("Fallback included results: " . $stats['fallback_included']);
            error_log("Additional searches performed: " . $stats['additional_searches']);
            error_log("Final results count: " . count($results));
            
            if (!empty($stats['errors'])) {
                error_log("Errors encountered: " . count($stats['errors']));
                foreach ($stats['errors'] as $index => $error) {
                    error_log("  Error " . ($index + 1) . ": " . $error);
                }
            }
            
            error_log("=== END SUMMARY ===");
        }
    }

    /**
     * Calculate dynamic filtering strictness based on results collected so far
     */
    private function calculateDynamicStrictness(float $baseStrictness, float $resultsRatio): float
    {
        // If we have very few results, be much more permissive
        if ($resultsRatio < 0.2) {
            return $baseStrictness * 0.3; // Very permissive when we have < 20% of needed results
        }
        // If we have less than half the results, be more permissive
        elseif ($resultsRatio < 0.5) {
            return $baseStrictness * 0.5; // Moderately permissive when we have < 50% of needed results
        }
        // If we have less than 80% of results, be slightly more permissive
        elseif ($resultsRatio < 0.8) {
            return $baseStrictness * 0.8; // Slightly permissive when we have < 80% of needed results
        }
        // If we're close to the limit, use the base strictness
        else {
            return $baseStrictness;
        }
    }
}
