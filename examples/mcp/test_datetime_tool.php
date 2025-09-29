<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Viceroy\Tools\GetCurrentDateTimeTool;

// Create the tool instance
$tool = new GetCurrentDateTimeTool();

// Test 1: Default timezone (UTC)
echo "Test 1: Default timezone (UTC)\n";
$result1 = $tool->execute([]);
print_r($result1);
echo "\n";

// Test 2: Valid timezone (Europe/Bucharest)
echo "Test 2: Valid timezone (Europe/Bucharest)\n";
$result2 = $tool->execute(['timezone' => 'Europe/Bucharest']);
print_r($result2);
echo "\n";

// Test 3: Another valid timezone (America/New_York)
echo "Test 3: Valid timezone (America/New_York)\n";
$result3 = $tool->execute(['timezone' => 'America/New_York']);
print_r($result3);
echo "\n";

// Test 4: Invalid timezone
echo "Test 4: Invalid timezone (Invalid/Timezone)\n";
$result4 = $tool->execute(['timezone' => 'Invalid/Timezone']);
print_r($result4);
echo "\n";

// Test 5: Validate arguments directly
echo "Test 5: Validate arguments\n";
$valid1 = $tool->validateArguments(['timezone' => 'UTC']);
$valid2 = $tool->validateArguments(['timezone' => 'Invalid/Timezone']);
echo "UTC validation: " . ($valid1 ? 'true' : 'false') . "\n";
echo "Invalid validation: " . ($valid2 ? 'true' : 'false') . "\n";

// Test 6: Get tool definition
echo "Test 6: Tool definition\n";
$definition = $tool->getDefinition();
print_r($definition);
echo "\n";

// Test 7: Get tool name
echo "Test 7: Tool name\n";
$name = $tool->getName();
echo "Tool name: " . $name . "\n";
