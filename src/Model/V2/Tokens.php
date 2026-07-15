<?php

declare(strict_types=1);

namespace Enconvert\Model\V2;

use Enconvert\Internal\Support;

final class Tokens
{
    public function __construct(
        public readonly int|float $input,
        public readonly int|float $output,
    ) {
    }

    public static function fromArray(mixed $d): self
    {
        $arr = is_array($d) ? $d : [];

        return new self(
            input: Support::num($arr['input'] ?? null),
            output: Support::num($arr['output'] ?? null),
        );
    }
}
