<?php

declare(strict_types=1);

namespace Enconvert;

use Enconvert\Exception\EnconvertException;
use Enconvert\Internal\Support;
use Enconvert\Model\V2\DiscoverResult;
use Enconvert\Model\V2\DistillResult;
use Enconvert\Model\V2\IngestJob;
use Enconvert\Model\V2\IngestJobList;
use Enconvert\Model\V2\IngestJobSummary;
use Enconvert\Model\V2\LookupResult;
use Enconvert\Model\V2\PerceiveBatchResult;
use Enconvert\Model\V2\PerceiveResult;
use Enconvert\Model\V2\Watcher;
use Enconvert\Model\V2\WatcherList;
use Enconvert\Model\V2\WatcherSnapshot;
use Enconvert\Model\V2\WatcherSnapshotList;
use Enconvert\Model\V2\WatcherSummary;
use Enconvert\Model\V2\WebhookRetryResult;
use Enconvert\Model\V2\WebhookSecret;
use Psr\Http\Message\ResponseInterface;

/**
 * V2 API namespace, reached as `$client->v2` (or `$client->v2()`).
 *
 * One method per V2 endpoint (20 total across six groups: perceive,
 * discover, lookup, distill, ingest, watch). Options are camelCase array
 * keys, serialized to the API's snake_case wire format; responses are mapped
 * back to camelCase typed result objects. User-data payloads (schemas,
 * extracted data, tracked fields, diff changes) pass through untouched.
 *
 * All V2 endpoints require a private API key (public keys are rejected)
 * and are plan-gated: a disabled feature or exhausted monthly quota
 * raises QuotaException (HTTP 402).
 */
final class V2
{
    /** @var callable(string, string, array<string, mixed>): ResponseInterface */
    private $request;

    /** @param callable(string, string, array<string, mixed>): ResponseInterface $request */
    public function __construct(callable $request)
    {
        $this->request = $request;
    }

    // ------------------------------------------------------------------
    // Perceive — render a URL into agent-ready artifacts
    // ------------------------------------------------------------------

    /**
     * Render one URL into the requested outputs (markdown, screenshots, PDF,
     * links, structured data, ...). Synchronous: returns the completed
     * operation with 15-minute signed artifact URLs.
     *
     * @param array<string, mixed> $opts
     */
    public function perceive(string $url, array $opts = []): PerceiveResult
    {
        $body = self::serializePerceiveOptions($opts);
        $body['url'] = $url;

        return PerceiveResult::fromArray($this->post('/v2/perceive', $body));
    }

    /**
     * Re-fetch a perceive operation by id (`per_...`). Artifact URLs are
     * freshly re-signed on every call.
     */
    public function getPerceiveOperation(string $operationId): PerceiveResult
    {
        return PerceiveResult::fromArray($this->get('/v2/perceive/' . rawurlencode($operationId)));
    }

    /**
     * Perceive up to 1000 URLs with one shared options block. Small batches
     * run inline (completed result); larger ones return status "queued" —
     * poll getPerceiveBatch with the jobId.
     *
     * @param string[] $urls
     * @param array<string, mixed> $opts
     */
    public function perceiveBatch(array $urls, array $opts = []): PerceiveBatchResult
    {
        $outputMode = $opts['outputMode'] ?? null;
        unset($opts['outputMode']);

        $body = [
            'urls' => $urls,
            'options' => self::serializePerceiveOptions($opts),
        ];
        if ($outputMode !== null) {
            $body['output_mode'] = $outputMode;
        }

        return PerceiveBatchResult::fromArray($this->post('/v2/perceive/batch', $body));
    }

    /** Poll a perceive batch by jobId. Items fill in as URLs complete. */
    public function getPerceiveBatch(string $jobId): PerceiveBatchResult
    {
        return PerceiveBatchResult::fromArray($this->get('/v2/perceive/batch/' . rawurlencode($jobId)));
    }

    // ------------------------------------------------------------------
    // Discover — enumerate a site's URLs without rendering
    // ------------------------------------------------------------------

