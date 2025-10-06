<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;
use DateTime;
use DateTimeZone;

class GetCurrentDateTimeTool implements ToolInterface
{
    public function getName(): string
    {
        return 'get_current_datetime';
    }

    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_current_datetime',
                'description' => 'Returns the current date and time in the specified timezone.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone identifier (e.g., UTC, Europe/London, America/New_York)',
                            'default' => 'UTC'
                        ]
                    ]
                ]
            ]
        ];
    }

    public function validateArguments($arguments): bool
    {
        $timezone = $arguments['timezone'] ?? 'UTC';

        // Validate timezone using PHP's timezone functions
        if (!in_array($timezone, timezone_identifiers_list())) {
            return false;
        }

        return true;
    }

    public function execute( $arguments): array
    {
        $timezone = $arguments['timezone'] ?? 'UTC';

        if (!$this->validateArguments($arguments)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Invalid timezone '{$timezone}' provided."
                    ]
                ],
                'isError' => true
            ];
        }

        try {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            $formattedDateTime = $dateTime->format('c'); // ISO 8601 format

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Current date and time in {$timezone}: {$formattedDateTime}"
                    ]
                ],
                'isError' => false
            ];
        } catch (\Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Failed to get current datetime - {$e->getMessage()}"
                    ]
                ],
                'isError' => true
            ];
        }
    }
}
