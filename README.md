# Enconvert PHP SDK

Honest eyes for your AI agent — the PHP SDK for [Enconvert](https://enconvert.com). PHP 8.1+.

Read any web page or file into clean Markdown, JSON, or screenshots, and get a `render_quality` score (0.0–1.0) on **every** read — so a blocked, challenge, or empty-SPA page comes back flagged with a low score and warnings, never mistaken for real content. Perceive, discover, look up, distill, ingest, and watch the web; convert 40+ file and document formats through the same key.

> Wiring an agent (Claude, Cursor, Windsurf, n8n, …)? The [MCP server](https://enconvert.com/mcp) is the native path — `npx @enconvert/mcp setup`. This SDK is the programmatic REST path for everything else.

## Install

```bash
composer require enconvert/enconvert-php
```

## Quick Start

```php
use Enconvert\Client;

$client = new Client('sk_...');

// Read a page the way your agent should — with a quality score attached.
$op = $client->v2->perceive('https://example.com', [
    'outputs' => ['markdown', 'structured'],
]);
echo $op->outputs['markdown']->url, ' ', $op->renderQuality; // e.g. 0.93
```

---

# V2 — agent-ready data (`$client->v2`)

The V2 namespace turns web pages into agent-ready data: render, search, extract, ingest, and monitor. All V2 endpoints require a **private API key** and are plan-gated — a disabled feature or exhausted monthly quota throws `QuotaException` (HTTP 402). `$client->v2` is a public property; `$client->v2()` is an equivalent accessor method.

Every render carries `renderQuality` (0.0–1.0). A low score means the page didn't render cleanly (challenge page, cookie wall, empty shell); the content is still returned, flagged, so a bad read never quietly enters your agent's context.

### Perceive — render a URL into artifacts

```php
$op = $client->v2->perceive('https://example.com', [
    'outputs' => ['markdown', 'screenshot', 'structured'],
    'extract' => ['tables', 'metadata'],
]);
echo $op->renderQuality;              // honesty score, 0.0–1.0
echo $op->outputs['markdown']->url;   // 15-min signed URL
print_r($op->structured);

// Re-sign artifact URLs later:
$again = $client->v2->getPerceiveOperation($op->operationId);

// Batch (<=1000 URLs; small batches run inline, larger return "queued" — poll):
$batch = $client->v2->perceiveBatch(['https://a.com', 'https://b.com'], [
    'outputs' => ['markdown'],
    'outputMode' => 'zip',
]);
$done = $client->v2->getPerceiveBatch($batch->jobId);

// Direct download — stream one artifact's raw bytes instead of the JSON envelope
// (exactly one artifact-producing output; metadata arrives via headers):
$direct = $client->v2->perceiveDirect('https://example.com', [
    'outputs' => ['pdf'],
]);
file_put_contents($direct->filename, $direct->content);

// Re-download a stored artifact later (output optional when the operation has only one):
$saved = $client->v2->downloadPerceiveArtifact($direct->operationId, 'pdf');
```

### Discover — enumerate a site's URLs (no rendering)

```php
$found = $client->v2->discover('https://example.com', [
    'mode' => 'hybrid',              // "sitemap" | "crawl" | "hybrid"
    'maxUrls' => 200,
    'excludePatterns' => ['/tag/'],
]);
echo $found->total;
print_r($found->urls);
```

### Lookup — web search with optional auto-perceive

```php
$search = $client->v2->lookup('best static site generators', [
    'category' => 'web',             // web | news | images | scholar | patents | maps
    'numResults' => 10,
    'perceiveTop' => 3,              // auto-render top 3 results (uses perceive quota)
]);
foreach ($search->results as $hit) {
    echo $hit->title, ' ', $hit->url, ' ', $hit->perceive?->renderQuality, "\n";
}
```

### Distill — schema-driven structured extraction

```php
$extraction = $client->v2->distill([
    'urls' => ['https://example.com/pricing'],
    'schema' => ['plans' => 'list of plan names with monthly prices'],
    'cssSchema' => [                 // optional free CSS pass before the LLM tier
        'baseSelector' => '.plan-card',
        'fields' => [
            ['name' => 'name', 'type' => 'text', 'selector' => 'h3'],
            ['name' => 'price', 'type' => 'text', 'selector' => '.price'],
        ],
    ],
]);
print_r($extraction->results[0]->data);
echo $extraction->results[0]->extractionTier;

// Or discover-then-distill:
$client->v2->distill([
    'discoverFrom' => ['url' => 'https://example.com', 'mode' => 'sitemap', 'maxPages' => 10],
    'schema' => ['title' => 'page title', 'summary' => 'one-line summary'],
]);
```

### Ingest — site or files to RAG-ready JSONL (always async)

Turn a whole site — or a set of uploaded documents — into chunked, RAG-ready JSONL through one pipeline.

```php
// From a site:
$job = $client->v2->ingest([
    'mode' => 'sitemap',
    'url' => 'https://docs.example.com',
    'maxPages' => 100,
    'chunk' => ['maxWords' => 512, 'sentenceOverlap' => 1],
    'webhookUrl' => 'https://my.app/hooks/enconvert',
]);

// Or from uploaded files (PDF, DOCX, PPTX, XLSX, CSV, HTML, EPUB, TXT/MD, legacy/ODF office):
$fileJob = $client->v2->ingestFiles(['handbook.pdf', 'notes.docx'], [
    'chunk' => ['maxWords' => 512, 'sentenceOverlap' => 1],
]);

$status = $client->v2->getIngestJob($job->jobId);   // poll
if ($status->status === 'completed') {
    echo $status->outputUrl; // JSONL
}

$client->v2->listIngestJobs(['limit' => 20]);
$client->v2->cancelIngestJob($job->jobId);           // idempotent

// Webhook signing (HMAC):
$secretInfo = $client->v2->getWebhookSecret();
$client->v2->rotateWebhookSecret();                  // invalidates old secret
$client->v2->retryIngestWebhook($job->jobId);        // re-deliver
```

### Watch — recurring change monitoring

```php
$watcher = $client->v2->createWatcher('https://example.com/pricing', [
    'frequencyMinutes' => 60,        // hourly floor
    'diffMode' => 'auto',            // auto | text | structured | tables | metadata
    'webhookUrl' => 'https://my.app/hooks/changes',
    'notifyEmail' => true,
]);

$client->v2->listWatchers();
$client->v2->getWatcher($watcher->watcherId);
$client->v2->getWatcherSnapshots($watcher->watcherId, ['limit' => 10]);
$client->v2->updateWatcher($watcher->watcherId, ['status' => 'paused']);
$client->v2->updateWatcher($watcher->watcherId, ['webhookUrl' => '']); // clears webhook
$client->v2->deleteWatcher($watcher->watcherId);      // soft-delete, idempotent
```

### V2 error handling

```php
use Enconvert\Exception\QuotaException;

try {
    $client->v2->ingest(['urls' => ['https://example.com']]);
} catch (QuotaException $e) {
    echo 'Upgrade plan or wait for quota reset';
}
```

---

# File conversion

The same key also converts 40+ formats. Two "anything → X" endpoints auto-detect the input; the format-specific endpoints below give you a validated, typed path.

### Anything to Markdown / PDF

```php
// Any document → clean Markdown (a RAG-ingestion building block):
$client->convertToMarkdown('report.docx', ['saveTo' => 'report.md']);
// PDF, DOCX, PPTX, XLSX, CSV, HTML, EPUB, TXT/MD, and legacy/ODF office. (Images not supported.)

// Almost anything → PDF:
$client->convertToPdf('slides.pptx', ['saveTo' => 'slides.pdf']);
// office/ODF/Pages/Numbers/RTF/CSV, HTML, Markdown, text, images, SVG, EPUB, or a PDF passthrough.
// Only pdfOptions.grayscale is honored on this endpoint:
$client->convertToPdf('scan.pdf', ['pdfOptions' => ['grayscale' => true], 'saveTo' => 'gray.pdf']);
```

### Image Conversion

```php
$result = $client->convertImage('photo.heic', [
    'outputFormat' => 'webp',
    'saveTo' => 'photo.webp',
]);
```

Any pair among `jpeg`, `png`, `svg`, `heic`, `webp` — plus PDF rasterization:

```php
$client->convertImage('scan.pdf', ['outputFormat' => 'jpeg', 'saveTo' => 'scan.jpeg']);
```

Raw bytes are also accepted, wrapped with an explicit filename (needed to resolve the input format and MIME type):

```php
$client->convertImage(
    ['data' => $rawBytes, 'filename' => 'photo.heic'],
    ['outputFormat' => 'webp', 'saveTo' => 'photo.webp']
);
```

### Document Conversion

```php
$client->convertDocument('report.docx', ['saveTo' => 'report.pdf']);
$client->convertDocument('data.json', ['outputFormat' => 'yaml', 'saveTo' => 'data.yaml']);
$client->convertDocument('notes.md', ['outputFormat' => 'html', 'saveTo' => 'notes.html']);
```

Supported inputs: `doc`/`docx`, `xls`/`xlsx`, `ppt`/`pptx`, `odt`, `ods`, `odp`, `ots`, `pages`, `numbers`, `html`, `markdown`, `csv`, `json`, `xml`, `yaml`, `toml`. (EPUB has no dedicated document pair — use `convertToPdf` / `convertToMarkdown`.)

The SDK validates every `{input}-to-{output}` pair against the conversions the API actually implements and throws immediately — with the list of valid outputs for that input — instead of sending a doomed request. Introspect programmatically:

```php
use Enconvert\Formats;

Formats::validOutputsFor('json'); // ["csv", "toml", "xml", "yaml"]
Formats::validOutputsFor('pdf');  // ["jpeg"]
Formats::IMPLEMENTED_CONVERSIONS; // the full set of 43 "{input}-to-{output}" pairs
```

### Supported conversions

| Input | Outputs |
|-------|---------|
| json | csv, toml, xml, yaml |
| xml | csv, json |
| yaml | json |
| csv | json, xml |
| toml | json |
| markdown | html, pdf |
| html | pdf |
| doc, excel, ppt, odt, ods, odp, ots, pages, numbers | pdf |
| jpeg, png, svg, heic, webp | each other (all 20 pairs) |
| pdf | jpeg |

### URL to PDF / Screenshot / Markdown

```php
$client->convertUrlToPdf('https://example.com', ['saveTo' => 'page.pdf']);
$client->convertUrlToScreenshot('https://example.com', ['viewportWidth' => 1440, 'saveTo' => 'shot.png']);
$client->convertUrlToMarkdown('https://example.com/article', ['saveTo' => 'article.md']);
```

### Website to PDF / Screenshot (whole-site batch)

Discover every page of a website (via sitemap, or full crawl on Pro/Business plans), convert each one in the background, and receive a single ZIP. Requires a private API key with crawl access.

```php
$batch = $client->convertWebsiteToPdf('https://example.com', [
    'crawlMode' => 'sitemap',            // "auto" (default) | "sitemap" | "full"
    'excludePatterns' => ['/blog/tag/'], // full crawl mode only
]);
echo $batch->batchId, ' ', $batch->urlCount, ' ', $batch->discoveryMethod;

// Block until done and save the ZIP:
$status = $client->waitForBatch($batch->batchId, ['saveTo' => 'site.zip']);
echo $status->completed, ' of ', $status->total, ' pages converted';

// Or poll yourself:
$s = $client->getBatchStatus($batch->batchId);
if ($s->status !== 'processing') {
    echo $s->zipDownloadUrl;
}
```

`convertWebsiteToScreenshot` works the same way and produces a ZIP of PNGs.

### PDF Options & Authenticated Pages

```php
$result = $client->convertUrlToPdf('https://example.com', [
    'pdfOptions' => [
        'pageSize' => 'A4',              // or custom dimensions via pageWidth + pageHeight
        'orientation' => 'landscape',
        'margins' => ['top' => 10, 'bottom' => 10, 'left' => 15, 'right' => 15],
        'header' => ['content' => 'Quarterly Report', 'height' => 15],
        'footer' => ['content' => 'Confidential', 'height' => 12],
    ],
    'saveTo' => 'report.pdf',
]);
```

All URL and website conversions accept HTTP Basic Auth, cookies, and custom headers for pages behind a login:

```php
$client->convertUrlToPdf('https://internal.example.com/report', [
    'auth' => ['username' => 'user', 'password' => 'pass'],
    // or cookies / headers:
    'cookies' => [['name' => 'session', 'value' => 'abc123', 'domain' => 'internal.example.com']],
    'headers' => ['X-Tenant' => 'acme'],
    'saveTo' => 'report.pdf',
]);
```

Do not combine `auth` with an `Authorization` header — the API rejects the conflict.

### Job Status (async polling)

```php
$status = $client->getJobStatus('job_abc123');
if ($status->status === 'success') {
    echo $status->presignedUrl;
}
```

---

## Error Handling

```php
use Enconvert\Client;
use Enconvert\Exception\ApiException;
use Enconvert\Exception\AuthenticationException;
use Enconvert\Exception\RateLimitException;

try {
    $client->convertUrlToPdf('https://example.com');
} catch (AuthenticationException $e) {
    echo 'Invalid API key';
} catch (RateLimitException $e) {
    echo 'Too many requests — slow down';
} catch (ApiException $e) {
    echo "API error [{$e->getStatusCode()}]: {$e->getMessage()}";
}
```

`AuthenticationException`, `QuotaException`, and `RateLimitException` all extend `ApiException`, which extends the base `EnconvertException`.

## Configuration

```php
$client = new Client('sk_...', [
    'timeout' => 300,   // seconds, default (idiomatic PHP/Guzzle unit; node-sdk uses milliseconds)
    'base_url' => 'https://api.enconvert.com', // override, e.g. for a self-hosted gateway
]);
```

## Get an API Key

Sign up at [enconvert.com](https://enconvert.com) to get your API key.

## License

MIT
