# GetCurrentDateTimeTool Documentation

## Overview

The `GetCurrentDateTimeTool` is a simple yet essential Viceroy tool that provides current date and time information for any specified timezone. It supports all standard PHP timezone identifiers and returns properly formatted ISO 8601 datetime strings.

## Features

- **Timezone Support**: Supports all PHP timezone identifiers (e.g., UTC, Europe/London, America/New_York)
- **ISO 8601 Formatting**: Returns standardized datetime format for consistency across applications
- **Input Validation**: Validates timezone identifiers against PHP's built-in timezone list
- **Error Handling**: Provides clear error messages for invalid timezones
- **Default Timezone**: Defaults to UTC when no timezone is specified

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| timezone | string | No | Timezone identifier (e.g., UTC, Europe/London, America/New_York). Default: UTC |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "Current date and time in [timezone]: [ISO_8601_datetime]"
    }
  ],
  "isError": boolean
}
```

**Successful Response Example:**
```json
{
  "content": [
    {
      "type": "text",
      "text": "Current date and time in America/New_York: 2024-01-15T14:30:45+00:00"
    }
  ],
  "isError": false
}
```

**Error Response Example:**
```json
{
  "content": [
    {
      "type": "text",
      "text": "Error: Invalid timezone 'Invalid/Timezone' provided."
    }
  ],
  "isError": true
}
```

## Supported Timezones

The tool supports all timezone identifiers recognized by PHP's `timezone_identifiers_list()` function. Common examples include:

### Major Timezones
- `UTC` - Coordinated Universal Time
- `GMT` - Greenwich Mean Time
- `EST` - Eastern Standard Time
- `PST` - Pacific Standard Time

### Regional Timezones
- `America/New_York` - US Eastern Time
- `America/Los_Angeles` - US Pacific Time
- `Europe/London` - UK Time
- `Europe/Paris` - Central European Time
- `Asia/Tokyo` - Japan Standard Time
- `Australia/Sydney` - Australian Eastern Time

## Usage Examples

### Basic Usage (UTC Default)

```php
use Viceroy\Tools\GetCurrentDateTimeTool;

$tool = new GetCurrentDateTimeTool();
$result = $tool->execute([], null);

if (!$result['isError']) {
    echo $result['content'][0]['text'];
    // Output: "Current date and time in UTC: 2024-01-15T19:30:45+00:00"
} else {
    echo "Error: " . $result['content'][0]['text'];
}
```

### Specific Timezone Usage

```php
$tool = new GetCurrentDateTimeTool();
$result = $tool->execute([
    'timezone' => 'America/New_York'
], null);

if (!$result['isError']) {
    echo $result['content'][0]['text'];
    // Output: "Current date and time in America/New_York: 2024-01-15T14:30:45+00:00"
}
```

### Multiple Timezones

```php
$tool = new GetCurrentDateTimeTool();
$timezones = ['UTC', 'Europe/London', 'America/New_York', 'Asia/Tokyo'];

foreach ($timezones as $tz) {
    $result = $tool->execute(['timezone' => $tz], null);
    if (!$result['isError']) {
        echo $result['content'][0]['text'] . "\n";
    }
}
```

### Error Handling

```php
$tool = new GetCurrentDateTimeTool();

// Test invalid timezone
$result = $tool->execute(['timezone' => 'Invalid/Timezone'], null);
if ($result['isError']) {
    echo "Expected error: " . $result['content'][0]['text'];
}

// Test with valid timezone
$result = $tool->execute(['timezone' => 'Europe/Berlin'], null);
if (!$result['isError']) {
    echo "Current time in Berlin: " . $result['content'][0]['text'];
}
```

### List Available Timezones

```php
// Get all available timezones
$availableTimezones = timezone_identifiers_list();
echo "Available timezones:\n";
foreach ($availableTimezones as $tz) {
    echo "- $tz\n";
}
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new GetCurrentDateTimeTool());

// Execute the tool
$result = $toolManager->executeTool('get_current_datetime', [
    'timezone' => 'Europe/Paris'
], $configuration);

if (!$result['isError']) {
    $datetime = $result['content'][0]['text'];
    // Process the datetime information
}
```

### Using with MCP (Model Context Protocol)

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'get_current_datetime',
    'arguments' => [
        'timezone' => 'Asia/Tokyo'
    ]
]);
```

### Integration in LLM Prompts

The tool is particularly useful in LLM contexts for:

```php
// Example LLM system prompt that uses the tool
$systemPrompt = "You are a helpful assistant. Always check the current time when scheduling events or discussing time-sensitive topics.";

$connection->setSystemMessage($systemPrompt);
$connection->addToolDefinition(new GetCurrentDateTimeTool());

// LLM can now call the tool to get current time for scheduling
$response = $connection->query("What time should I schedule a meeting for tomorrow?");
```

## Technical Implementation

### Dependencies

- **PHP DateTime**: Uses PHP's built-in DateTime and DateTimeZone classes
- **PHP timezone functions**: Relies on `timezone_identifiers_list()` for validation
- **No external dependencies**: Pure PHP implementation

### Timezone Validation

The tool validates timezones using PHP's built-in functions:

```php
// Validation process
if (!in_array($timezone, timezone_identifiers_list())) {
    return false; // Invalid timezone
}
```

