<?php

declare(strict_types=1);

namespace Enconvert\Model;

use Enconvert\Internal\Support;

/** 202 response from an async batch submission (website conversions). */
final class BatchSubmission
{
    public function __construct(
        public readonly string $batchId,
        /** Always "processing" on submission. */
        public readonly string $status,
        /** Number of pages queued for conversion. */
        public readonly int|float $urlCount,
        /** Total URLs found during discovery (before plan limits applied). */
        public readonly int|float|null $totalDiscovered = null,
        /** How URLs were discovered: "sitemap" or "full_crawl". */
        public readonly ?string $discoveryMethod = null,
        /** Output packaging, "zip" for website conversions. */
        public readonly ?string $outputFormat = null,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            batchId: Support::str($d['batch_id'] ?? null),
            status: Support::optStr($d['status'] ?? null) ?? 'processing',
            urlCount: Support::num($d['url_count'] ?? null),
            totalDiscovered: Support::optNum($d['total_discovered'] ?? null),
            discoveryMethod: Support::optStr($d['discovery_method'] ?? null),
            outputFormat: Support::optStr($d['output_format'] ?? null),
        );
    }
}
