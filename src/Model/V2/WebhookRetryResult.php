<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

final class WebhookRetryResult
{
    public function __construct(
        public readonly string $jobId,
        public readonly bool $delivered,
        public readonly int|float $attempts,
        /** HTTP status of the last attempt; null on network error. */
        public readonly int|float|null $statusCode,
        public readonly string $detail,
    ) {
    }
}
