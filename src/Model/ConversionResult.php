<?php

declare(strict_types=1);

namespace Enconvert\Model;

use Enconvert\Internal\Support;

/** Result of a completed single-file conversion (URL or file based). */
final class ConversionResult
{
    public function __construct(
        public readonly string $presignedUrl,
        public readonly string $objectKey,
        public readonly string $filename,
        public readonly int|float|null $fileSize = null,
        public readonly int|float|null $conversionTimeSeconds = null,
        public readonly ?string $jobId = null,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $objectKey = Support::str($d['object_key'] ?? null);
        // Job-status fallback responses omit `filename`; recover it from the
        // object key so callers never see an empty basename artefact.
        $filenameFallback = $objectKey === '' ? '' : basename($objectKey);
        $filename = Support::optStr($d['filename'] ?? null) ?? $filenameFallback;

        return new self(
            presignedUrl: Support::str($d['presigned_url'] ?? null),
            objectKey: $objectKey,
            filename: $filename,
            fileSize: Support::optNum($d['file_size'] ?? null),
            conversionTimeSeconds: Support::optNum($d['conversion_time_seconds'] ?? null),
            jobId: Support::optStr($d['job_id'] ?? null),
        );
    }
}
