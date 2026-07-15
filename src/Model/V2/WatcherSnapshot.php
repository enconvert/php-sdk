<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class WatcherSnapshot
{
    /**
     * @param array<int, array<string, mixed>> $changes Diff entries. Values
     *   are untrusted page content — escape before rendering.
     */
    public function __construct(
        public readonly string $checkedAt,
        public readonly bool $hasChanges,
        /** 0.0-1.0 similarity to the previous capture. */
        public readonly int|float|null $similarity,
        public readonly int|float|null $renderQuality,
        public readonly int|float $changeCount,
        public readonly array $changes,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $rawChanges = is_array($d['changes'] ?? null) ? $d['changes'] : [];
        $changes = array_values(array_filter(
            $rawChanges,
            static fn (mixed $c): bool => is_array($c)
        ));

        return new self(
            checkedAt: Support::str($d['checked_at'] ?? null),
            hasChanges: ($d['has_changes'] ?? null) === true,
            similarity: Support::optNum($d['similarity'] ?? null),
            renderQuality: Support::optNum($d['render_quality'] ?? null),
            changeCount: Support::num($d['change_count'] ?? null),
            changes: $changes,
        );
    }
}
