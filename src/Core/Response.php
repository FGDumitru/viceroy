<?php

/**
 * Response - Handles HTTP response operations
 * 
 * This class processes and provides access to HTTP responses,
 * including LLM-specific content parsing and think-tag extraction.
 */
namespace Viceroy\Core;

use Psr\Http\Message\ResponseInterface;

class Response {

    /**
     * @var ResponseInterface $response The raw HTTP response
     */
    private ResponseInterface $response;

    /**
     * @var mixed $contents Cached response body contents
     */
    private $contents = NULL;

    /**
     * @var mixed $processedContent Processed LLM response content
     */
    private $processedContent = NULL;

    /**
     * @var string $thinkContent Extracted think-tag content
     */
    private $thinkContent = NULL;

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    /**
     * Constructor
     *
     * @param ResponseInterface $response The HTTP response to process
     */
    public function __construct(ResponseInterface $response) {
        $this->response = $response;
    }

    /**
     * Gets the raw HTTP response
     *
     * @return ResponseInterface The unprocessed HTTP response
     */
    public function getRawResponse(): ResponseInterface {
        return $this->response;
    }

    /**
     * @return mixed
     */
    /**
     * Gets the processed LLM response content
     *
     * @return mixed The processed response content with think-tags removed
     */
    public function getLlmResponse(): mixed {
        $this->processContent();
        return $this->processedContent;
    }

    /**
     * Extracts and returns content within <think> tags from the LLM response.
     * @return string
     */
    /**
     * Gets content extracted from think-tags
     *
     * @return string Concatenated think-tag content
     */
    public function getThinkContent(): string {
        $this->processContent();
        return $this->thinkContent;
    }

    /**
     * Gets the first choice from LLM response
     *
     * @return mixed The first choice message from the response
     */
    private function getChoice(): mixed {
        $content = $this->getContent();
        $contentArray = json_decode($content, TRUE);
        $choices = $contentArray['choices'];
        return $choices[0]['message'];
    }

    /**
     * Gets the raw response content
     *
     * @return string The raw response body contents
     */
    private function getContent(): string {
        if (is_null($this->contents)) {
            $this->contents = $this->response->getBody()->getContents();
        }

        return $this->contents;
    }

    /**
     * Processes response content to extract think-tags and clean output
     *
     * @return void
     */
    private function processContent(): void {
        if ($this->processedContent === NULL) {
            $choice = $this->getChoice();
            $content = $choice['content'];
            preg_match_all('/<think>(.*?)<\/think>/s', $content, $matches);
            $this->thinkContent = implode("\n", $matches[1] ?? []);
            $this->processedContent = preg_replace('/<think>.*?<\/think>/s', '', $content);
        }
    }

    /**
     * Gets the role from the LLM response
     *
     * @return mixed The role specified in the response
     */
    public function getLlmResponseRole(): mixed {
        $choice = $this->getChoice();
        return $choice['role'];
    }

    /**
     * Gets the raw unprocessed response content
     *
     * @return string The complete raw response content
     */
    public function getRawContent(): string {
        return $this->getContent();
    }

}
