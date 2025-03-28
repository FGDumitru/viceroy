<?php

namespace Viceroy\Connections\Simple;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class simpleLlamaCppOAICompatibleConnection extends OpenAICompatibleEndpointConnection {

  private $llmConnection;
  private $response;

  function __construct($systemMessage = 'You are a helpful LLM that responds to user queries.') {
    parent::__construct();


    $this->llmConnection = new OpenAICompatibleEndpointConnection();


    $this->llmConnection->getRolesManager()
      ->clearMessages()
      ->setSystemMessage($systemMessage);

  }

    function query($queryString) {
        // Add a system message (if the model supports it).
        $this->llmConnection->getRolesManager()
            ->clearMessages()
            ->setSystemMessage('You are a helpful LLM that responds to user queries.');

        // Ensure model name is set
        if (!empty($this->model)) {
            $this->llmConnection->setLLMmodelName($this->model);
        }

        // Query the model as a User.
        try {
            $this->llmConnection->getRolesManager()
                ->addMessage('user', $queryString);
        }
        catch (Exception $e) {
            throw new \RuntimeException("Failed to add user message: " . $e->getMessage());
        }

        // Perform the actual LLM query.
        $this->response = $this->llmConnection->queryPost();
        

        return $this->response->getLlmResponse();
    }

  function getResponse() {
    if (!$this->response) {
        throw new \RuntimeException("No response available - query may have failed");
    }
    return $this->response->getLlmResponse();
  }

}
