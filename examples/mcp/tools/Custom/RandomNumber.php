<?php

namespace Viceroy\Tools;

use Viceroy\Tools\Interfaces\ToolInterface;

class RandomNumber implements Interfaces\ToolInterface
{

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'randomNumber';
    }

    /**
     * @inheritDoc
     */
    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'randomNumber',
                'description' => 'Returns a number between min and max values.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'min' => [
                            'type' => 'integer',
                            'description' => 'The lowest number that can be random generated.',
                            'default' => 0
                        ],
                        'max' => [
                            'type' => 'integer',
                            'description' => 'The highest number that can be random generated.',
                            'default' => 100
                        ]
                    ],
                    'required' => []
                ]
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments, $configuration): array
    {
        $min = $arguments['min'] ?? 0;
        $max = $arguments['max'] ?? 100;

        $randomNumber = rand($min, $max);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($randomNumber, JSON_PRETTY_PRINT)
                ]
            ],
            'isError' => false
        ];
    }

    /**
     * @inheritDoc
     */
    public function validateArguments(array $arguments): bool
    {
        $min = $arguments['min'] ?? 0;
        $max = $arguments['max'] ?? 100;
        return $min <= $max;
    }
}