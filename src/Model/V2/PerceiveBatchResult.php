<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class PerceiveBatchResult
{
    /**
     * @param PerceiveResult[] $items One entry per URL. Empty on the initial
     *   202 — poll V2::getPerceiveBatch().
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $jobId,
        /** "queued" | "processing" | "completed" | "failed" | "partial". */
        public readonly string $status,
        /** "manifest" | "zip". */
        public readonly string $outputMode,
        public readonly int|float $total,
        public readonly int|float $completed,
        public readonly int|float $failed,
        public readonly int|float $pending,
        /** Bundle of every successful artifact (outputMode "zip", once done). */
        public readonly ?OutputArtifact $zip,
        public readonly array $items,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $items = is_array($d['items'] ?? null) ? $d['items'] : [];
        $zipRaw = $d['zip'] ?? null;

        return new self(
            jobId: Support::str($d['job_id'] ?? null),
            status: Support::str($d['status'] ?? null),
            outputMode: Support::optStr($d['output_mode'] ?? null) ?? 'manifest',
            total: Support::num($d['total'] ?? null),
            completed: Support::num($d['completed'] ?? null),
            failed: Support::num($d['failed'] ?? null),
            pending: Support::num($d['pending'] ?? null),
            zip: is_array($zipRaw) ? OutputArtifact::fromArray($zipRaw) : null,
            items: array_map(
                static fn (mixed $item): PerceiveResult => PerceiveResult::fromArray(is_array($item) ? $item : []),
                $items
            ),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
