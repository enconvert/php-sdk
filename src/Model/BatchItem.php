<?php

declare(strict_types=1);

namespace Enconvert\Model;

use Enconvert\Internal\Support;

/** Per-URL entry in a batch status response. */
final class BatchItem
{
    public function __construct(
        public readonly string $sourceUrl,
        /** Raw activity status: "In Progress", "Success", or "Failed". */
        public readonly string $status,
        public readonly ?string $downloadUrl = null,
        public readonly int|float|null $outputFileSize = null,
        public readonly ?string $duration = null,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            sourceUrl: Support::str($d['source_url'] ?? null),
            status: Support::str($d['status'] ?? null),
            downloadUrl: Support::optStr($d['download_url'] ?? null),
            outputFileSize: Support::optNum($d['output_file_size'] ?? null),
            duration: Support::optStr($d['duration'] ?? null),
        );
    }
}
