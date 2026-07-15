<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

/** A rendered output stored server-side, addressed by signed URL. */
final class OutputArtifact
{
    public function __construct(
        /** Pre-signed download URL (15 minutes). Re-signed on every status GET. */
        public readonly ?string $url,
        public readonly string $objectKey,
        public readonly int|float $sizeBytes,
        public readonly string $contentType,
        public readonly int|float $expiresIn,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            url: Support::optStr($d['url'] ?? null),
            objectKey: Support::str($d['object_key'] ?? null),
            sizeBytes: Support::num($d['size_bytes'] ?? null),
            contentType: Support::str($d['content_type'] ?? null, 'application/octet-stream'),
            expiresIn: Support::num($d['expires_in'] ?? null, 900),
        );
    }
}
