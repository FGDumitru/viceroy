<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SearchTool implements ToolInterface
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
        return 'search';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Performs a search query using SearXNG and returns relevant web results.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query string to look up on the web.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return.',
                            'default' => 10
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }


    public function validateArguments( $arguments): bool
    {
        return isset($arguments['query']) && is_string($arguments['query']);
    }

    public function execute(array $arguments, $configuration): array
    {
        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 5;


        
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

            // Try to determine the response format
            $firstChar = substr(trim($body), 0, 1);
            if ($firstChar === '{' || $firstChar === '[') {
                $results = json_decode($body, true);
                if ($this->debugMode &&  json_last_error() !== JSON_ERROR_NONE) {
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
                // If not JSON, try to create a simple result structure
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

            // Transform results to MCP 2025-06-18 format
            $structuredContent = [
                'query' => $query,
                'total' => count($results['results'] ?? []),
                'results' => array_map(function($result) {
                    return [
                        'title' => $result['title'] ?? '',
                        'content' => $result['content'] ?? '',
                        'url' => $result['url'] ?? '',
                        'score' => $result['score'] ?? 0.0,
                        'engine' => $result['engine'] ?? '',
                        'category' => $result['category'] ?? ''
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
}
