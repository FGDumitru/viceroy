# SendTelegramMessageTool - Intelligent Message Splitting

## Overview

The `SendTelegramMessageTool` has been enhanced with an intelligent message splitting algorithm that respects natural language boundaries and protects URLs from being split across message chunks.

## Features

### Priority-Based Splitting

The algorithm uses a hierarchical approach to determine the best split points:

1. **Paragraph Boundaries** (`\n\n`) - Highest priority
2. **Line Boundaries** (`\n`) - Second priority  
3. **Sentence Boundaries** (`.`, `!`, `?` followed by space) - Third priority
4. **Word Boundaries** (spaces) - Fourth priority
5. **Character Boundaries** - Last resort only

### URL Protection

- URLs are never split across message boundaries
- If a URL would be split, the entire URL is moved to the next message chunk
- Supports various URL formats including:
  - Standard URLs: `https://example.com/page`
  - URLs with query parameters: `https://example.com/search?q=test&sort=date`
  - URLs with fragments: `https://example.com/page#section-1`
  - URLs with encoded characters: `https://example.com/search?q=hello%20world`
  - GitHub URLs: `https://github.com/user/repo/blob/main/file.php`

### Length Constraints

- Maximum 4000 characters per message chunk
- Leaves buffer for Telegram's 4096 character limit and `[X/Y]` prefix
- Gracefully handles single paragraphs/sentences that exceed 4000 characters

## Implementation Details

### Core Methods

#### `splitMessage(string $message): array`
The main splitting method that orchestrates the intelligent chunking process.

#### `extractIntelligentChunk(string $text, int $maxLength): string`
Extracts a single chunk using the priority-based approach.

#### `findBestSplitPosition(string $text, int $maxLength, string $delimiter): int|false`
Finds the best split position for a given delimiter (paragraph or line boundaries).

#### `findSentenceBoundary(string $text, int $maxLength): int|false`
Locates sentence endings that can serve as split points.

#### `findWordBoundary(string $text, int $maxLength): int|false`
Finds word boundaries while avoiding URL fragmentation.

#### `handleUrlBoundaries(string $text, int $maxLength): int|false`
Identifies URLs that would be split and adjusts split points accordingly.

#### `isUrlFragment(string $text): bool`
Determines if a text fragment appears to be part of a URL.

## Usage Examples

### Basic Usage
```php
$tool = new SendTelegramMessageTool();
$result = $tool->execute(['message' => $longMessage], $config);
```

### Long Message with Paragraphs and URLs
```php
$message = "First paragraph with content.\n\n";
$message .= "Second paragraph with URL: https://example.com/resource\n\n";
$message .= "Third paragraph with more content.";

$result = $tool->execute(['message' => $message], $config);
// Will split at paragraph boundaries while keeping URLs intact
```

### Single Long Paragraph with URLs
```php
$message = "This is a very long paragraph without paragraph breaks. ";
$message .= "It contains URLs like https://github.com/user/repo ";
$message .= str_repeat("More content. ", 100);
$message .= "And another URL: https://example.com/another-resource";

$result = $tool->execute(['message' => $message], $config);
// Will split at sentence boundaries while protecting URLs
```

## Edge Cases Handled

### Very Long Single Words
If a message contains a single very long word (no spaces), the algorithm will split it at the character boundary as a last resort.

### Multiple Consecutive Newlines
The algorithm handles multiple consecutive newlines (`\n\n\n`) gracefully by treating them as paragraph boundaries.

### Mixed Content
Messages containing a mix of text, URLs, and formatting are processed intelligently to maintain readability.

### Very Long URLs
URLs longer than the 4000 character limit are kept intact and moved entirely to the next chunk.

## Testing

Comprehensive test suites are available:

- `tests/test_telegram_splitting.php` - Basic splitting functionality
- `tests/test_telegram_url_protection.php` - URL protection specific tests  
- `tests/test_telegram_long_messages.php` - Long message splitting tests
- `examples/telegram_intelligent_splitting_demo.php` - Demonstration examples

### Running Tests
```bash
php tests/test_telegram_splitting.php
php tests/test_telegram_url_protection.php  
php tests/test_telegram_long_messages.php
php examples/telegram_intelligent_splitting_demo.php
```

## Backward Compatibility

The enhanced tool maintains full backward compatibility:

- Existing API remains unchanged
- Method signatures are preserved
- Output format is consistent
- `[X/Y]` numbering system continues to work

## Performance Considerations

- The algorithm uses efficient string operations
- Regular expressions are optimized for common URL patterns
- Splitting decisions are made in a single pass through the text
- Memory usage is proportional to message size

## Benefits

1. **Improved Readability**: Messages split at natural language boundaries
2. **URL Integrity**: Links are never broken across chunks
3. **Better User Experience**: Numbered chunks are more coherent
4. **Robustness**: Handles edge cases gracefully
5. **Flexibility**: Works with various content types and formats

## Future Enhancements

Potential improvements for future versions:

- Support for markdown formatting preservation
- Customizable splitting priorities
- Additional URL pattern recognition
- Integration with link shortening services
- Support for other natural language boundaries (e.g., list items)