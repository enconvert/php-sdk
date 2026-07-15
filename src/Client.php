<?php

declare(strict_types=1);

namespace Enconvert;

use Enconvert\Exception\ApiException;
use Enconvert\Exception\EnconvertException;
use Enconvert\Internal\Support;
use Enconvert\Model\BatchStatus;
use Enconvert\Model\BatchSubmission;
use Enconvert\Model\ConversionResult;
use Enconvert\Model\JobStatus;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Enconvert file conversion client.
 *
 * @example
 *   $client = new \Enconvert\Client('sk_...');
 *   $result = $client->convertUrlToPdf('https://example.com');
 *   echo $result->presignedUrl;
 */
final class Client
{
    private const DEFAULT_BASE_URL = 'https://api.enconvert.com';
    /** Default request timeout, in seconds (300s = 5 minutes). */
    private const DEFAULT_TIMEOUT = 300;
    private const DEFAULT_BATCH_POLL_INTERVAL_MS = 5000;
    private const DEFAULT_BATCH_TIMEOUT_MS = 1_800_000;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly GuzzleClient $http;

    /**
     * V2 API namespace: perceive, discover, lookup, distill, ingest, watch.
     * Requires a private API key; endpoints are plan-gated (QuotaException on 402).
     */
    public readonly V2 $v2;

    /**
     * @param array{timeout?: int|float, base_url?: string} $options `timeout`
     *   is in seconds (default 300). `base_url` overrides the API host.
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new EnconvertException("Enconvert: 'apiKey' is required");
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;

        $this->http = new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'http_errors' => false,
        ]);

        $this->v2 = new V2(fn (string $method, string $path, array $opts = []): ResponseInterface => $this->request($method, $path, $opts));
    }

    /** Accessor form of the `v2` property, for callers that prefer method syntax. */
    public function v2(): V2
    {
        return $this->v2;
    }

    // ------------------------------------------------------------------
    // URL conversions (single page)
    // ------------------------------------------------------------------

