<?php

namespace ScriptDevelop\MetaCatalogManager\Exceptions;

use Exception;

/**
 * Excepción base para errores del Graph API al manejar Catálogos de Meta.
 */
class CatalogApiException extends Exception
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
