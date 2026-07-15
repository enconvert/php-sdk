<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class LookupResult
{
    /**
     * @param LookupItem[] $results
     * @param string[] $perceiveOperationIds
     * @param array<string, mixed>|null $answerBox
     * @param array<string, mixed>|null $knowledgeGraph
     * @param string[] $warnings
     */
    public function __construct(
        /** Audit row id; null when the audit write failed (results still valid). */
        public readonly int|float|null $lookupId,
        public readonly string $query,
        /** "web" | "news" | "images" | "scholar" | "patents" | "maps". */
        public readonly string $category,
        public readonly ?string $country,
        public readonly ?string $locale,
        /** "hour" | "day" | "week" | "month" | "year". */
        public readonly ?string $timeFilter,
        public readonly int|float $total,
        public readonly array $results,
        /** How many results were actually perceived (may be below requested). */
        public readonly int|float $perceiveTop,
        public readonly array $perceiveOperationIds,
        public readonly ?array $answerBox,
        public readonly ?array $knowledgeGraph,
        /** Search-provider credits consumed. */
        public readonly int|float|null $credits,
        public readonly int|float $costCents,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $results = is_array($d['results'] ?? null) ? $d['results'] : [];

        return new self(
            lookupId: Support::optNum($d['lookup_id'] ?? null),
            query: Support::str($d['query'] ?? null),
            category: Support::str($d['category'] ?? null),
            country: Support::optStr($d['country'] ?? null),
            locale: Support::optStr($d['locale'] ?? null),
            timeFilter: Support::optStr($d['time_filter'] ?? null),
            total: Support::num($d['total'] ?? null),
            results: array_map(
                static fn (mixed $item): LookupItem => LookupItem::fromArray(is_array($item) ? $item : []),
                $results
            ),
            perceiveTop: Support::num($d['perceive_top'] ?? null),
            perceiveOperationIds: Support::strArr($d['perceive_operation_ids'] ?? null),
            answerBox: Support::optObj($d['answer_box'] ?? null),
            knowledgeGraph: Support::optObj($d['knowledge_graph'] ?? null),
            credits: Support::optNum($d['credits'] ?? null),
            costCents: Support::num($d['cost_cents'] ?? null),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
