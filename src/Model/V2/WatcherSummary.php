<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

/** Compact watcher row from V2::listWatchers. */
final class WatcherSummary
{
    public function __construct(
        public readonly string $watcherId,
        public readonly string $url,
        /** "active" | "paused" | "deleted". */
        public readonly string $status,
        public readonly int|float $frequencyMinutes,
        public readonly int|float $checksCount,
        public readonly int|float $consecutiveErrors,
        public readonly ?string $lastCheckAt,
        public readonly ?string $nextCheckAt,
        public readonly ?string $lastChangeAt,
        public readonly ?string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            watcherId: Support::str($d['watcher_id'] ?? null),
            url: Support::str($d['url'] ?? null),
            status: Support::str($d['status'] ?? null),
            frequencyMinutes: Support::num($d['frequency_minutes'] ?? null),
            checksCount: Support::num($d['checks_count'] ?? null),
            consecutiveErrors: Support::num($d['consecutive_errors'] ?? null),
            lastCheckAt: Support::optStr($d['last_check_at'] ?? null),
            nextCheckAt: Support::optStr($d['next_check_at'] ?? null),
            lastChangeAt: Support::optStr($d['last_change_at'] ?? null),
            createdAt: Support::optStr($d['created_at'] ?? null),
        );
    }
}
