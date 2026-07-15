<?php

declare(strict_types=1);

namespace Enconvert;

use Enconvert\Exception\EnconvertException;

/**
 * Format tables mirroring the gateway's CONVERTER_MAP (api/v1/convert.py).
 *
 * IMPLEMENTED_CONVERSIONS is the client-side gate: the gateway returns 503
 * for any `{input}-to-{output}` endpoint not in its CONVERTER_MAP, so we
 * reject unsupported pairs here with a useful message instead of paying a
 * network round-trip for a guaranteed failure.
 */
final class Formats
{
    private function __construct()
    {
        // static-only helper class
    }

    /** The 44 implemented `{input}-to-{output}` conversion endpoints. */
    public const IMPLEMENTED_CONVERSIONS = [
        // Structured text (13)
        'json-to-xml',
        'xml-to-json',
        'json-to-yaml',
        'yaml-to-json',
        'csv-to-json',
        'json-to-csv',
        'json-to-toml',
        'toml-to-json',
        'csv-to-xml',
        'xml-to-csv',
        'markdown-to-html',
        'markdown-to-pdf',
        'html-to-pdf',
        // Documents (10)
        'doc-to-pdf',
        'excel-to-pdf',
        'ppt-to-pdf',
        'odt-to-pdf',
        'ods-to-pdf',
        'odp-to-pdf',
        'ots-to-pdf',
        'pages-to-pdf',
        'numbers-to-pdf',
        'epub-to-pdf',
        // Images (21)
        'jpeg-to-png',
        'png-to-jpeg',
        'jpeg-to-svg',
        'svg-to-jpeg',
        'jpeg-to-heic',
        'heic-to-jpeg',
        'jpeg-to-webp',
        'webp-to-jpeg',
        'png-to-svg',
        'svg-to-png',
        'png-to-heic',
        'heic-to-png',
        'png-to-webp',
        'webp-to-png',
        'svg-to-heic',
        'heic-to-svg',
        'svg-to-webp',
        'webp-to-svg',
        'heic-to-webp',
        'webp-to-heic',
        'pdf-to-jpeg',
    ];

    /** Extension -> API format name (input side). */
    public const IMAGE_FORMATS = [
        '.jpg' => 'jpeg',
        '.jpeg' => 'jpeg',
        '.png' => 'png',
        '.svg' => 'svg',
        '.heic' => 'heic',
        '.webp' => 'webp',
        // PDF is an image input solely for pdf-to-jpeg (rasterization).
        '.pdf' => 'pdf',
    ];

    public const DOCUMENT_FORMATS = [
        '.doc' => 'doc',
        '.docx' => 'doc',
        '.xls' => 'excel',
        '.xlsx' => 'excel',
        '.ppt' => 'ppt',
        '.pptx' => 'ppt',
        '.html' => 'html',
        '.htm' => 'html',
        '.odt' => 'odt',
        '.ods' => 'ods',
        '.odp' => 'odp',
        '.ots' => 'ots',
        '.pages' => 'pages',
        '.numbers' => 'numbers',
        '.epub' => 'epub',
        '.md' => 'markdown',
        '.markdown' => 'markdown',
        '.csv' => 'csv',
        '.json' => 'json',
        '.xml' => 'xml',
        '.yaml' => 'yaml',
        '.yml' => 'yaml',
        '.toml' => 'toml',
    ];

    public const MIME_BY_EXT = [
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.png' => 'image/png',
        '.svg' => 'image/svg+xml',
        '.heic' => 'image/heic',
        '.webp' => 'image/webp',
        '.pdf' => 'application/pdf',
        '.doc' => 'application/msword',
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.xls' => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.ppt' => 'application/vnd.ms-powerpoint',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '.html' => 'text/html',
        '.htm' => 'text/html',
        '.odt' => 'application/vnd.oasis.opendocument.text',
        '.ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        '.odp' => 'application/vnd.oasis.opendocument.presentation',
        '.epub' => 'application/epub+zip',
        '.md' => 'text/markdown',
        '.markdown' => 'text/markdown',
        '.csv' => 'text/csv',
        '.json' => 'application/json',
        '.xml' => 'application/xml',
        '.yaml' => 'application/x-yaml',
        '.yml' => 'application/x-yaml',
        '.toml' => 'application/toml',
    ];

    /** Common aliases users pass that differ from the API's canonical format names. */
    private const OUTPUT_FORMAT_ALIASES = [
        'jpg' => 'jpeg',
        'yml' => 'yaml',
        'htm' => 'html',
        'md' => 'markdown',
    ];

    public static function extOf(string $name): string
    {
        $pos = strrpos($name, '.');

        return $pos === false ? '' : strtolower(substr($name, $pos));
    }

    public static function mimeFor(string $name): string
    {
        return self::MIME_BY_EXT[self::extOf($name)] ?? 'application/octet-stream';
    }

    /**
     * Map a filename's extension to its API input format, or throw.
     *
     * @param array<string, string> $map
     */
    public static function resolveInputFormat(string $name, array $map): string
    {
        $ext = self::extOf($name);
        if (!array_key_exists($ext, $map)) {
            $keys = array_keys($map);
            sort($keys);
            $supported = implode(', ', $keys);

            throw new EnconvertException("Unsupported file extension '{$ext}'. Supported: {$supported}");
        }

        return $map[$ext];
    }

    /** Lowercase, strip a leading dot, and resolve aliases (jpg, yml, htm, md). */
    public static function normalizeOutputFormat(string $fmt): string
    {
        $f = preg_replace('/^\./', '', strtolower($fmt)) ?? strtolower($fmt);

        return self::OUTPUT_FORMAT_ALIASES[$f] ?? $f;
    }

    /**
     * List the output formats the API implements for a given input format.
     *
     * @return string[]
     */
    public static function validOutputsFor(string $inputFormat): array
    {
        $prefix = "{$inputFormat}-to-";
        $outputs = [];
        foreach (self::IMPLEMENTED_CONVERSIONS as $name) {
            if (str_starts_with($name, $prefix)) {
                $outputs[] = substr($name, strlen($prefix));
            }
        }
        sort($outputs);

        return $outputs;
    }

    /**
     * Assert `{input}-to-{output}` is an implemented endpoint and return its
     * name. Throws with the list of valid outputs for that input otherwise.
     */
    public static function assertConversionImplemented(string $inputFormat, string $outputFormat): string
    {
        $endpoint = "{$inputFormat}-to-{$outputFormat}";
        if (!in_array($endpoint, self::IMPLEMENTED_CONVERSIONS, true)) {
            $outputs = self::validOutputsFor($inputFormat);
            $hint = count($outputs) > 0
                ? "Supported outputs for '{$inputFormat}': " . implode(', ', $outputs)
                : "No conversions are available for input format '{$inputFormat}'";

            throw new EnconvertException(
                "Conversion '{$inputFormat}' to '{$outputFormat}' is not supported. {$hint}."
            );
        }

        return $endpoint;
    }
}
