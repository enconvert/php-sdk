<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class DistillItem
{
    /**
     * @param array<string, mixed>|null $data Extracted data matching the
     *   requested schema.
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $urlFinal,
        /** "completed" | "failed". */
        public readonly string $status,
        public readonly ?array $data,
        /** "css" | "llm" | "mixed" | "none". */
        public readonly string $extractionTier,
        public readonly int|float $fieldsFromCss,
        public readonly int|float $fieldsFromLlm,
        public readonly int|float|null $renderQuality,
        public readonly Tokens $tokens,
        public readonly int|float $costCents,
        public readonly ?string $error,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            url: Support::str($d['url'] ?? null),
            urlFinal: Support::optStr($d['url_final'] ?? null),
            status: Support::optStr($d['status'] ?? null) ?? 'completed',
            data: Support::optObj($d['data'] ?? null),
            extractionTier: Support::optStr($d['extraction_tier'] ?? null) ?? 'none',
            fieldsFromCss: Support::num($d['fields_from_css'] ?? null),
            fieldsFromLlm: Support::num($d['fields_from_llm'] ?? null),
            renderQuality: Support::optNum($d['render_quality'] ?? null),
            tokens: Tokens::fromArray($d['tokens'] ?? null),
            costCents: Support::num($d['cost_cents'] ?? null),
            error: Support::optStr($d['error'] ?? null),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
