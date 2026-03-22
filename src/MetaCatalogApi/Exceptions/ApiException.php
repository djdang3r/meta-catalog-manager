<?php

namespace ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        protected array $body = []
    ) {
        parent::__construct($message, $code);
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