    /**
     * List a site's URLs via sitemap, HTTP crawl, or both. No browser
     * rendering — fast and does not consume perceive quota.
     *
     * @param array<string, mixed> $opts
     */
    public function discover(string $url, array $opts = []): DiscoverResult
    {
        $body = ['url' => $url];
        Support::copyIfSet($body, $opts, [
            'mode' => 'mode',
            'maxUrls' => 'max_urls',
            'maxDepth' => 'max_depth',
            'includePatterns' => 'include_patterns',
            'excludePatterns' => 'exclude_patterns',
            'sameDomainOnly' => 'same_domain_only',
            'respectRobots' => 'respect_robots',
        ]);

        return DiscoverResult::fromArray($this->post('/v2/discover', $body));
    }

    // ------------------------------------------------------------------
    // Lookup — web search with optional auto-perceive
    // ------------------------------------------------------------------

    /**
     * Run a categorized web search. With perceiveTop > 0, the top-N result
     * URLs are auto-perceived (each consumes one perceive-quota unit) and
     * carry their full PerceiveResult inline.
     *
     * @param array<string, mixed> $opts
     */
    public function lookup(string $query, array $opts = []): LookupResult
    {
        $body = ['query' => $query];
        Support::copyIfSet($body, $opts, [
            'category' => 'category',
            'country' => 'country',
            'locale' => 'locale',
            'timeFilter' => 'time_filter',
            'numResults' => 'num_results',
            'page' => 'page',
            'location' => 'location',
            'autocorrect' => 'autocorrect',
            'perceiveTop' => 'perceive_top',
        ]);

        return LookupResult::fromArray($this->post('/v2/lookup', $body));
    }

    // ------------------------------------------------------------------
    // Distill — schema-driven structured extraction
    // ------------------------------------------------------------------

    /**
     * Extract structured data matching `schema` from explicit URLs or from
     * a discovered site. An optional cssSchema answers fields for free;
     * anything it misses escalates to the LLM tier (plan-gated).
     *
     * @param array<string, mixed> $opts
     */
    public function distill(array $opts): DistillResult
    {
        $hasUrls = isset($opts['urls']) && is_array($opts['urls']) && count($opts['urls']) > 0;
        $hasDiscover = Support::isSet($opts, 'discoverFrom');
        if ($hasUrls === $hasDiscover) {
            throw new EnconvertException("distill: provide exactly one of 'urls' or 'discoverFrom'");
        }
        if (!isset($opts['schema']) || !is_array($opts['schema'])) {
            throw new EnconvertException("distill: 'schema' is required and must be an object");
        }

        $body = ['schema' => $opts['schema']];
        if ($hasUrls) {
            $body['urls'] = $opts['urls'];
        }
        if (Support::isSet($opts, 'discoverFrom')) {
            $discoverFrom = $opts['discoverFrom'];
            $df = ['url' => $discoverFrom['url']];
            Support::copyIfSet($df, $discoverFrom, [
                'mode' => 'mode',
                'maxPages' => 'max_pages',
            ]);
            $body['discover_from'] = $df;
        }
        if (Support::isSet($opts, 'cssSchema')) {
            $body['css_schema'] = self::serializeCssSchema($opts['cssSchema']);
        }
        Support::copyIfSet($body, $opts, [
            'waitFor' => 'wait_for',
            'waitTimeoutMs' => 'wait_timeout_ms',
            'headers' => 'headers',
            'cookies' => 'cookies',
            'respectRobots' => 'respect_robots',
        ]);

        return DistillResult::fromArray($this->post('/v2/distill', $body));
    }

    // ------------------------------------------------------------------
    // Ingest — site to RAG-ready JSONL chunks (always async)
    // ------------------------------------------------------------------