    /**
     * Convert a URL to PDF.
     *
     * @param array<string, mixed> $opts
     */
    public function convertUrlToPdf(string $url, array $opts = []): ConversionResult
    {
        $body = self::buildUrlBody($url, $opts);
        $body['single_page'] = $opts['singlePage'] ?? true;
        if (Support::isSet($opts, 'pdfOptions')) {
            $body['pdf_options'] = Support::serializePdfOptions($opts['pdfOptions']);
        }

        $data = $this->postJson('/v1/convert/url-to-pdf', $body);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    /**
     * Convert a URL to a PNG screenshot.
     *
     * @param array<string, mixed> $opts
     */
    public function convertUrlToScreenshot(string $url, array $opts = []): ConversionResult
    {
        $body = self::buildUrlBody($url, $opts);

        $data = $this->postJson('/v1/convert/url-to-screenshot', $body);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    /**
     * Convert a URL to clean GitHub-Flavored Markdown with YAML frontmatter
     * (title, description, url, links, images). Strips nav/footer/ads/scripts
     * and extracts the main article content.
     *
     * @param array<string, mixed> $opts
     */
    public function convertUrlToMarkdown(string $url, array $opts = []): ConversionResult
    {
        $body = self::buildUrlBody($url, $opts);

        $data = $this->postJson('/v1/convert/url-to-markdown', $body);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Website conversions (async batch, whole-site crawl)
    // ------------------------------------------------------------------

    /**
     * Convert every discovered page of a website to PDF. Async-only: pages are
     * discovered via sitemap or full crawl (plan-dependent), converted in the
     * background, and bundled into a single ZIP. Poll with getBatchStatus or
     * block with waitForBatch. Requires a private API key with crawl access.
     *
     * @param array<string, mixed> $opts
     */
    public function convertWebsiteToPdf(string $url, array $opts = []): BatchSubmission
    {
        $body = self::buildWebsiteBody($url, $opts);
        if (Support::isSet($opts, 'singlePage')) {
            $body['single_page'] = $opts['singlePage'];
        }
        if (Support::isSet($opts, 'pdfOptions')) {
            $body['pdf_options'] = Support::serializePdfOptions($opts['pdfOptions']);
        }

        // No job-polling fallback: website submissions have no per-job row, so
        // a 5xx here means the submission itself failed and must surface directly.
        $data = $this->postJson('/v1/convert/website-to-pdf', $body, false);

        return BatchSubmission::fromArray($data);
    }

    /**
     * Screenshot every discovered page of a website (PNG). Async-only, bundled
     * into a single ZIP. Poll with getBatchStatus or block with waitForBatch.
     * Requires a private API key with crawl access.
     *
     * @param array<string, mixed> $opts
     */
    public function convertWebsiteToScreenshot(string $url, array $opts = []): BatchSubmission
    {
        $body = self::buildWebsiteBody($url, $opts);

        $data = $this->postJson('/v1/convert/website-to-screenshot', $body, false);

        return BatchSubmission::fromArray($data);
    }

    // ------------------------------------------------------------------
    // File conversions
    // ------------------------------------------------------------------

    /**
     * Convert an image between formats (jpeg, png, svg, heic, webp), or
     * rasterize a PDF to JPEG. Only pairs implemented by the API are accepted;
     * unsupported pairs throw before any request is made.
     *
     * @param string|array{data: string, filename: string, contentType?: string} $file
     *   A filesystem path, or raw bytes wrapped with an explicit filename.
     * @param array{outputFormat: string, saveTo?: string, outputFilename?: string} $opts
     */
    public function convertImage(string|array $file, array $opts): ConversionResult
    {
        $part = Support::toFilePart($file);
        $inputFormat = Formats::resolveInputFormat($part['filename'], Formats::IMAGE_FORMATS);
        $outputFormat = Formats::normalizeOutputFormat($opts['outputFormat']);
        $endpoint = '/v1/convert/' . Formats::assertConversionImplemented($inputFormat, $outputFormat);

        $data = $this->postFile($endpoint, $part, ['outputFilename' => $opts['outputFilename'] ?? null]);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    /**
     * Convert a document (doc, excel, ppt, odt, ods, odp, ots, pages, numbers,
     * epub, html, markdown, csv, json, xml, yaml, toml). Output defaults to
     * pdf. Only pairs implemented by the API are accepted; unsupported pairs
     * throw before any request is made.
     *
     * @param string|array{data: string, filename: string, contentType?: string} $file
     * @param array{outputFormat?: string, saveTo?: string, outputFilename?: string, pdfOptions?: array<string, mixed>} $opts
     */
    public function convertDocument(string|array $file, array $opts = []): ConversionResult
    {
        $part = Support::toFilePart($file);
        $inputFormat = Formats::resolveInputFormat($part['filename'], Formats::DOCUMENT_FORMATS);
        $outputFormat = Formats::normalizeOutputFormat($opts['outputFormat'] ?? 'pdf');
        $endpoint = '/v1/convert/' . Formats::assertConversionImplemented($inputFormat, $outputFormat);

        $data = $this->postFile($endpoint, $part, [
            'outputFilename' => $opts['outputFilename'] ?? null,
            'pdfOptions' => $opts['pdfOptions'] ?? null,
        ]);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    /**
     * Convert an uploaded file of (almost) any document format to clean Markdown
     * — PDF, DOCX, PPTX, XLSX, CSV, HTML, EPUB, TXT/MD, and legacy/ODF office.
     * The format is auto-detected server-side; a RAG-ingestion building block.
     * Images are not supported.
     *
     * @param string|array{data: string, filename: string, contentType?: string} $file
     * @param array{saveTo?: string, outputFilename?: string} $opts
     */
    public function convertToMarkdown(string|array $file, array $opts = []): ConversionResult
    {
        $part = Support::toFilePart($file);
        $data = $this->postFile('/v1/convert/anything-to-markdown', $part, [
            'outputFilename' => $opts['outputFilename'] ?? null,
        ]);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    /**
     * Convert an uploaded file of (almost) any format to PDF — office/ODF/Pages/
     * Numbers/RTF/CSV, HTML, Markdown, text, raster images, SVG, EPUB, or an
     * existing PDF (passthrough/normalise). The format is auto-detected
     * server-side. Only `pdfOptions.grayscale` is honored on this endpoint.
     *
     * @param string|array{data: string, filename: string, contentType?: string} $file
     * @param array{saveTo?: string, outputFilename?: string, pdfOptions?: array<string, mixed>} $opts
     */
    public function convertToPdf(string|array $file, array $opts = []): ConversionResult
    {
        $part = Support::toFilePart($file);
        $data = $this->postFile('/v1/convert/anything-to-pdf', $part, [
            'outputFilename' => $opts['outputFilename'] ?? null,
            'pdfOptions' => $opts['pdfOptions'] ?? null,
        ]);
        $result = ConversionResult::fromArray($data);
        if (!empty($opts['saveTo'])) {
            $this->download($result->presignedUrl, $opts['saveTo']);
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Job + batch status
    // ------------------------------------------------------------------

    /** Poll the status of an async conversion job. */
    public function getJobStatus(string $jobId): JobStatus
    {
        $resp = $this->request('GET', "/v1/convert/status/{$jobId}");
        Support::raiseForStatus($resp);
        $data = json_decode((string) $resp->getBody(), true);

        return JobStatus::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Get the status of an async batch (website conversion). Returns aggregate
     * counts, per-URL statuses, and download URLs. Private API keys only.
     */
    public function getBatchStatus(string $batchId): BatchStatus
    {
        $resp = $this->request('GET', "/v1/convert/batch/{$batchId}");
        Support::raiseForStatus($resp);
        $data = json_decode((string) $resp->getBody(), true);

        return BatchStatus::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Poll a batch until it leaves "processing", then return its final status.
     * With `saveTo`, downloads the batch ZIP once available. Throws
     * ApiException(504) on timeout.
     *
     * @param array{intervalMs?: int, timeoutMs?: int, saveTo?: string} $opts
     */
    public function waitForBatch(string $batchId, array $opts = []): BatchStatus
    {
        $intervalMs = $opts['intervalMs'] ?? self::DEFAULT_BATCH_POLL_INTERVAL_MS;
        $timeoutMs = $opts['timeoutMs'] ?? self::DEFAULT_BATCH_TIMEOUT_MS;
        $deadline = self::nowMs() + $timeoutMs;

        for (;;) {
            $status = $this->getBatchStatus($batchId);
            if ($status->status !== 'processing') {
                if (!empty($opts['saveTo'])) {
                    if ($status->zipDownloadUrl === null) {
                        throw new ApiException(
                            500,
                            "Batch {$batchId} finished with status '{$status->status}' but no ZIP is available to save"
                        );
                    }
                    $this->download($status->zipDownloadUrl, $opts['saveTo']);
                }

                return $status;
            }
            if (self::nowMs() >= $deadline) {
                throw new ApiException(504, "Batch {$batchId} did not complete within {$timeoutMs}ms");
            }
            usleep((int) ($intervalMs * 1000));
        }
    }

    // ------------------------------------------------------------------
    // Internal helpers (mirror of node-sdk's postJson / postFile / pollJob /
    // download / toFilePart / raiseForStatus)
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function postJson(string $endpoint, array $body, bool $jobFallback = true): array
    {
        $jobId = $jobFallback ? Support::newJobId() : null;
        if ($jobId !== null) {
            $body['job_id'] = $jobId;
        }

        try {
            $resp = $this->request('POST', $endpoint, ['json' => $body]);
            Support::raiseForStatus($resp);
            $data = json_decode((string) $resp->getBody(), true);
            $data = is_array($data) ? $data : [];

            // Some success responses omit job_id (URL sync path); backfill the
            // client-generated id so callers can still poll getJobStatus with it.
            return $jobId !== null ? array_merge(['job_id' => $jobId], $data) : $data;
        } catch (ApiException $e) {
            if ($jobId !== null && $e->getStatusCode() >= 500) {
                return $this->pollJob($jobId);
            }

            throw $e;
        }
    }

    /**
     * @param array{bytes: string, filename: string, contentType: string} $part
     * @param array{outputFilename?: string|null, pdfOptions?: array<string, mixed>|null} $opts
     * @return array<string, mixed>
     */
    private function postFile(string $endpoint, array $part, array $opts = []): array
    {
        $jobId = Support::newJobId();
        $multipart = [
            [
                'name' => 'file',
                'contents' => $part['bytes'],
                'filename' => $part['filename'],
                'headers' => ['Content-Type' => $part['contentType']],
            ],
            ['name' => 'direct_download', 'contents' => 'false'],
            ['name' => 'job_id', 'contents' => $jobId],
        ];
        if (!empty($opts['outputFilename'])) {
            $multipart[] = ['name' => 'output_filename', 'contents' => $opts['outputFilename']];
        }
        if (Support::isSet($opts, 'pdfOptions')) {
            $multipart[] = [
                'name' => 'pdf_options',
                'contents' => (string) json_encode(Support::serializePdfOptions($opts['pdfOptions'])),
            ];
        }

        try {
            $resp = $this->request('POST', $endpoint, ['multipart' => $multipart]);
            Support::raiseForStatus($resp);
            $data = json_decode((string) $resp->getBody(), true);
            $data = is_array($data) ? $data : [];

            return array_merge(['job_id' => $jobId], $data);
        } catch (ApiException $e) {
            if ($e->getStatusCode() >= 500) {
                return $this->pollJob($jobId);
            }

            throw $e;
        }
    }

    /** Poll job status until success/failure. Used as fallback when the HTTP request fails. */
    private function pollJob(string $jobId, int $maxWaitMs = 300_000, int $intervalMs = 3_000): array
    {
        $deadline = self::nowMs() + $maxWaitMs;
        while (self::nowMs() < $deadline) {
            usleep($intervalMs * 1000);
            $resp = $this->request('GET', "/v1/convert/status/{$jobId}");
            if ($resp->getStatusCode() === 404) {
                continue;
            }
            Support::raiseForStatus($resp);
            $data = json_decode((string) $resp->getBody(), true);
            $data = is_array($data) ? $data : [];
            if (($data['status'] ?? null) === 'success') {
                return $data;
            }
            if (($data['status'] ?? null) === 'failed') {
                // Support::isSet mirrors JS `??`: only a missing/null error
                // falls back — an explicit "" error message is preserved.
                $errorMessage = Support::isSet($data, 'error') ? $data['error'] : 'Conversion failed';
                throw new ApiException(500, is_string($errorMessage) ? $errorMessage : 'Conversion failed');
            }
        }

        throw new ApiException(504, 'Conversion timed out');
    }

    /** Save a presigned URL to a local file. Streams the response to disk. Sends no API key — it's a signed S3 URL. */
    private function download(string $url, string $dest): void
    {
        $dir = dirname($dest);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $downloader = new GuzzleClient(['http_errors' => false]);
        $resp = $downloader->request('GET', $url, ['sink' => $dest]);
        if ($resp->getStatusCode() >= 400) {
            if (is_file($dest)) {
                @unlink($dest);
            }

            throw new ApiException($resp->getStatusCode(), 'Failed to download: ' . $resp->getReasonPhrase());
        }
    }

    /** Centralized, authenticated, timeout-wrapped request. */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = array_merge(['X-API-Key' => $this->apiKey], $options['headers'] ?? []);

        try {
            return $this->http->request($method, $path, $options);
        } catch (GuzzleException $e) {
            throw new EnconvertException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function nowMs(): float
    {
        return microtime(true) * 1000;
    }

    // ------------------------------------------------------------------
    // Request body builders
    // ------------------------------------------------------------------

    /** Request body shared by all single-URL conversions. @param array<string, mixed> $opts */
    private static function buildUrlBody(string $url, array $opts): array
    {
        $body = [
            'url' => $url,
            'direct_download' => false,
            'viewport_width' => $opts['viewportWidth'] ?? 1920,
            'viewport_height' => $opts['viewportHeight'] ?? 1080,
            'load_media' => $opts['loadMedia'] ?? true,
            'enable_scroll' => $opts['enableScroll'] ?? true,
        ];
        if (!empty($opts['outputFilename'])) {
            $body['output_filename'] = $opts['outputFilename'];
        }
        self::appendBrowserAccess($body, $opts);

        return $body;
    }

    /**
     * Request body for website (whole-site) conversions. Render options are
     * only sent when set — the gateway applies the same defaults per page.
     *
     * @param array<string, mixed> $opts
     */
    private static function buildWebsiteBody(string $url, array $opts): array
    {
        $body = ['url' => $url];
        if (!empty($opts['crawlMode'])) {
            $body['crawl_mode'] = $opts['crawlMode'];
        }
        if (Support::isSet($opts, 'includePatterns')) {
            $body['include_patterns'] = $opts['includePatterns'];
        }
        if (Support::isSet($opts, 'excludePatterns')) {
            $body['exclude_patterns'] = $opts['excludePatterns'];
        }
        if (!empty($opts['notificationEmail'])) {
            $body['notification_email'] = $opts['notificationEmail'];
        }
        if (!empty($opts['callbackUrl'])) {
            $body['callback_url'] = $opts['callbackUrl'];
        }
        if (!empty($opts['outputFilename'])) {
            $body['output_filename'] = $opts['outputFilename'];
        }
        if (Support::isSet($opts, 'viewportWidth')) {
            $body['viewport_width'] = $opts['viewportWidth'];
        }
        if (Support::isSet($opts, 'viewportHeight')) {
            $body['viewport_height'] = $opts['viewportHeight'];
        }
        if (Support::isSet($opts, 'loadMedia')) {
            $body['load_media'] = $opts['loadMedia'];
        }
        if (Support::isSet($opts, 'enableScroll')) {
            $body['enable_scroll'] = $opts['enableScroll'];
        }
        self::appendBrowserAccess($body, $opts);

        return $body;
    }

    /**
     * Attach the plan-gated auth/cookies/headers fields when provided.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $opts
     */
    private static function appendBrowserAccess(array &$body, array $opts): void
    {
        // auth/cookies/headers are object/array-valued in the source SDK,
        // where even an empty {}/[] is truthy — so presence (not emptiness)
        // gates inclusion, matching Support::isSet semantics.
        if (Support::isSet($opts, 'auth')) {
            $body['auth'] = $opts['auth'];
        }
        if (Support::isSet($opts, 'cookies')) {
            $body['cookies'] = $opts['cookies'];
        }
        if (Support::isSet($opts, 'headers')) {
            $body['headers'] = $opts['headers'];
        }
    }
}
