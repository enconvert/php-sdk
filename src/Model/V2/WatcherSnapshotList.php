<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

final class WatcherSnapshotList
{
    /** @param WatcherSnapshot[] $snapshots */
    public function __construct(
        public readonly string $watcherId,
        public readonly array $snapshots,
        public readonly int|float $limit,
    ) {
    }
}
