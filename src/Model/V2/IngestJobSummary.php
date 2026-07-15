<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

/** Compact job row from V2::listIngestJobs (webhookUrl replaced by a flag). */
final class IngestJobSummary
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $status,
        public readonly string $mode,
        public readonly int|float $pagesDiscovered,
        public readonly int|float $pagesProcessed,
        public readonly int|float $pagesFailed,
        public readonly int|float $totalChunks,
        public readonly ?string $outputUrl,
        public readonly ?string $errorMessage,
        public readonly bool $webhookConfigured,
        public readonly bool $webhookDelivered,
        public readonly ?string $createdAt,
        public readonly ?string $completedAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            jobId: Support::str($d['job_id'] ?? null),
            status: Support::str($d['status'] ?? null),
            mode: Support::str($d['mode'] ?? null),
            pagesDiscovered: Support::num($d['pages_discovered'] ?? null),
            pagesProcessed: Support::num($d['pages_processed'] ?? null),
            pagesFailed: Support::num($d['pages_failed'] ?? null),
            totalChunks: Support::num($d['total_chunks'] ?? null),
            outputUrl: Support::optStr($d['output_url'] ?? null),
            errorMessage: Support::optStr($d['error_message'] ?? null),
            webhookConfigured: ($d['webhook_configured'] ?? null) === true,
            webhookDelivered: ($d['webhook_delivered'] ?? null) === true,
            createdAt: Support::optStr($d['created_at'] ?? null),
            completedAt: Support::optStr($d['completed_at'] ?? null),
        );
    }
}