    /**
     * Start an ingest job: turn explicit URLs or a discovered site into
     * chunked, RAG-ready JSONL. Always asynchronous — returns the queued
     * job; poll getIngestJob or configure webhookUrl for completion.
     *
     * @param array<string, mixed> $opts
     */
    public function ingest(array $opts): IngestJob
    {
        $mode = $opts['mode'] ?? 'urls';
        if ($mode === 'urls') {
            if (empty($opts['urls'])) {
                throw new EnconvertException("ingest: mode 'urls' requires a non-empty 'urls' list");
            }
            if (Support::isSet($opts, 'url')) {
                throw new EnconvertException("ingest: mode 'urls' does not accept 'url'");
            }
        } else {
            if (empty($opts['url'])) {
                throw new EnconvertException("ingest: mode '{$mode}' requires a seed 'url'");
            }
            if (Support::isSet($opts, 'urls')) {
                throw new EnconvertException("ingest: mode '{$mode}' does not accept 'urls'");
            }
        }

        $body = [];
        Support::copyIfSet($body, $opts, [
            'mode' => 'mode',
            'url' => 'url',
            'urls' => 'urls',
            'maxPages' => 'max_pages',
            'maxDepth' => 'max_depth',
            'sameDomainOnly' => 'same_domain_only',
            'includePatterns' => 'include_patterns',
            'excludePatterns' => 'exclude_patterns',
            'respectRobots' => 'respect_robots',
            'waitFor' => 'wait_for',
            'waitTimeoutMs' => 'wait_timeout_ms',
        ]);
        if (Support::isSet($opts, 'chunk')) {
            $chunk = [];
            Support::copyIfSet($chunk, $opts['chunk'], [
                'maxWords' => 'max_words',
                'sentenceOverlap' => 'sentence_overlap',
            ]);
            $body['chunk'] = $chunk;
        }
        if (Support::isSet($opts, 'webhookUrl')) {
            $body['webhook_url'] = $opts['webhookUrl'];
        }

        return IngestJob::fromArray($this->post('/v2/ingest', $body));
    }

