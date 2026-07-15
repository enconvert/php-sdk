<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class WebhookSecret
{
    public function __construct(
        public readonly string $secret,
        /** Header carrying the HMAC signature, e.g. "X-Enconvert-Signature". */
        public readonly string $signatureHeader,
        public readonly string $timestampHeader,
        public readonly string $signatureScheme,
        public readonly int|float $replayToleranceSeconds,
        /** True when this response just replaced the previous secret. */
        public readonly bool $rotated,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            secret: Support::str($d['secret'] ?? null),
            signatureHeader: Support::str($d['signature_header'] ?? null),
            timestampHeader: Support::str($d['timestamp_header'] ?? null),
            signatureScheme: Support::str($d['signature_scheme'] ?? null),
            replayToleranceSeconds: Support::num($d['replay_tolerance_seconds'] ?? null),
            rotated: ($d['rotated'] ?? null) === true,
        );
    }
}
