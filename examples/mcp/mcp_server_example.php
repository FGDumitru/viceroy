<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * A simple MCP server example that demonstrates basic functionality
 * with built-in web server capabilities
 */

// CLI arguments parsing
$options = getopt('h:p:', ['host:', 'port:']);
$host = $options['h'] ?? $options['host'] ?? 'localhost';
$port = $options['p'] ?? $options['port'] ?? '8111';

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    echo "Starting MCP server on {$host}:{$port}\n";
    echo "Use Ctrl+C to stop the server\n";
    $command = sprintf('php -S %s:%d %s', $host, $port, __FILE__);
    system($command);
    exit;
}

// Initialize response array
$response = [
    'jsonrpc' => '2.0',
    'id' => null,
];

// Read the raw input
$input = file_get_contents('php://input');
$request = json_decode($input, true);

// Validate request
if (!$request || !isset($request['method'])) {
    $response['error'] = [
        'code' => -32600,
        'message' => 'Invalid Request'
    ];
    echo json_encode($response);
    exit;
}

// Set the response ID from the request
$response['id'] = $request['id'] ?? null;

// Handle different methods
switch ($request['method']) {
    case 'workspace/configuration':
        $response['result'] = [
            'capabilities' => [
                'searchProvider' => [
                    'methods' => [
                        'search/query' => [
                            'description' => 'Performs a search query using SearXNG',
                            'parameters' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'The search query string'
                                ],
                                'limit' => [
                                    'type' => 'integer',
                                    'description' => 'Maximum number of results to return',
                                    'default' => 5,
                                    'optional' => true
                                ]
                            ],
                            'returns' => [
                                'type' => 'object',
                                'properties' => [
                                    'query' => [
                                        'type' => 'string',
                                        'description' => 'The original search query'
                                    ],
                                    'total' => [
                                        'type' => 'integer',
                                        'description' => 'Total number of results found'
                                    ],
                                    'results' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'title' => [
                                                    'type' => 'string',
                                                    'description' => 'Title of the result'
                                                ],
                                                'content' => [
                                                    'type' => 'string',
                                                    'description' => 'Result content or description'
                                                ],
                                                'url' => [
                                                    'type' => 'string',
                                                    'description' => 'URL of the result'
                                                ],
                                                'score' => [
                                                    'type' => 'number',
                                                    'description' => 'Relevance score'
                                                ],
                                                'engine' => [
                                                    'type' => 'string',
                                                    'description' => 'Search engine that provided the result'
                                                ],
                                                'category' => [
                                                    'type' => 'string',
                                                    'description' => 'Category of the result'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        break;

    case 'search/query':
        // Query SearchNX instance
        if (!isset($request['params']['query'])) {
            $response['error'] = [
                'code' => -32602,
                'message' => 'Missing required parameter: query'
            ];
            break;
        }

        $query = $request['params']['query'];
        $limit = $request['params']['limit'] ?? 5;
        
        try {
            error_log("Attempting to connect to SearchNX with query: " . $query);
            
            $searchClient = new Client([
                'base_uri' => 'http://192.168.0.121:8080',
                'timeout' => 10.0,
                'http_errors' => false // Don't throw exceptions on 4xx/5xx responses
            ]);
            
            error_log("Making search request to SearchNX...");
            $searchResponse = $searchClient->get('/search', [
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
            
            error_log("SearchNX response status: " . $statusCode);
            error_log("SearchNX raw response body: " . var_export($body, true));
            
            if ($statusCode !== 200) {
                $response['error'] = [
                    'code' => -32603,
                    'message' => 'SearchNX returned status ' . $statusCode . ': ' . $body
                ];
                break;
            }

            // Try to determine the response format
            $firstChar = substr(trim($body), 0, 1);
            if ($firstChar === '{' || $firstChar === '[') {
                $results = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    error_log("Problematic JSON string: " . $body);
                    $response['error'] = [
                        'code' => -32603,
                        'message' => 'Invalid JSON from SearchNX: ' . json_last_error_msg() . '. Raw response: ' . substr($body, 0, 100) . '...'
                    ];
                    break;
                }
            } else {
                // If not JSON, try to create a simple result structure
                $results = [
                    'hits' => [
                        [
                            'title' => 'Search Result',
                            'content' => $body,
                            'url' => 'http://192.168.0.121:8080/search?q=' . urlencode($query),
                            'score' => 1.0
                        ]
                    ]
                ];
            }
            
            // Transform SearXNG results to our format
            $response['result'] = [
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
        } catch (GuzzleException $e) {
            error_log("SearchNX connection error: " . $e->getMessage());
            $response['error'] = [
                'code' => -32603,
                'message' => 'SearchNX connection error: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Unexpected error: " . $e->getMessage());
            $response['error'] = [
                'code' => -32603,
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
        }
        break;

    default:
        $response['error'] = [
            'code' => -32601,
            'message' => 'Method not found'
        ];
        break;
}

// Send response
header('Content-Type: application/json');
echo json_encode($response);