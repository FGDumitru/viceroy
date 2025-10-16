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

    public function __construct(string $searchEndpoint = 'http://192.168.0.121:8080', bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->searchEndpoint = $searchEndpoint;
        $this->httpClient = new Client([
            'base_uri' => $this->searchEndpoint,
            'timeout' => 30.0,
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
                'description' => 'Performs an advanced search using SearXNG, fetches cleaned content for each result, and summarizes it.',
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
                            'default' => 3
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
        error_log("AdvanceSearchTool: Starting execution with query: " . ($arguments['query'] ?? 'none') . ", limit: " . ($arguments['limit'] ?? 3));

        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 3;

        // First, perform the search
        error_log("AdvanceSearchTool: Performing search");
        $searchResults = $this->performSearch($query, $limit);
        if ($searchResults['isError']) {
            error_log("AdvanceSearchTool: Search failed: " . json_encode($searchResults));
            return $searchResults;
        }

        $results = json_decode($searchResults['content'][0]['text'], true)['results'] ?? [];
        error_log("AdvanceSearchTool: Found " . count($results) . " results");

        $summarizedResults = [];
        foreach ($results as $index => $result) {
            if (count($summarizedResults) >= $limit) {
                break;
            }
            $url = $result['url'];
//            $title = $result['title'];
            error_log("AdvanceSearchTool: Processing result " . ($index + 1) . ": " .  $url);

            // Fetch and clean content
            $cleanedContent = $this->fetchCleanedContent($url);
            if ($cleanedContent === null) {
                error_log("AdvanceSearchTool: Failed to fetch content from " . $url);
                continue;
            }
            error_log("AdvanceSearchTool: Fetched content, length: " . strlen($cleanedContent));
            // Summarize
            $summaryData = $this->summarizeContent($cleanedContent, $query, $configuration);
            error_log("AdvanceSearchTool: Summarized content: " . substr($summaryData['content'], 0, 100) . "... Relevant: " . ($summaryData['relevant_info_contained'] ? 'yes' : 'no'));

            if ($summaryData['relevant_info_contained']) {
                $summarizedResults[] = [
                    //'title' => $title,
                    'url' => $url,
                    'summary' => $summaryData['content']
                ];
            }
        }

        error_log("AdvanceSearchTool: Execution completed, returning " . count($summarizedResults) . " summarized results");

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'query' => $query,
                        'total' => count($summarizedResults),
                        'results' => $summarizedResults
                    ], JSON_PRETTY_PRINT)
                ]
            ],
            'isError' => false
        ];
    }

    private function performSearch(string $query, int $limit): array
    {
        try {
            if ($this->debugMode) {
                error_log("Attempting to connect to SearchNX with query: " . $query);
            }

            $searchResponse = $this->httpClient->get('/search', [
                'query' => [
                    'q' => $query,
                    'limit' => $limit,
                    'format' => 'json',
                    'categories' => 'general',
                    'language' => 'en'
                ],
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
                $results = json_decode($body, true);
                if ($this->debugMode && json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    error_log("Problematic JSON string: " . $body);
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
                            'url' => $this->searchEndpoint . '/search?q=' . urlencode($query),
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
                       // 'title' => $result['title'] ?? '',
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
                error_log("SearchNX connection error: " . $e->getMessage());
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
                error_log("Unexpected error: " . $e->getMessage());
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
            $webpageTool = new \Viceroy\Tools\WebPageToMarkdownTool();
            $result = $webpageTool->execute(['url' => $url], null);
            if ($result['isError']) {
                return null;
            }
            return $result['content'][0]['text'];
        } catch (\Exception $e) {
            error_log("AdvanceSearchTool: Error fetching content from $url: " . $e->getMessage());
            return null;
        }
    }

    private function summarizeContent(string $content, string $query, $configuration): array
    {
        // Create a new connection for summarization
        $summaryConnection = new \Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection($configuration);

        // Limit content to avoid overflow
        $maxLength = 100000; // Shorter to avoid token limits
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }

        $prompt = "Summarize this webpage content and determine if it contains relevant information about the query (Note: Pages that aggregate or list articles/news/other pages are not relevant, a page should be concerned with a single subject): '{$query}'. Return a JSON object with 'content' (summary string) and 'relevant_info_contained' (boolean). Content: " . $content . '/nothink';

        try {
            $summaryConnection->setSystemMessage("You are a helpful assistant that summarizes webpage content concisely and determines relevance. Always respond with valid JSON.");
            error_log("AdvanceSearchTool: About to query with prompt length: " . strlen($prompt));
            $response = $summaryConnection->queryPost($prompt);
            $llmContent = $response->getLlmResponse();
            $thinkContent = $response->getThinkContent();
            error_log("AdvanceSearchTool: LLM response length: " . strlen($llmContent) . ", Think length: " . strlen($thinkContent));
            $responseText = $llmContent ?: $thinkContent ?: '{"content": "No content", "relevant_info_contained": false}';
            $parsed = json_decode($responseText, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AdvanceSearchTool: JSON decode error: " . json_last_error_msg());
                return ['content' => 'Invalid JSON response', 'relevant_info_contained' => false];
            }
            return [
                'content' => $parsed['content'] ?? 'No summary',
                'relevant_info_contained' => $parsed['relevant_info_contained'] ?? false
            ];
        } catch (\Exception $e) {
            error_log("AdvanceSearchTool: Query failed: " . $e->getMessage());
            return ['content' => "Summary failed: " . $e->getMessage(), 'relevant_info_contained' => false];
        }
    }
}