<?php


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
                'completionProvider' => true,
                'textDocumentSync' => 1, // Full sync
                'workspaceSymbolProvider' => true
            ]
        ];
        break;

    case 'textDocument/completion':
        // Simple completion example
        $response['result'] = [
            'items' => [
                [
                    'label' => 'example',
                    'kind' => 1, // Text
                    'detail' => 'An example completion',
                    'documentation' => 'This is a sample completion item'
                ]
            ]
        ];
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