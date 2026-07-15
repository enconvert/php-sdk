<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class DistillResult
{
    /**
     * @param DistillItem[] $results
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $operationId,
        public readonly int|float $total,
        public readonly int|float $completed,
        public readonly int|float $failed,
        public readonly array $results,
        public readonly int|float $totalCostCents,
        public readonly array $warnings,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $results = is_array($d['results'] ?? null) ? $d['results'] : [];

        return new self(
            operationId: Support::str($d['operation_id'] ?? null),
            total: Support::num($d['total'] ?? null),
            completed: Support::num($d['completed'] ?? null),
            failed: Support::num($d['failed'] ?? null),
            results: array_map(
                static fn (mixed $item): DistillItem => DistillItem::fromArray(is_array($item) ? $item : []),
                $results
            ),
            totalCostCents: Support::num($d['total_cost_cents'] ?? null),
            warnings: Support::strArr($d['warnings'] ?? null),
        );
    }
}
