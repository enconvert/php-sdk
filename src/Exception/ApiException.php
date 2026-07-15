<?php

declare(strict_types=1);

namespace Enconvert\Exception;

/** Raised for any HTTP error response (status >= 400) not mapped to a more specific exception. */
class ApiException extends EnconvertException
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message)
    {
        parent::__construct(sprintf('[%d] %s', $statusCode, $message));
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
