<?php

declare(strict_types=1);

namespace Enconvert\Exception;

/**
 * HTTP 402 — plan feature not enabled or monthly quota exhausted. Raised by
 * V2 endpoints, which are all plan-gated.
 */
class QuotaException extends ApiException
{
    public function __construct(string $message = 'Plan feature not enabled or quota exhausted')
    {
        parent::__construct(402, $message);
    }
}
