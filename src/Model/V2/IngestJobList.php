<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

final class IngestJobList
{
    /** @param IngestJobSummary[] $jobs */
    public function __construct(
        public readonly array $jobs,
        public readonly int|float $skip,
        public readonly int|float $limit,
        public readonly bool $hasMore,
    ) {
    }
}
