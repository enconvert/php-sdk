<?php

declare(strict_types=1);

namespace Enconvert\Internal;

use Enconvert\Exception\ApiException;
use Enconvert\Exception\AuthenticationException;
use Enconvert\Exception\QuotaException;
use Enconvert\Exception\RateLimitException;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared internal helpers used by both the V1 Client and the V2 namespace.
 * Mirrors node-sdk's `internal.ts`.
 *
 * @internal
 */
final class Support
{
    private function __construct()
    {
        // static-only helper class
    }

    /**
     * True when $opts[$key] was explicitly provided (present and non-null).
     * Mirrors JavaScript's `opts.key !== undefined` check: PHP arrays have no
     * "undefined", so a missing key or an explicit null are both treated as
     * "not provided".
     *
     * @param array<string, mixed> $opts
     */
    public static function isSet(array $opts, string $key): bool
    {
        return array_key_exists($key, $opts) && $opts[$key] !== null;
    }

    public static function str(mixed $v, string $fallback = ''): string
    {
        return is_string($v) ? $v : $fallback;
    }

    public static function optStr(mixed $v): ?string
    {
        return is_string($v) ? $v : null;
    }

    public static function num(mixed $v, int|float $fallback = 0): int|float
    {
        return is_int($v) || is_float($v) ? $v : $fallback;
    }

    public static function optNum(mixed $v): int|float|null
    {
        return is_int($v) || is_float($v) ? $v : null;
    }

    /** @return string[] */
    public static function strArr(mixed $v): array
    {
        if (!is_array($v)) {
            return [];
        }

        return array_values(array_filter($v, static fn (mixed $x): bool => is_string($x)));
    }

    /** @return array<string, mixed>|null */
    public static function optObj(mixed $v): ?array
    {
        return is_array($v) ? $v : null;
    }

    /**
     * Copy fields from $source into $out under their wire (snake_case) names,
     * but only when present in $source. `$fieldMap` is `[camelKey => snakeKey]`.
     *
     * @param array<string, mixed> $out
     * @param array<string, mixed> $source
     * @param array<string, string> $fieldMap
     */
    public static function copyIfSet(array &$out, array $source, array $fieldMap): void
    {
        foreach ($fieldMap as $camel => $snake) {
            if (self::isSet($source, $camel)) {
                $out[$snake] = $source[$camel];
            }
        }
    }

    /**
     * Raise the mapped exception for an HTTP error response (status >= 400).
     * No-op for successful responses.
     */
    public static function raiseForStatus(ResponseInterface $resp): void
    {
        $status = $resp->getStatusCode();
        if ($status < 400) {
            return;
        }

        $bodyStr = (string) $resp->getBody();
        $decoded = json_decode($bodyStr, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $message = $decoded['detail'] ?? null;
            if ($message === null || $message === '') {
                $message = $decoded['error'] ?? null;
            }
            if ($message === null || $message === '') {
                $message = json_encode($decoded);
            }
            if (!is_string($message)) {
                $message = (string) json_encode($message);
            }
        } else {
            $message = $bodyStr !== '' ? $bodyStr : "HTTP {$status}";
        }

        throw match (true) {
            $status === 401, $status === 403 => new AuthenticationException($message),
            $status === 402 => new QuotaException($message),
            $status === 429 => new RateLimitException($message),
            default => new ApiException($status, $message),
        };
    }

    /**
     * Serialize PDF options (camelCase) to the API's snake_case wire format.
     * Only fields explicitly set are included.
     *
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    public static function serializePdfOptions(array $o): array
    {
        $out = [];
        self::copyIfSet($out, $o, [
            'pageSize' => 'page_size',
            'pageWidth' => 'page_width',
            'pageHeight' => 'page_height',
            'orientation' => 'orientation',
            'margins' => 'margins',
            'scale' => 'scale',
            'grayscale' => 'grayscale',
            'header' => 'header',
            'footer' => 'footer',
        ]);

        return $out;
    }

    /** A 32-character hex job id. */
    public static function newJobId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
