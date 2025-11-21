<?php

/**
 * Core HTTP response processor for Viceroy
 *
 * Provides:
 * - Raw and processed response access
 * - Think-tag extraction and metadata processing
 * - Streamed response handling with progressive accumulation
 * - Content validation and sanitization
 *
 * Key Features:
 * - Dual-mode handling for streamed/complete responses
 * - Think-tag extraction for internal metadata
 * - Caching of processed content for performance
 * - PSR-7 response integration
 *
 * Interdependencies:
 * - Works with Request class to complete HTTP lifecycle
 * - Integrates with Connection implementations for response parsing
 * - Used by RolesManager for role validation
 *
 * @package Viceroy\Core
 */
namespace Viceroy\Core;

use Psr\Http\Message\ResponseInterface;

class Response
{
    /**
     * Raw HTTP response container
     *
     * @var ResponseInterface $response Unprocessed PSR-7 response object containing:
     *   - Headers
     *   - Status code
     *   - Raw body content
     */
    private ResponseInterface $response;

    /**
     * Cached response body contents
     *
     * @var mixed $contents Raw response body storage
     */
    private $contents = NULL;

    /**
     * Processed LLM response content
     *
     * @var mixed $processedContent Cleaned response content with think-tags removed
     */
    private $processedContent = NULL;

    /**
     * Extracted think-tag content
     *
     * @var string $thinkContent Concatenated metadata from think-tags
     */
    private $thinkContent = NULL;

    /**
     * Streaming status flag
     *
     * @var bool $wasStreamed Indicates if response was received via streaming
     */
    private $wasStreamed = FALSE;

    /**
     * Accumulated streamed content buffer
     *
     * @var mixed $streamedContent Progressive response content for streaming
     */
    private $streamedContent = NULL;

    /**
     * Response processor constructor
     *
     * @param ResponseInterface $response PSR-7 HTTP response to process
     * @throws \InvalidArgumentException If invalid response object provided
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Gets the raw HTTP response
     *
     * @return ResponseInterface The unprocessed PSR-7 response object
     */
    public function getRawResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Gets processed LLM response content
     *
     * @return mixed Cleaned response content with think-tags removed
     */
    public function getLlmResponse(): mixed
    {
        $this->processContent();
        return $this->processedContent;
    }

    /**
     * Gets content extracted from think-tags
     *
     * @return string Concatenated metadata from all think-tags
     */
    public function getThinkContent(): string
    {
        $this->processContent();
        return $this->thinkContent;
    }

    /**
     * Retrieves first choice from LLM response structure
     *
     * @return array LLM message object containing:
     *   - role (string)
     *   - content (string)
     *   - think tags (if present)
     */
    private function getChoice()
    {
        if (!$this->wasStreamed()) {
            $content = $this->getContent();
            $contentArray = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $choices = $contentArray['choices'];
            return $choices[0]['message'];
        } else {
            return $this->getStreamedContent();
        }
    }

    /**
     * Gets raw response content
     *
     * @return string Raw response body contents
     */
    private function getContent(): string
    {
        if (is_null($this->contents)) {
            $this->contents = $this->response->getBody()->getContents();
        }
        return $this->contents;
    }

    /**
     * Processes response content extracting think-tags and sanitizing output
     *
     * Execution Steps:
     * 1. Retrieves raw content (streamed or complete)
     * 2. Extracts think-tag content into $this->thinkContent
     * 3. Removes think-tags from final output
     * 4. Caches processed content for subsequent calls
     *
     * @throws \RuntimeException If content parsing fails
     */
    private function processContent(): void
    {
        if ($this->processedContent === null) {
            if (!$this->wasStreamed) {
                $content = $this->getChoice();

            } else {
                $content = $this->getStreamedContent();
            }

            // Extract think tags
            preg_match_all('/<think>(.*?)<\/think>/s', $content, $matches);
            $this->thinkContent = implode("\n", $matches[0] ?? []);

            // Remove think tags from final output
            $this->processedContent = preg_replace('/<think>.*?<\/think>/s', '', $content);
        }
    }

    /**
     * Gets role from LLM response
     *
     * @param string|null $defaultAiRole Default role if not present
     * @return mixed Role specified in the response
     */
    public function getLlmResponseRole(?string $defaultAiRole = 'assistant'): mixed
    {
        if (!$this->wasStreamed()) {
            $choice = $this->getChoice();
            return $choice['role'];
        } else {
            return $defaultAiRole;
        }
    }

    /**
     * Gets raw unprocessed response content
     *
     * @return string Complete raw response content
     */
    public function getRawContent(): string
    {
        $result =  $this->wasStreamed() ? $this->getStreamedContent() : $this->getContent();


        return $result;
    }

    /**
     * Checks if response was streamed
     *
     * @return bool True if response was streamed
     */
    public function wasStreamed(): bool
    {
        return $this->wasStreamed;
    }

    /**
     * Sets streamed content buffer
     *
     * @param mixed $streamedContent Accumulated streamed data
     */
    public function setStreamedContent(mixed $streamedContent): void
    {
        $this->streamedContent = $streamedContent;
    }

    public function setContent(mixed $content): void
    {
        $this->contents = $content;
    }

    /**
     * Gets accumulated streamed content
     *
     * @return mixed Streamed content accumulated during progressive response
     */
    public function getStreamedContent(): mixed
    {
        return $this->streamedContent;
    }

    /**
     * Sets streaming status flag
     *
     * @param bool $wasStreamed Streaming status indicator
     */
    public function setWasStreamed(bool $wasStreamed = true): void
    {
        $this->wasStreamed = $wasStreamed;
    }
}
