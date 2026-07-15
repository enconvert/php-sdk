<?php

declare(strict_types=1);

namespace Enconvert\Exception;

/** HTTP 401/403 — invalid or missing API key. */
class AuthenticationException extends ApiException
{
    public function __construct(string $message = 'Invalid or missing API key')
    {
        parent::__construct(401, $message);
    }
}
