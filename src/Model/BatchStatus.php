<?php

declare(strict_types=1);

namespace Enconvert\Model;

use Enconvert\Internal\Support;

/** Aggregate status of an async website batch conversion. */
final class BatchStatus
{
    /** @param BatchItem[] $items */
    public function __construct(
        public readonly string $batchId,
        /** "processing" | "completed" | "partial" | "failed". */
        public readonly string $status,
        public readonly int|float $total,
        public readonly int|float $completed,
        public readonly int|float $failed,
        public readonly int|float $inProgress,
        /** "zip" | "individual". */
        public readonly string $outputMode,
        /** Presigned URL of the bundled ZIP when outputMode is "zip". */
        public readonly ?string $zipDownloadUrl = null,
        public readonly array $items = [],
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $rawItems = is_array($d['items'] ?? null) ? $d['items'] : [];

        return new self(
            batchId: Support::str($d['batch_id'] ?? null),
            status: Support::str($d['status'] ?? null),
            total: Support::num($d['total'] ?? null),
            completed: Support::num($d['completed'] ?? null),
            failed: Support::num($d['failed'] ?? null),
            inProgress: Support::num($d['in_progress'] ?? null),
            outputMode: Support::str($d['output_mode'] ?? null),
            zipDownloadUrl: Support::optStr($d['zip_download_url'] ?? null),
            items: array_map(
                static fn (mixed $item): BatchItem => BatchItem::fromArray(is_array($item) ? $item : []),
                $rawItems
            ),
        );
    }
}
