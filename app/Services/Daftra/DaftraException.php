<?php

namespace App\Services\Daftra;

use Exception;

class DaftraException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        public readonly ?array $responseData = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function unauthorized(?array $data = null): self
    {
        return new self('Invalid Daftra API key or unauthorized access', 401, null, $data);
    }

    public static function notFound(string $resource = 'resource', ?array $data = null): self
    {
        return new self("Daftra {$resource} not found", 404, null, $data);
    }

    public static function validationError(array $errors, ?array $data = null): self
    {
        $message = 'Daftra validation failed: '.json_encode($errors);
        return new self($message, 422, null, $data);
    }

    public static function serverError(?array $data = null): self
    {
        return new self('Daftra server error', 500, null, $data);
    }
}
