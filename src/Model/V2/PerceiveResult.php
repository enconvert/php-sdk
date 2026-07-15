<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class PerceiveResult
{
    /**
     * @param array<string, OutputArtifact> $outputs Keyed by output name
     *   (e.g. "markdown", "screenshot_full_page").
     * @param array<string, mixed>|null $structured Present when extract/schema
     *   was requested. Shape is caller-defined.
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $operationId,
        /** "queued" | "processing" | "completed" | "failed". */
        public readonly string $status,
        public readonly string $url,
        public readonly ?string $urlFinal,
        public readonly ?string $contentHash,
        /** 0.0-1.0 render quality score. */
        public readonly int|float|null $renderQuality,
        public readonly bool $cacheHit,
        public readonly array $outputs,
        public readonly ?array $structured,
        /** "heuristic" | "css" | "llm". */
        public readonly ?string $extractionTier,
        public readonly Tokens $tokens,
        public readonly int|float $costCents,
        public readonly int|float|null $durationMs,
        public readonly ?string $error,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $rawOutputs = is_array($d['outputs'] ?? null) ? $d['outputs'] : [];
        $outputs = [];
        foreach ($rawOutputs as $name => $artifact) {
            $outputs[$name] = OutputArtifact::fromArray(is_array($artifact) ? $artifact : []);
        }

        return new self(
            operationId: Support::str($d['operation_id'] ?? null),
            status: Support::str($d['status'] ?? null),
            url: Support::str($d['url'] ?? null),
            urlFinal: Support::optStr($d['url_final'] ?? null),
            contentHash: Support::optStr($d['content_hash'] ?? null),
            renderQuality: Support::optNum($d['render_quality'] ?? null),
            cacheHit: ($d['cache_hit'] ?? null) === true,
            outputs: $outputs,
            structured: Support::optObj($d['structured'] ?? null),
            extractionTier: Support::optStr($d['extraction_tier'] ?? null),
            tokens: Tokens::fromArray($d['tokens'] ?? null),
            costCents: Support::num($d['cost_cents'] ?? null),
            durationMs: Support::optNum($d['duration_ms'] ?? null),
            error: Support::optStr($d['error'] ?? null),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
