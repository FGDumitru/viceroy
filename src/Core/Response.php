<?php

namespace Viceroy\Core;

use Psr\Http\Message\ResponseInterface;

class Response {

  private ResponseInterface $response;

  private $contents = NULL;

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
    $choice = $this->getChoice();
    return $choice['content'];
  }

  private function getChoice(): mixed {
    $content = json_decode($this->getContent(), TRUE);
    $choices = $content['choices'];
    return $choices[0]['message'];
  }

  private function getContent(): string {
    if (is_null($this->contents)) {
      $this->contents = $this->response->getBody()->getContents();
    }

    return $this->contents;
  }

  public function getLlmResponseRole(): mixed {
    $choice = $this->getChoice();
    return $choice['role'];
  }

  public function getRawContent(): string {
    return $this->getContent();
  }

}