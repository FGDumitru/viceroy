<?php
namespace Viceroy\Connections\Traits;

Trait setSystemMessageTrait {

    private string $systemMessage;

    public function setSystemMessageTrait(string $message) {
        $this->systemMessage = $message;
    }

    public function getSystemMessage(): string {
        return $this->systemMessage;
    }

}