<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * A simplified MCP server that autodiscovers tools from individual files in a directory
 * Implements the MCP 2025-06-18 specification with simplified tool discovery
 */

header('Content-Type: application/json');

// CLI arguments parsing
$options = getopt('h:p:', ['host:', 'port:']);
$host = $options['h'] ?? $options['host'] ?? '0.0.0.0';
$port = $options['p'] ?? $options['port'] ?? '8111';

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    echo "Starting simplified MCP server on {$host}:{$port}\n";
    echo "Use Ctrl+C to stop the server\n";

    // Add signal handling for graceful shutdown
    pcntl_signal(SIGTERM, function() {
        exit(0);
    });

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

// Auto-discover tools from the tools directory
$toolsDirectory = __DIR__ . '/tools';
$tools = [];

if (is_dir($toolsDirectory)) {
    $toolFiles = glob($toolsDirectory . '/*.php');
    foreach ($toolFiles as $toolFile) {
        $toolName = basename($toolFile, '.php');
        $tools[$toolName] = $toolFile;
    }
}

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
        if (!isset($capabilities) || !is_array($capabilities) || empty(array_filter(array_keys($capabilities), 'is_string'))) {
            $response['error'] = [
                'code' => -32602,
                'message' => "'capabilities' parameter is required and must be an object (associative array with string keys)"
            ];
            echo json_encode($response);
            exit;
        }

        // Extract client information (must be an object)
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
                'name' => 'MCP Simple Server',
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
        $toolDefinitions = [];
        
        foreach ($tools as $toolName => $toolFile) {
            if (file_exists($toolFile)) {
                require_once $toolFile;
                
                // Check if the tool class exists (class name should match filename)
                $className = ucfirst($toolName);
                if (class_exists($className)) {
                    $toolInstance = new $className();
                    $toolDefinitions[] = $toolInstance->getDefinition();
                }
            }
        }

        $response['result'] = [
            'tools' => $toolDefinitions,
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

        // print_r($tools);

        $toolName = $request['params']['name'];
        $toolFile = $tools[$toolName] ?? null;

        if (!$toolFile || !file_exists($toolFile)) {
            $response['error'] = [
                'code' => -32601,
                'message' => 'Unknown tool: ' . $toolName
            ];
            break;
        }

        require_once $toolFile;
        
        // Check if the tool class exists (class name should match filename)
        $className = ucfirst($toolName);
        if (!class_exists($className)) {
            $response['error'] = [
                'code' => -32601,
                'message' => 'Tool class not found: ' . $className
            ];
            break;
        }

        $toolInstance = new $className();
        $args = $request['params']['arguments'];

        // Validate arguments
        if (!$toolInstance->validateArguments($args)) {
            $response['error'] = [
                'code' => -32602,
                'message' => 'Invalid arguments for tool: ' . $toolName
            ];
            break;
        }

        try {
            $result = $toolInstance->execute($args);
            $response['result'] = $result;
        } catch (Exception $e) {
            error_log("Tool execution error: " . $e->getMessage());
            $response['result'] = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Tool execution error: ' . $e->getMessage()
                    ]
                ],
                'isError' => true
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
