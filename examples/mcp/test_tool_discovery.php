<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Simulate the tool discovery logic from mcp_simple_server.php
$toolsDirectory = __DIR__ . '/tools';
$tools = [];

if (is_dir($toolsDirectory)) {
    $toolFiles = glob($toolsDirectory . '/*.php');
    echo "Found tool files: " . json_encode($toolFiles) . "\n";
    
    foreach ($toolFiles as $toolFile) {
        $toolName = basename($toolFile, '.php');
        $tools[$toolName] = $toolFile;
        echo "Tool file: $toolFile, tool name: $toolName\n";
        
        if (file_exists($toolFile)) {
            require_once $toolFile;
            
            // Check if the tool class exists (class name should match filename)
            $className = ucfirst($toolName);
            echo "Checking for class: $className\n";
            
            if (class_exists($className)) {
                echo "Class $className exists\n";
                $toolInstance = new $className();
                $definition = $toolInstance->getDefinition();
                echo "Tool definition: " . json_encode($definition, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "Class $className does not exist\n";
            }
        }
    }
} else {
    echo "Tools directory not found: $toolsDirectory\n";
}

echo "Final tools array: " . json_encode($tools, JSON_PRETTY_PRINT) . "\n";
