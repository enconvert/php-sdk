<?php

declare(strict_types=1);

namespace Enconvert\Model;

use Enconvert\Internal\Support;

/** Status of an async conversion job: "processing" | "success" | "failed". */
final class JobStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $presignedUrl = null,
        public readonly ?string $objectKey = null,
        public readonly ?string $error = null,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            status: Support::str($d['status'] ?? null),
            presignedUrl: Support::optStr($d['presigned_url'] ?? null),
            objectKey: Support::optStr($d['object_key'] ?? null),
            error: Support::optStr($d['error'] ?? null),
        );
    }
}