### ISO 8601 Formatting

The tool uses PHP's DateTime `format('c')` method to generate ISO 8601 compliant timestamps:

```php
$dateTime = new DateTime('now', new DateTimeZone($timezone));
$formattedDateTime = $dateTime->format('c'); // ISO 8601 format
```

### Error Handling Strategy

1. **Input Validation**: Checks timezone against valid identifiers list
2. **Exception Handling**: Catches DateTime creation exceptions
3. **Clear Error Messages**: Provides descriptive error messages for invalid inputs

## Troubleshooting

### Common Issues

1. **Invalid Timezone Identifier**:
   ```
   Error: Invalid timezone 'InvalidZone' provided.
   ```
   **Solution**: Use valid timezone identifiers from `timezone_identifiers_list()`

2. **Empty Timezone Parameter**:
   ```
   Error: Invalid timezone '' provided.
   ```
   **Solution**: Provide a valid timezone or omit parameter to use UTC default

3. **Case Sensitivity Issues**:
   ```
   Error: Invalid timezone 'utc' provided.
   ```
   **Solution**: Use correct case (e.g., 'UTC' instead of 'utc')

### Validating Timezones

Before using a timezone, you can validate it:

```php
function isValidTimezone($timezone) {
    return in_array($timezone, timezone_identifiers_list());
}

if (isValidTimezone('America/New_York')) {
    echo "Timezone is valid";
} else {
    echo "Timezone is invalid";
}
```

### Common Timezone Mistakes

| Incorrect | Correct | Reason |
|------------|----------|---------|
| 'EST' | 'America/New_York' | EST is ambiguous, use specific timezone |
| 'PST' | 'America/Los_Angeles' | PST doesn't handle DST properly |
| 'CET' | 'Europe/Paris' | CET doesn't handle DST properly |
| 'gmt' | 'GMT' | Timezone identifiers are case-sensitive |

## Best Practices

1. **Timezone Selection**:
   - Use specific timezone identifiers (e.g., 'America/New_York') instead of abbreviations
   - Consider user location when defaulting timezones
   - Store user preferences for timezone selection

2. **Error Handling**:
   - Always check the `isError` flag in responses
   - Provide fallback to UTC when timezone validation fails
   - Display user-friendly error messages

3. **Display Formatting**:
   - Parse ISO 8601 format for user-friendly display
   - Consider user locale when formatting dates
   - Show timezone information clearly to users

4. **Integration Patterns**:
   ```php
   // Best practice for timezone handling
   function getCurrentTimeForUser($userTimezone = null) {
       $tool = new GetCurrentDateTimeTool();
       $timezone = $userTimezone ?: 'UTC';
       
       $result = $tool->execute(['timezone' => $timezone], null);
       
       if ($result['isError']) {
           // Fallback to UTC
           $result = $tool->execute(['timezone' => 'UTC'], null);
       }
       
       return $result;
   }
   ```

5. **Performance Considerations**:
   - The tool is lightweight with minimal performance impact
   - Consider caching timezone validation results
   - Reuse tool instance for multiple calls

## Advanced Usage

### Custom Time Formatting

While the tool returns ISO 8601 format, you can parse and reformat:

```php
$tool = new GetCurrentDateTimeTool();
$result = $tool->execute(['timezone' => 'America/New_York'], null);

if (!$result['isError']) {
    // Extract ISO datetime from response
    $text = $result['content'][0]['text'];
    preg_match('/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})/', $text, $matches);
    
    if (isset($matches[1])) {
        $dateTime = new DateTime($matches[1]);
        
        // Custom formatting
        echo $dateTime->format('l, F j, Y, g:i A'); // Monday, January 15, 2024, 2:30 PM
        echo $dateTime->format('Y-m-d H:i:s'); // 2024-01-15 14:30:45
    }
}
```

### Timezone Conversion Helper

```php
class TimezoneHelper {
    private $tool;
    
    public function __construct() {
        $this->tool = new GetCurrentDateTimeTool();
    }
    
    public function getTimeInTimezone($timezone) {
        $result = $this->tool->execute(['timezone' => $timezone], null);
        
        if ($result['isError']) {
            return null;
        }
        
        // Extract datetime from response
        $text = $result['content'][0]['text'];
        preg_match('/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})/', $text, $matches);
        
        return isset($matches[1]) ? new DateTime($matches[1]) : null;
    }
    
    public function compareTimezones($tz1, $tz2) {
        $time1 = $this->getTimeInTimezone($tz1);
        $time2 = $this->getTimeInTimezone($tz2);
        
        if ($time1 && $time2) {
            return [
                'timezone1' => $tz1,
                'time1' => $time1->format('Y-m-d H:i:s'),
                'timezone2' => $tz2,
                'time2' => $time2->format('Y-m-d H:i:s'),
                'difference' => $time1->getTimestamp() - $time2->getTimestamp()
            ];
        }
        
        return null;
    }
}

// Usage
$helper = new TimezoneHelper();
$comparison = $helper->compareTimezones('America/New_York', 'Asia/Tokyo');
```

## Version History

- **v1.1**: Enhanced timezone validation and improved error messages
- **v1.0**: Initial implementation with basic timezone support