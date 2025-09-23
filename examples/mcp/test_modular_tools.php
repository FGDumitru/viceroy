<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\ToolManager;

// Test the modular tool system
echo "Testing Modular Tool System\n";
echo "===========================\n\n";

$toolManager = new ToolManager();
$toolManager->discoverTools();

echo "Discovered tools:\n";
$toolNames = $toolManager->getToolNames();
foreach ($toolNames as $toolName) {
    echo "- {$toolName}\n";
}

echo "\nTool Definitions:\n";
$definitions = $toolManager->getToolDefinitions();
echo json_encode($definitions, JSON_PRETTY_PRINT);

echo "\n\nTesting Example Tool:\n";
try {
    $result = $toolManager->executeTool('example', ['message' => 'Test message']);
    echo "Success: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTesting Search Tool (if available):\n";
if ($toolManager->hasTool('search')) {
    try {
        $result = $toolManager->executeTool('search', ['query' => 'test']);
        echo "Search tool executed successfully\n";
    } catch (Exception $e) {
        echo "Search tool error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Search tool not available (requires modular SearchTool)\n";
}

echo "\nMigration Status:\n";
$migrationResults = $toolManager->migrateLegacyTools();
echo json_encode($migrationResults, JSON_PRETTY_PRINT);
