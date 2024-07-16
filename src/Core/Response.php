<?php

namespace Viceroy\Core;

use Psr\Http\Message\ResponseInterface;

class Response {

  private ResponseInterface $response;

  private $contents = NULL;

  public function __construct(ResponseInterface $response) {
    $this->response = $response;
  }

  public function getRawResponse() {
    return $this->response;
  }

  public function getLlmResponse() {
    $choice = $this->getChoice();
    return $choice['content'];
  }

  private function getChoice() {
    $content = json_decode($this->getContent(), TRUE);
    $choices = $content['choices'];
    return $choices[0]['message'];
  }

  private function getContent() {
    if (is_null($this->contents)) {
      $this->contents = $this->response->getBody()->getContents();
    }

    return $this->contents;
  }

  public function getLlmResponseRole() {
    $choice = $this->getChoice();
    return $choice['role'];
  }

  public function getRawContent() {
    return $this->getContent();
  }

}