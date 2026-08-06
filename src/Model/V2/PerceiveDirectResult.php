<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Psr\Http\Message\ResponseInterface;

/**
 * The raw artifact bytes plus header-borne metadata returned by
 * `perceiveDirect()` / `downloadPerceiveArtifact()`.
 */
final class PerceiveDirectResult
{
    public function __construct(
        /** Raw artifact bytes. */
        public readonly string $content,
        /** Artifact media type, e.g. "text/markdown; charset=utf-8". */
        public readonly string $contentType,
        /** Parsed from Content-Disposition; null when the header is absent. */
        public readonly ?string $filename,
        public readonly string $operationId,
        public readonly string $objectKey,
        public readonly bool $cacheHit,
        /** 0.0-1.0 render quality score; null when the header is absent. */
        public readonly ?float $renderQuality,
        /** HTTP status of the upstream main-document response. */
        public readonly ?int $sourceStatusCode,
        /** sha256 of the artifact; null when the header is absent. */
        public readonly ?string $contentHash,
        /** The operation's warning count (0 when the header is absent). */
        public readonly int $warningsCount,
    ) {
    }

    /**
     * Build from a direct-download response: the body is the artifact bytes,
     * the metadata lives in response headers. Optional headers may be absent
     * — every parse is guarded.
     */
    public static function fromResponse(ResponseInterface $resp): self
    {
        $filename = null;
        if (preg_match('/filename="?([^";]+)"?/', $resp->getHeaderLine('Content-Disposition'), $m) === 1) {
            $filename = $m[1];
        }
        $renderQuality = $resp->getHeaderLine('X-Render-Quality');
        $sourceStatusCode = $resp->getHeaderLine('X-Source-Status-Code');
        $contentHash = $resp->getHeaderLine('X-Content-Hash');
        $warningsCount = $resp->getHeaderLine('X-Warnings-Count');

        return new self(
            content: (string) $resp->getBody(),
            contentType: $resp->getHeaderLine('Content-Type'),
            filename: $filename,
            operationId: $resp->getHeaderLine('X-Operation-Id'),
            objectKey: $resp->getHeaderLine('X-Object-Key'),
            cacheHit: $resp->getHeaderLine('X-Cache-Hit') === 'true',
            renderQuality: is_numeric($renderQuality) ? (float) $renderQuality : null,
            sourceStatusCode: is_numeric($sourceStatusCode) ? (int) $sourceStatusCode : null,
            contentHash: $contentHash !== '' ? $contentHash : null,
            warningsCount: is_numeric($warningsCount) ? (int) $warningsCount : 0,
        );
    }
}
