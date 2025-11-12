# SendTelegramMessageTool Documentation

## Overview

The `SendTelegramMessageTool` is a Viceroy tool designed to send messages via Telegram using the `telegramMe` command-line utility. It provides automatic message splitting functionality to handle messages that exceed Telegram's character limit.

## Features

- **Automatic Message Splitting**: Messages longer than 4000 characters are automatically split into multiple parts
- **Message Numbering**: Split messages are prefixed with `[current/total]` numbering (e.g., `[1/3]`, `[2/3]`, `[3/3]`)
- **Error Handling**: Comprehensive error handling for invalid inputs and command execution failures
- **Detailed Response**: Returns metadata about the operation, including split information and success/failure counts
- **Shell Safety**: Proper shell escaping to prevent injection attacks

## Parameters

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| message | string | Yes | The message content to send via Telegram. If longer than 4000 characters, it will be automatically split into multiple messages. |

### Response Format

The tool returns a structured response with the following format:

```json
{
  "content": [
    {
      "type": "text",
      "text": "Status message with operation details"
    }
  ],
  "isError": boolean,
  "metadata": {
    "total_chunks": number,
    "successful_sends": number,
    "failed_sends": number,
    "was_split": boolean
  }
}
```

### Metadata Fields

| Field | Type | Description |
|-------|------|-------------|
| total_chunks | number | Total number of message parts that were sent |
| successful_sends | number | Number of message parts that were sent successfully |
| failed_sends | number | Number of message parts that failed to send |
| was_split | boolean | Indicates whether the original message was split into multiple parts |

## Message Splitting Behavior

### Splitting Threshold

- Messages with **4000 characters or less**: Sent as a single message
- Messages with **more than 4000 characters**: Split into multiple 4000-character chunks
- The 4000-character limit provides a buffer from Telegram's 4096 character limit

### Splitting Process

1. The original message is divided into chunks of 4000 characters each
2. Each chunk is prefixed with numbering in the format `[current/total]`
3. Chunks are sent sequentially via the `telegramMe` command
4. The response includes metadata about the splitting operation

### Numbering Format

When a message is split, each part is prefixed with a `[current/total]` indicator:

```
[1/3] This is the first part of a long message...
[2/3] This is the second part of a long message...
[3/3] This is the third and final part of a long message...
```

## Usage Examples

### Basic Usage (Single Message)

```php
use Viceroy\Tools\SendTelegramMessageTool;

$tool = new SendTelegramMessageTool();
$result = $tool->execute([
    'message' => 'Hello, this is a test message!'
], null);

if (!$result['isError']) {
    echo "Message sent successfully!\n";
    echo "Was split: " . ($result['metadata']['was_split'] ? 'Yes' : 'No') . "\n";
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Long Message (Automatic Splitting)

```php
$longMessage = str_repeat("This is a very long message. ", 200);
$result = $tool->execute(['message' => $longMessage], null);

if (!$result['isError']) {
    echo "Message sent successfully!\n";
    echo "Total chunks: " . $result['metadata']['total_chunks'] . "\n";
    echo "Successful sends: " . $result['metadata']['successful_sends'] . "\n";
    
    if ($result['metadata']['was_split']) {
        echo "Message was split into multiple parts with numbering.\n";
    }
} else {
    echo "Error: " . $result['content'][0]['text'] . "\n";
}
```

### Error Handling

```php
// Empty message
$result = $tool->execute(['message' => ''], null);
if ($result['isError']) {
    echo "Expected error for empty message: " . $result['content'][0]['text'] . "\n";
}

// Missing message parameter
$result = $tool->execute([], null);
if ($result['isError']) {
    echo "Expected error for missing parameter: " . $result['content'][0]['text'] . "\n";
}
```

## Integration with Viceroy

### Using with ToolManager

```php
use Viceroy\Core\ToolManager;

$toolManager = new ToolManager();
$toolManager->registerTool(new SendTelegramMessageTool());

// Execute the tool
$result = $toolManager->executeTool('send_telegram_message', [
    'message' => 'Your message here'
], $configuration);
```

### Using with MCP (Model Context Protocol)

The tool automatically integrates with the MCP system when registered:

```php
// The tool will be automatically discovered and available via MCP
$mcpServer = new MCPServerPlugin(['/path/to/tools']);
$connection->registerPlugin($mcpServer);

// The tool can be called via MCP tools/call
$result = $connection->{'tools/call'}([
    'name' => 'send_telegram_message',
    'arguments' => ['message' => 'Your message here']
]);
```

## Technical Implementation

### Dependencies

- `telegramMe` command-line utility must be installed and accessible in the system PATH
- PHP `exec()` function must be enabled for command execution

### Security Considerations

- All messages are properly escaped using `escapeshellarg()` to prevent shell injection
- Input validation ensures the message parameter is a non-empty string
- Error handling prevents exposure of system information

### Performance Considerations

- Large messages are split to respect Telegram's limits
- Each chunk is sent as a separate command execution
- Response time scales with the number of chunks for very long messages

## Troubleshooting

### Common Issues

1. **Command not found**: Ensure `telegramMe` is installed and in the system PATH
2. **Permission denied**: Check that PHP has permission to execute shell commands
3. **Partial failures**: Check the `failed_sends` field in the metadata for partial failures

### Debug Information

The response metadata provides useful information for debugging:

```php
$metadata = $result['metadata'];
echo "Total chunks attempted: " . $metadata['total_chunks'] . "\n";
echo "Successfully sent: " . $metadata['successful_sends'] . "\n";
echo "Failed to send: " . $metadata['failed_sends'] . "\n";
echo "Was message split: " . ($metadata['was_split'] ? 'Yes' : 'No') . "\n";
```

## Best Practices

1. **Message Content**: Avoid sending sensitive information through Telegram
2. **Message Length**: Consider breaking up very long messages at logical points before sending
3. **Error Handling**: Always check the `isError` flag and handle failures appropriately
4. **Rate Limiting**: Be mindful of Telegram's rate limits when sending many messages
5. **Testing**: Test with both short and long messages to ensure proper behavior

## Version History

- **v1.1**: Added automatic message splitting for messages > 4000 characters
- **v1.0**: Initial implementation with basic message sending functionality