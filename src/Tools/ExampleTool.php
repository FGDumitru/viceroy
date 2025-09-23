<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;

class ExampleTool implements ToolInterface
{
    public function getName(): string
    {
        return 'example';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'example',
            'title' => 'Example Tool',
            'description' => 'A simple example tool that demonstrates the modular tool system',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The message to process',
                        'default' => 'Hello World'
                    ]
                ],
                'required' => []
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['text']
                                ],
                                'text' => [
                                    'type' => 'string'
                                ]
                            ],
                            'required' => ['type', 'text']
                        ]
                    ],
                    'isError' => [
                        'type' => 'boolean'
                    ]
                ],
                'required' => ['content', 'isError']
            ]
        ];
    }

    public function validateArguments(array $arguments): bool
    {
        // All arguments are optional for this example tool
        return true;
    }

    public function execute(array $arguments): array
    {
        $message = $arguments['message'] ?? 'Hello World';
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Example tool executed with message: {$message}"
                ]
            ],
            'isError' => false
        ];
    }
}
