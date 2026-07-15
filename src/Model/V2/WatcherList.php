<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

final class WatcherList
{
    /** @param WatcherSummary[] $watchers */
    public function __construct(
        public readonly array $watchers,
        public readonly int|float $skip,
        public readonly int|float $limit,
        public readonly bool $hasMore,
    ) {
    }
}