    /**
     * Ingest one or more uploaded FILES into RAG-ready JSONL chunks — the file
     * counterpart of ingest(), sharing the same job lifecycle (mode "files").
     * PDF, DOCX, PPTX, XLSX, CSV, HTML, EPUB, TXT/MD and legacy/ODF office are
     * accepted. Always asynchronous; poll getIngestJob or configure a webhook.
     *
     * @param array<int, string|array{data: string, filename: string, contentType?: string}> $files
     * @param array{chunk?: array{maxWords?: int, sentenceOverlap?: int}, webhookUrl?: string} $opts
     */
    public function ingestFiles(array $files, array $opts = []): IngestJob
    {
        if (count($files) === 0) {
            throw new EnconvertException('ingestFiles: provide at least one file');
        }

        $multipart = [];
        foreach ($files as $file) {
            $part = Support::toFilePart($file);
            $multipart[] = [
                'name' => 'files',
                'contents' => $part['bytes'],
                'filename' => $part['filename'],
                'headers' => ['Content-Type' => $part['contentType']],
            ];
        }
        if (Support::isSet($opts, 'chunk')) {
            if (Support::isSet($opts['chunk'], 'maxWords')) {
                $multipart[] = ['name' => 'max_words', 'contents' => (string) $opts['chunk']['maxWords']];
            }
            if (Support::isSet($opts['chunk'], 'sentenceOverlap')) {
                $multipart[] = ['name' => 'sentence_overlap', 'contents' => (string) $opts['chunk']['sentenceOverlap']];
            }
        }
        if (Support::isSet($opts, 'webhookUrl')) {
            $multipart[] = ['name' => 'webhook_url', 'contents' => $opts['webhookUrl']];
        }

        $resp = ($this->request)('POST', '/v2/ingest/files', ['multipart' => $multipart]);
        Support::raiseForStatus($resp);
        $decoded = json_decode((string) $resp->getBody(), true);

        return IngestJob::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * List ingest jobs, newest first.
     *
     * @param array{skip?: int, limit?: int} $opts
     */
    public function listIngestJobs(array $opts = []): IngestJobList
    {
        $d = $this->get('/v2/ingest' . self::listQuery($opts));
        $jobs = is_array($d['jobs'] ?? null) ? $d['jobs'] : [];

        return new IngestJobList(
            jobs: array_map(
                static fn (mixed $j): IngestJobSummary => IngestJobSummary::fromArray(is_array($j) ? $j : []),
                $jobs
            ),
            skip: Support::num($d['skip'] ?? null),
            limit: Support::num($d['limit'] ?? null, 20),
            hasMore: ($d['has_more'] ?? null) === true,
        );
    }

    /** Get one ingest job by id (`ing_...`). */
    public function getIngestJob(string $jobId): IngestJob
    {
        return IngestJob::fromArray($this->get('/v2/ingest/' . rawurlencode($jobId)));
    }

    /**
     * Cancel a queued/processing ingest job. Idempotent: canceling an
     * already-terminal job returns it unchanged.
     */
    public function cancelIngestJob(string $jobId): IngestJob
    {
        return IngestJob::fromArray($this->json('DELETE', '/v2/ingest/' . rawurlencode($jobId)));
    }

    /**
     * Re-deliver the completion webhook of a completed job (409 if the job
     * is not completed, 400 if it has no webhook configured).
     */
    public function retryIngestWebhook(string $jobId): WebhookRetryResult
    {
        $d = $this->post('/v2/ingest/' . rawurlencode($jobId) . '/retry-webhook', null);

        return new WebhookRetryResult(
            jobId: Support::str($d['job_id'] ?? null),
            delivered: ($d['delivered'] ?? null) === true,
            attempts: Support::num($d['attempts'] ?? null),
            statusCode: Support::optNum($d['status_code'] ?? null),
            detail: Support::str($d['detail'] ?? null),
        );
    }

    /**
     * Get (creating on first call) the project's webhook signing secret and
     * the header/scheme details needed to verify deliveries.
     */
    public function getWebhookSecret(): WebhookSecret
    {
        return WebhookSecret::fromArray($this->get('/v2/ingest/webhook-secret'));
    }

    /**
     * Rotate the webhook signing secret. Signatures made with the previous
     * secret stop verifying immediately.
     */
    public function rotateWebhookSecret(): WebhookSecret
    {
        return WebhookSecret::fromArray($this->post('/v2/ingest/webhook-secret/rotate', null));
    }

    // ------------------------------------------------------------------
    // Watch — recurring change monitoring
    // ------------------------------------------------------------------

    /**
     * Create a watcher that re-renders `url` on a fixed cadence (hourly
     * floor) and notifies on changes via email and/or webhook.
     *
     * @param array<string, mixed> $opts
     */
    public function createWatcher(string $url, array $opts = []): Watcher
    {
        $body = ['url' => $url];
        Support::copyIfSet($body, $opts, [
            'frequencyMinutes' => 'frequency_minutes',
            'diffMode' => 'diff_mode',
            'trackFields' => 'track_fields',
            'webhookUrl' => 'webhook_url',
            'notifyEmail' => 'notify_email',
        ]);

        return Watcher::fromArray($this->post('/v2/watch', $body));
    }

    /**
     * List watchers, newest first.
     *
     * @param array{skip?: int, limit?: int} $opts
     */
    public function listWatchers(array $opts = []): WatcherList
    {
        $d = $this->get('/v2/watch' . self::listQuery($opts));
        $watchers = is_array($d['watchers'] ?? null) ? $d['watchers'] : [];

        return new WatcherList(
            watchers: array_map(
                static fn (mixed $w): WatcherSummary => WatcherSummary::fromArray(is_array($w) ? $w : []),
                $watchers
            ),
            skip: Support::num($d['skip'] ?? null),
            limit: Support::num($d['limit'] ?? null, 20),
            hasMore: ($d['has_more'] ?? null) === true,
        );
    }

    /** Get one watcher by id (`wat_...`). Deleted watchers read as 404. */
    public function getWatcher(string $watcherId): Watcher
    {
        return Watcher::fromArray($this->get('/v2/watch/' . rawurlencode($watcherId)));
    }

    /**
     * Page through a watcher's check history, newest first.
     *
     * @param array{limit?: int} $opts
     */
    public function getWatcherSnapshots(string $watcherId, array $opts = []): WatcherSnapshotList
    {
        $query = Support::isSet($opts, 'limit') ? '?limit=' . $opts['limit'] : '';
        $d = $this->get('/v2/watch/' . rawurlencode($watcherId) . '/snapshots' . $query);
        $snapshots = is_array($d['snapshots'] ?? null) ? $d['snapshots'] : [];

        return new WatcherSnapshotList(
            watcherId: Support::str($d['watcher_id'] ?? null),
            snapshots: array_map(
                static fn (mixed $s): WatcherSnapshot => WatcherSnapshot::fromArray(is_array($s) ? $s : []),
                $snapshots
            ),
            limit: Support::num($d['limit'] ?? null, 20),
        );
    }

    /**
     * Update a watcher. At least one field is required. Set webhookUrl to
     * "" to clear the webhook; resuming a paused watcher re-checks the
     * plan's watcher cap.
     *
     * @param array<string, mixed> $updates
     */
    public function updateWatcher(string $watcherId, array $updates): Watcher
    {
        $body = [];
        Support::copyIfSet($body, $updates, [
            'frequencyMinutes' => 'frequency_minutes',
            'diffMode' => 'diff_mode',
            'trackFields' => 'track_fields',
            'webhookUrl' => 'webhook_url',
            'notifyEmail' => 'notify_email',
            'status' => 'status',
        ]);
        if (count($body) === 0) {
            throw new EnconvertException('updateWatcher: provide at least one field to update');
        }

        return Watcher::fromArray($this->json('PATCH', '/v2/watch/' . rawurlencode($watcherId), $body));
    }

    /**
     * Soft-delete a watcher (idempotent). Returns the tombstoned watcher
     * with status "deleted".
     */
    public function deleteWatcher(string $watcherId): Watcher
    {
        return Watcher::fromArray($this->json('DELETE', '/v2/watch/' . rawurlencode($watcherId)));
    }

    // ------------------------------------------------------------------
    // HTTP helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null): array
    {
        $options = $body !== null ? ['json' => $body] : [];
        $resp = ($this->request)($method, $path, $options);
        Support::raiseForStatus($resp);
        $decoded = json_decode((string) $resp->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function post(string $path, ?array $body): array
    {
        return $this->json('POST', $path, $body);
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->json('GET', $path);
    }

    // ------------------------------------------------------------------
    // Request serializers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    private static function serializePerceiveOptions(array $o): array
    {
        $out = [];
        Support::copyIfSet($out, $o, [
            'outputs' => 'outputs',
            'extract' => 'extract',
            'schema' => 'schema',
            'waitFor' => 'wait_for',
            'waitTimeoutMs' => 'wait_timeout_ms',
            'jsCode' => 'js_code',
            'viewport' => 'viewport',
            'headers' => 'headers',
            'cookies' => 'cookies',
            'auth' => 'auth',
            'proxyUrl' => 'proxy_url',
            'geolocation' => 'geolocation',
            'actionChain' => 'action_chain',
            'cacheMode' => 'cache_mode',
            'blockResources' => 'block_resources',
            'respectRobots' => 'respect_robots',
            'mobile' => 'mobile',
        ]);
        if (Support::isSet($o, 'pdfOptions')) {
            $out['pdf_options'] = Support::serializePdfOptions($o['pdfOptions']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private static function serializeCssField(array $f): array
    {
        $out = ['name' => $f['name'], 'type' => $f['type']];
        Support::copyIfSet($out, $f, [
            'selector' => 'selector',
            'attribute' => 'attribute',
            'pattern' => 'pattern',
            'default' => 'default',
            'transform' => 'transform',
        ]);
        if (Support::isSet($f, 'fields')) {
            $out['fields'] = array_map([self::class, 'serializeCssField'], $f['fields']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private static function serializeCssSchema(array $s): array
    {
        // NB: mirrors node-sdk's v2.ts verbatim — the wire field is
        // "baseSelector" (not snake_case) while "targetField" IS converted to
        // "target_field". This asymmetry exists in the source of truth.
        $out = [
            'baseSelector' => $s['baseSelector'],
            'fields' => array_map([self::class, 'serializeCssField'], $s['fields']),
        ];
        if (Support::isSet($s, 'name')) {
            $out['name'] = $s['name'];
        }
        if (Support::isSet($s, 'targetField')) {
            $out['target_field'] = $s['targetField'];
        }

        return $out;
    }

    /** @param array{skip?: int, limit?: int} $opts */
    private static function listQuery(array $opts): string
    {
        $params = [];
        if (Support::isSet($opts, 'skip')) {
            $params['skip'] = (string) $opts['skip'];
        }
        if (Support::isSet($opts, 'limit')) {
            $params['limit'] = (string) $opts['limit'];
        }

        return count($params) > 0 ? '?' . http_build_query($params) : '';
    }
}
