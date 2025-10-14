<?php

namespace Viceroy\Tools;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Tools\Interfaces\ToolInterface;

/**
 * GetRedditHot class implements the ToolInterface to fetch Reddit hot posts.
 */
class GetRedditHot implements ToolInterface
{
    /**
     * Returns the tool's name.
     */
    public function getName(): string
    {
        return 'get_reddit_hot';
    }

    /**
     * Defines the tool's function structure.
     */
    public function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_reddit_hot',
                'description' => 'Fetches the current hot/latest posts from Reddit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                    ]
                ]
            ]
        ];
    }

    /**
     * Validates the arguments passed to the tool.
     */
    public function validateArguments($arguments): bool
    {
        return true;
    }

    /**
     * Executes the tool to fetch Reddit hot posts via LLM.
     */
    public function execute($arguments, $configuration): array
    {

        if (!$this->validateArguments($arguments)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Invalid arguments for get_reddit_hot."
                    ]
                ],
                'isError' => true
            ];
        }

        try {
            // Placeholder for LLM integration to fetch Reddit hot posts

          $connection = new OpenAICompatibleEndpointConnection($configuration);
          $connection->getConfiguration()->setFullConfigData($configuration);
          $connection->addToolDefinition(new WebPageToMarkdownTool());
          $prompt = <<<'EOF'
Get the all Reddit posts titles and their comments links from https://old.reddit.com/hot .
Your output should be a JSON array object where each entry has a 'title' and a 'link' field. Do not output any other addition explication pre or post preamble. Prefix the json object with tripple ticks followed immediately by the "json" string and suffix it by another tripple ticks.

# Example output
```json
(the actual json object here)
```

EOF;

          $connection->queryPost($prompt);
          $LLMOutput = $connection->getLastResponse()->getLlmResponse();

//          $LLMOutput = <<<EOP
//Get the all Reddit posts titles and their comments links from https://old.reddit.com/hot .
//Your output should be a JSON array object where each entry has a 'title' and a 'link' field. Do not output any other addition explication pre or post preamble.
//EOP;


          $result = $this->extractJsonFromText($LLMOutput);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' =>  $result
                    ]
                ],
                'isError' => false
            ];
        } catch (\Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Failed to fetch Reddit hot posts - {$e->getMessage()}"
                    ]
                ],
                'isError' => true
            ];
        }
    }

  /**
   * Extracts a JSON block that is wrapped in a Markdown code fence.
   *
   * @param string $text  The input text that may contain a JSON block.
   * @return string|null  The raw JSON string (without the surrounding fence) or null if nothing is found.
   */
  function extractJsonFromText(string $text): ?string
  {
    // Look for a Markdown code fence that starts with ```json
    // The pattern is intentionally lazy (.*?) so it stops at the first closing ```.
    // \s* allows optional new‑line / spaces before/after the JSON payload.
    if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $text, $m)) {
      // $m[1] contains everything between the fences
      return trim($m[1]);   // trim() removes leading/trailing newlines / spaces
    }

    // If no fence was found, return null
    return null;
  }


}
