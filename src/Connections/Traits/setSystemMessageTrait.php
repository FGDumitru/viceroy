<?php
namespace Viceroy\Connections\Traits;

Trait setSystemMessageTrait {

    protected string $systemMessage;

    public function setSystemMessageTrait(string $message) {
        $this->systemMessage = $message;
    }

    public function getSystemMessage(): string {
        return $this->systemMessage;
    }

}