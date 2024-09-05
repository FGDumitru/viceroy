<?php

namespace Viceroy\Connections\Simple;

use Viceroy\Connections\llamacppOAICompatibleConnection;

class simpleLlamaCppOAICompatibleConnection extends llamacppOAICompatibleConnection {

  private $llmConnection;
  private $response;

  function __construct($systemMessage = 'You are a helpful LLM that responds to user queries.') {
    parent::__construct();

    $this->llmConnection = new llamacppOAICompatibleConnection();

    // Check the endpoint health.
    if (!$this->llmConnection->health()) {
      die('The LLM health status is not valid!');
    }

    $this->llmConnection->getRolesManager()
      ->clearMessages()
      ->setSystemMessage($systemMessage);

  }

  function query($queryString) {
    // Add a system message (if the model supports it).
    $this->llmConnection->getRolesManager()
      ->clearMessages()
      ->setSystemMessage('You are a helpful LLM that responds to user queries.');

    // Query the model as a User.
    try {
      echo $queryString . "\n";
      $this->llmConnection->getRolesManager()
        ->addMessage('user', $queryString);
    }
    catch (Exception $e) {
      echo($e->getMessage());
      die(1);
    }

    // Perform the actual LLM query.
    $this->response = $this->llmConnection->queryPost();

    return $this->response->getLlmResponse();
  }

  function getResponse() {
    return $this->response->getLlmResponse();
  }

}