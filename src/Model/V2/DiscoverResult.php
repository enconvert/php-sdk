<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class DiscoverResult
{
    /**
     * @param string[] $urls
     * @param array<string, int|float> $sources Raw counts per source before
     *   dedup, e.g. {sitemap: 42, crawl: 30}.
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $url,
        /** "sitemap" | "crawl" | "hybrid". */
        public readonly string $mode,
        public readonly int|float $total,
        public readonly array $urls,
        public readonly int|float $pagesCrawled,
        /** True when more URLs were found than maxUrls allowed. */
        public readonly bool $truncated,
        public readonly bool $robotsRespected,
        public readonly array $sources,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            url: Support::str($d['url'] ?? null),
            mode: Support::str($d['mode'] ?? null),
            total: Support::num($d['total'] ?? null),
            urls: Support::strArr($d['urls'] ?? null),
            pagesCrawled: Support::num($d['pages_crawled'] ?? null),
            truncated: ($d['truncated'] ?? null) === true,
            robotsRespected: ($d['robots_respected'] ?? null) === true,
            sources: Support::optObj($d['sources'] ?? null) ?? [],
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
