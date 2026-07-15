<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

/** One search hit, optionally carrying its full perceive result. */
final class LookupItem
{
    /** @param array<string, mixed> $extra Provider-specific passthrough fields. */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $url,
        public readonly ?string $snippet,
        public readonly int|float|null $position,
        public readonly ?string $source,
        public readonly ?string $date,
        public readonly ?string $imageUrl,
        public readonly ?string $thumbnailUrl,
        public readonly array $extra,
        /** Present for the top-N results when perceiveTop > 0 and it succeeded. */
        public readonly ?PerceiveResult $perceive,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $perceive = Support::optObj($d['perceive'] ?? null);

        return new self(
            title: Support::optStr($d['title'] ?? null),
            url: Support::optStr($d['url'] ?? null),
            snippet: Support::optStr($d['snippet'] ?? null),
            position: Support::optNum($d['position'] ?? null),
            source: Support::optStr($d['source'] ?? null),
            date: Support::optStr($d['date'] ?? null),
            imageUrl: Support::optStr($d['image_url'] ?? null),
            thumbnailUrl: Support::optStr($d['thumbnail_url'] ?? null),
            extra: Support::optObj($d['extra'] ?? null) ?? [],
            perceive: $perceive !== null ? PerceiveResult::fromArray($perceive) : null,
        );
    }
}
