<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class IngestJob
{
    /** @param string[] $warnings */
    public function __construct(
        public readonly string $jobId,
        /** "queued" | "discovering" | "processing" | "completed" | "failed" | "canceled". */
        public readonly string $status,
        /** "urls" | "sitemap" | "crawl". */
        public readonly string $mode,
        public readonly int|float $pagesDiscovered,
        public readonly int|float $pagesProcessed,
        public readonly int|float $pagesFailed,
        public readonly int|float $totalChunks,
        /** Signed URL to the final JSONL, once completed. */
        public readonly ?string $outputUrl,
        public readonly ?string $errorMessage,
        public readonly ?string $webhookUrl,
        public readonly bool $webhookDelivered,
        public readonly ?string $createdAt,
        public readonly ?string $completedAt,
        public readonly array $warnings,
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
            webhookUrl: Support::optStr($d['webhook_url'] ?? null),
            webhookDelivered: ($d['webhook_delivered'] ?? null) === true,
            createdAt: Support::optStr($d['created_at'] ?? null),
            completedAt: Support::optStr($d['completed_at'] ?? null),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
