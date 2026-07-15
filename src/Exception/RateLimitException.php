<?php

declare(strict_types=1);

namespace Enconvert\Exception;

/** HTTP 429 — too many requests. */
class RateLimitException extends ApiException
{
    public function __construct(string $message = 'Rate limit exceeded')
    {
        parent::__construct(429, $message);
    }
}
