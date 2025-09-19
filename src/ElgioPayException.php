<?php

namespace ElgioPay\SDK;

use Exception;

class ElgioPayException extends Exception
{
    protected $response;

    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null, array $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}