<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Plugins\MCPServerPlugin;

/**
 * MCPServerPlugin Integration Test Script
 * 
 * This script tests the MCPServerPlugin integration with OpenAICompatibleEndpointConnection
 * Demonstrates tool discovery, MCP method handling, and error scenarios
 */

echo "=== MCPServerPlugin Integration Test ===\n\n";

// Test 1: Instantiate OpenAICompatibleEndpointConnection and add MCPServerPlugin
echo "1. Testing MCPServerPlugin instantiation with tools directory...\n";

try {
    $connection = new OpenAICompatibleEndpointConnection();
    $toolsDirectory = __DIR__ . '/../../src/Tools';
    
    echo "   Tools directory: {$toolsDirectory}\n";
    
    if (!is_dir($toolsDirectory)) {
        throw new Exception("Tools directory does not exist: {$toolsDirectory}");
    }
    
    $mcpPlugin = new MCPServerPlugin($toolsDirectory);
    $connection->registerPlugin($mcpPlugin);
    
    echo "   ✅ MCPServerPlugin successfully instantiated and registered\n\n";
} catch (Exception $e) {
    echo "   ❌ Failed to instantiate MCPServerPlugin: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Test tool discovery functionality
echo "2. Testing tool discovery functionality...\n";

try {
    // Check if plugin can handle MCP methods
    $canHandleList = $mcpPlugin->canHandle('tools/list');
    $canHandleCall = $mcpPlugin->canHandle('tools/call');
    
    echo "   Plugin can handle 'tools/list': " . ($canHandleList ? '✅ Yes' : '❌ No') . "\n";
    echo "   Plugin can handle 'tools/call': " . ($canHandleCall ? '✅ Yes' : '❌ No') . "\n";
    
    if (!$canHandleList || !$canHandleCall) {
        throw new Exception("Plugin cannot handle required MCP methods");
    }
    
    echo "   ✅ Tool discovery functionality verified\n\n";
} catch (Exception $e) {
    echo "   ❌ Tool discovery test failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Test tools/list method handling
echo "3. Testing tools/list method handling...\n";

try {
    $toolsList = $connection->{'tools/list'}([]);
    
    echo "   Tools found: " . count($toolsList['tools'] ?? []) . "\n";
    
    if (!empty($toolsList['tools'])) {
        foreach ($toolsList['tools'] as $tool) {
            echo "     - {$tool['name']}: {$tool['description']}\n";
        }
    }
    
    if (empty($toolsList['tools'])) {
        throw new Exception("No tools discovered in the directory");
    }
    
    echo "   ✅ tools/list method successfully executed\n\n";
} catch (Exception $e) {
    echo "   ❌ tools/list test failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 4: Test tools/call method with valid parameters
echo "4. Testing tools/call method with valid parameters...\n";

try {
    // Use the first discovered tool for testing
    $firstTool = $toolsList['tools'][0] ?? null;
    
    if (!$firstTool) {
        throw new Exception("No tools available for testing");
    }
    
    $toolName = $firstTool['name'];
    echo "   Testing tool: {$toolName}\n";
    
    // Call the tool with valid parameters
    $result = $connection->{'tools/call'}([
        'name' => $toolName,
        'arguments' => ['message' => 'Test message from integration test']
    ]);
    
    echo "   Tool execution result:\n";
    if (isset($result['error'])) {
        echo "     ❌ Error: " . $result['error']['message'] . "\n";
        throw new Exception("Tool execution failed");
    } else {
        echo "     ✅ Success: " . print_r($result, true) . "\n";
    }
    
    echo "   ✅ tools/call method successfully executed\n\n";
} catch (Exception $e) {
    echo "   ❌ tools/call test failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Test error handling for invalid directories
echo "5. Testing error handling for invalid directories...\n";

try {
    $invalidDirectory = __DIR__ . '/nonexistent_tools_directory';
    $invalidPlugin = new MCPServerPlugin($invalidDirectory);
    
    // This should throw an exception during initialization
    $testConnection = new OpenAICompatibleEndpointConnection();
    $testConnection->registerPlugin($invalidPlugin);
    
    echo "   ❌ Expected exception for invalid directory was not thrown\n\n";
    exit(1);
} catch (InvalidArgumentException $e) {
    echo "   ✅ Correctly handled invalid directory: " . $e->getMessage() . "\n\n";
} catch (Exception $e) {
    echo "   ✅ Exception thrown for invalid directory: " . $e->getMessage() . "\n\n";
}

// Test 6: Test error handling for non-existent tools
echo "6. Testing error handling for non-existent tools...\n";

try {
    $result = $connection->{'tools/call'}([
        'name' => 'nonexistent_tool',
        'arguments' => ['message' => 'test']
    ]);
    
    if (isset($result['error'])) {
        echo "   ✅ Correctly handled non-existent tool: " . $result['error']['message'] . "\n\n";
    } else {
        echo "   ❌ Expected error for non-existent tool but got success\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✅ Exception thrown for non-existent tool: " . $e->getMessage() . "\n\n";
}

// Test 7: Test error handling for invalid tool call parameters
echo "7. Testing error handling for invalid tool call parameters...\n";

try {
    $result = $connection->{'tools/call'}([
        'name' => 'example', // Using example tool from src/Tools/
        'arguments' => 'invalid_arguments_format' // Should be array, not string
    ]);
    
    if (isset($result['error'])) {
        echo "   ✅ Correctly handled invalid arguments: " . $result['error']['message'] . "\n\n";
    } else {
        echo "   ❌ Expected error for invalid arguments but got success\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✅ Exception thrown for invalid arguments: " . $e->getMessage() . "\n\n";
}

echo "=== Test Summary ===\n";
echo "✅ All MCPServerPlugin integration tests completed successfully!\n";
echo "✅ Tool discovery works correctly\n";
echo "✅ MCP methods (tools/list and tools/call) are handled internally\n";
echo "✅ Proper error handling for invalid scenarios\n";
echo "✅ Integration with OpenAICompatibleEndpointConnection verified\n\n";

echo "The MCPServerPlugin successfully integrates with the OpenAICompatibleEndpointConnection\n";
echo "and provides MCP server functionality for tool discovery and execution.\n";
