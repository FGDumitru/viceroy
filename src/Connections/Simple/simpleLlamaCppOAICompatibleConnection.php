<?php

namespace Viceroy\Connections\Simple;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class simpleLlamaCppOAICompatibleConnection extends OpenAICompatibleEndpointConnection {

  private $llmConnection;
  private $response;

  function __construct($systemMessage = 'You are a helpful LLM that responds to user queries.') {
    parent::__construct();


    $this->llmConnection = new OpenAICompatibleEndpointConnection();
    $this->llmConnection->setEndpointTypeToLlamaCpp();

    // Check the endpoint health with detailed diagnostics
    $health = $this->llmConnection->health();
    if (!$health['status']) {
        $error = $health['error'] ?? 'Unknown error';
        $endpointStatus = json_encode($health['endpoints'] ?? []);
        //die("LLM health check failed:\nError: $error\nEndpoint Status: $endpointStatus\nLatency: {$health['latency']}ms");
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
        
        if ($this->response === false) {
            $health = $this->llmConnection->health();
            $errorDetails = [
                'health_status' => $health['status'] ? 'OK' : 'FAILED',
                'endpoints' => $health['endpoints'],
                'last_error' => $health['error'] ?? 'None'
            ];
            $suggestion = "";
            if ($errorDetails['endpoints']['completions']['status_code'] === 404) {
                $suggestion = "\nSuggestion: The completions endpoint may not be properly configured. " .
                             "Check your server URL and endpoint paths in the configuration.";
            }
            
            throw new \RuntimeException(
                "LLM query failed. Diagnostics:\n" . 
                json_encode($errorDetails, JSON_PRETTY_PRINT) .
                $suggestion
            );
        }

        return $this->response->getLlmResponse();
    }

  function getResponse() {
    if (!$this->response) {
        throw new \RuntimeException("No response available - query may have failed");
    }
    return $this->response->getLlmResponse();
  }

}
