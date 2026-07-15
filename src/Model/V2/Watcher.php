<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class Watcher
{
    /** @param array<string, mixed>|null $trackFields */
    public function __construct(
        public readonly string $watcherId,
        public readonly string $url,
        /** "active" | "paused" | "deleted". */
        public readonly string $status,
        public readonly int|float $frequencyMinutes,
        /** "auto" | "text" | "structured" | "tables" | "metadata". */
        public readonly string $diffMode,
        public readonly ?array $trackFields,
        public readonly ?string $webhookUrl,
        public readonly bool $notifyEmail,
        public readonly int|float $consecutiveErrors,
        public readonly int|float $checksCount,
        public readonly ?string $lastCheckAt,
        public readonly ?string $nextCheckAt,
        public readonly ?string $lastChangeAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
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
            diffMode: Support::str($d['diff_mode'] ?? null),
            trackFields: Support::optObj($d['track_fields'] ?? null),
            webhookUrl: Support::optStr($d['webhook_url'] ?? null),
            notifyEmail: ($d['notify_email'] ?? null) !== false,
            consecutiveErrors: Support::num($d['consecutive_errors'] ?? null),
            checksCount: Support::num($d['checks_count'] ?? null),
            lastCheckAt: Support::optStr($d['last_check_at'] ?? null),
            nextCheckAt: Support::optStr($d['next_check_at'] ?? null),
            lastChangeAt: Support::optStr($d['last_change_at'] ?? null),
            createdAt: Support::optStr($d['created_at'] ?? null),
            updatedAt: Support::optStr($d['updated_at'] ?? null),
        );
    }
}
