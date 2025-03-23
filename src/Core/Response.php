<?php

namespace Viceroy\Core;

use Psr\Http\Message\ResponseInterface;

class Response {

    private ResponseInterface $response;

    private $contents = NULL;

    private $processedContent = NULL;

    private $thinkContent = NULL;

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function __construct(ResponseInterface $response) {
        $this->response = $response;
    }

    public function getRawResponse(): ResponseInterface {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function getLlmResponse(): mixed {
        $this->processContent();
        return $this->processedContent;
    }

    /**
     * Extracts and returns content within <think> tags from the LLM response.
     * @return string
     */
    public function getThinkContent(): string {
        $this->processContent();
        return $this->thinkContent;
    }

    private function getChoice(): mixed {
        $content = $this->getContent();
        $contentArray = json_decode($content, TRUE);
        $choices = $contentArray['choices'];
        return $choices[0]['message'];
    }

    private function getContent(): string {
        if (is_null($this->contents)) {
            $this->contents = $this->response->getBody()->getContents();
        }

        return $this->contents;
    }

    private function processContent(): void {
        if ($this->processedContent === NULL) {
            $choice = $this->getChoice();
            $content = $choice['content'];
            preg_match_all('/<think>(.*?)<\/think>/s', $content, $matches);
            $this->thinkContent = implode("\n", $matches[1] ?? []);
            $this->processedContent = preg_replace('/<think>.*?<\/think>/s', '', $content);
        }
    }

    public function getLlmResponseRole(): mixed {
        $choice = $this->getChoice();
        return $choice['role'];
    }

    public function getRawContent(): string {
        return $this->getContent();
    }

}