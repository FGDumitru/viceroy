<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\ToolManager;

/**
 * A modular MCP server example that implements the MCP 2025-06-18 specification
 * with dynamic tool discovery and registration
 */

header('Content-Type: application/json');

// CLI arguments parsing
$options = getopt('h:p:', ['host:', 'port:']);
$host = $options['h'] ?? $options['host'] ?? '0.0.0.0';
$port = $options['p'] ?? $options['port'] ?? '8111';

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    echo "Starting modular MCP server on {$host}:{$port}\n";
    echo "Use Ctrl+C to stop the server\n";

    // Add signal handling for graceful shutdown
    pcntl_signal(SIGTERM, function() {
        // Cleanup temporary files if needed
        exit(0);
    });

    // Remove the following lines:
    $command = sprintf('php -S %s:%d %s', $host, $port, __FILE__);
    system($command);
    exit;
}

// Initialize Tool Manager
$toolManager = new ToolManager();
$toolManager->discoverTools();

// For backward compatibility, register the legacy search tool if no modular tools found
if (!$toolManager->hasTool('search')) {
    $toolManager->registerLegacyTool('search', [
        'name' => 'search',
        'title' => 'Web Search Provider',
        'description' => 'Performs a search query using SearXNG',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query string'
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return',
                    'default' => 5
                ]
            ],
            'required' => ['query']
        ],
        'outputSchema' => [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => ['text']
                            ],
                            'text' => [
                                'type' => 'string'
                            ]
                        ],
                        'required' => ['type', 'text']
                    ]
                ],
                'structuredContent' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string'
                        ],
                        'total' => [
                            'type' => 'integer'
                        ],
                        'results' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'content' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                    'score' => ['type' => 'number'],
                                    'engine' => ['type' => 'string'],
                                    'category' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ],
                'isError' => [
                    'type' => 'boolean'
                ]
            ],
            'required' => ['content', 'isError']
        ]
    ], function(array $arguments) {
        // Legacy search execution logic
        $query = $arguments['query'];
        $limit = $arguments['limit'] ?? 5;

        try {
            error_log("Attempting to connect to SearchNX with query: " . $query);

            $searchClient = new GuzzleHttp\Client([
                'base_uri' => 'http://192.168.0.121:8080',
                'timeout' => 10.0,
                'http_errors' => false
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
                if (json_last_error() !== JSON_ERROR_NONE) {
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
                            'url' => 'http://192.168.0.121:8080/search?q=' . urlencode($query),
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
                'structuredContent' => $structuredContent,
                'isError' => false
            ];
        } catch (GuzzleException $e) {
            error_log("SearchNX connection error: " . $e->getMessage());
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'SearchNX connection error: ' . $e->getMessage()
                    ]
                ],
                'isError' => true
            ];
        } catch (Exception $e) {
            error_log("Unexpected error: " . $e->getMessage());
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
    });
}

// Initialize response array
$response = [
    'jsonrpc' => '2.0',
    'id' => null,
];

// Read the raw input
$input = file_get_contents('php://input');
error_log("Received raw input: " . $input);
$request = json_decode($input, true);
error_log("Decoded request: " . var_export($request, true));

// Validate request
if (!is_array($request)) {
    $response['error'] = [
        'code' => -32600,
        'message' => 'Invalid Request'
    ];
    echo json_encode($response);
    exit;
}

if (!isset($request['method']) || !is_string($request['method'])) {
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
    case 'initialize':
        // Validate protocolVersion parameter
        $protocolVersion = $request['params']['protocolVersion'] ?? null;
        if (!is_string($protocolVersion)) {
            $response['error'] = [
                'code' => -32602,
                'message' => "'protocolVersion' parameter is required and must be a string"
            ];
            echo json_encode($response);
            exit;
        }

        // Validate capabilities parameter (must be an object, not an array)
        $capabilities = $request['params']['capabilities'] ?? null;
        $capabilities['tools'][] = 'search';
        if (!isset($capabilities) || !is_array($capabilities) || empty(array_filter(array_keys($capabilities), 'is_string'))) {
            $response['error'] = [
                'code' => -32602,
                'message' => "'capabilities' parameter is required and must be an object (associative array with string keys)"
            ];
            echo json_encode($response);
            exit;
        }

        // Extract client information (must be an object)
        // Check for both 'clientInfo' and 'client' for backward compatibility
        $clientInfo = $request['params']['clientInfo'] ?? ($request['params']['client'] ?? null);
        if (!isset($clientInfo) || !is_array($clientInfo) || empty(array_filter(array_keys($clientInfo), 'is_string'))) {
            $response['error'] = [
                'code' => -32602,
                'message' => "'clientInfo' or 'client' parameter is required and must be an object (associative array with string keys)"
            ];
            echo json_encode($response);
            exit;
        }

        $response['result'] = [
            'server' => [
                'name' => 'Modular MCP Server Example',
                'version' => '1.0.0',
                'protocolVersion' => $protocolVersion
            ],
            'features' => [
                'workspace/configuration',
                'tools/list',
                'supports_streaming' => true
            ],
            'capabilities' => $capabilities,
            'clientInfo' => $clientInfo
        ];
        break;

    case 'workspace/configuration':
        $response['result'] = [
            'capabilities' => [
                'tools' => [
                    'listChanged' => false
                ]
            ]
        ];
        break;

    case 'tools/list':
        $response['result'] = [
            'tools' => $toolManager->getToolDefinitions(),
            'nextCursor' => null
        ];
        break;

    case 'tools/call':
        if (!isset($request['params']['name']) || !isset($request['params']['arguments'])) {
            $response['error'] = [
                'code' => -32602,
                'message' => 'Invalid parameters: requires name and arguments'
            ];
            break;
        }

        $toolName = $request['params']['name'];
        $arguments = $request['params']['arguments'];

        if (!$toolManager->hasTool($toolName)) {
            $response['error'] = [
                'code' => -32601,
                'message' => 'Unknown tool: ' . $toolName
            ];
            break;
        }

        try {
            $result = $toolManager->executeTool($toolName, $arguments);
            $response['result'] = $result;
        } catch (\InvalidArgumentException $e) {
            $response['error'] = [
                'code' => -32602,
                'message' => 'Invalid arguments: ' . $e->getMessage()
            ];
        } catch (\RuntimeException $e) {
            $response['error'] = [
                'code' => -32601,
                'message' => 'Tool execution failed: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            $response['error'] = [
                'code' => -32000,
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
