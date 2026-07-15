# enconvert-php

PHP SDK for the [Enconvert](https://enconvert.com) file conversion API.

Convert URLs to PDFs, capture screenshots, extract Markdown, crawl whole websites, transform images, and convert documents — all with a single API call. PHP 8.1+.

## Install

```bash
composer require enconvert/enconvert-php
```

## Quick Start

```php
use Enconvert\Client;

$client = new Client('sk_...');
```

### URL to PDF

```php
$result = $client->convertUrlToPdf('https://example.com', [
    'saveTo' => 'page.pdf',
]);
echo $result->presignedUrl;
```

### URL to Screenshot

```php
$result = $client->convertUrlToScreenshot('https://example.com', [
    'viewportWidth' => 1440,
    'saveTo' => 'screenshot.png',
]);
```

### URL to Markdown

Extract clean GitHub-Flavored Markdown from any URL — strips nav/footer/ads/scripts, keeps the main article content, and adds YAML frontmatter (title, description, url, links, images).

```php
$result = $client->convertUrlToMarkdown('https://example.com/article', [
    'saveTo' => 'article.md',
]);
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

Supported inputs: `doc`/`docx`, `xls`/`xlsx`, `ppt`/`pptx`, `odt`, `ods`, `odp`, `ots`, `pages`, `numbers`, `epub`, `html`, `markdown`, `csv`, `json`, `xml`, `yaml`, `toml`

The SDK validates every `{input}-to-{output}` pair against the conversions the API actually implements and throws immediately — with the list of valid outputs for that input — instead of sending a doomed request. Introspect programmatically:

```php
use Enconvert\Formats;

Formats::validOutputsFor('json'); // ["csv", "toml", "xml", "yaml"]
Formats::validOutputsFor('pdf');  // ["jpeg"]
Formats::IMPLEMENTED_CONVERSIONS; // the full set of 44 "{input}-to-{output}" pairs
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
| doc, excel, ppt, odt, ods, odp, ots, pages, numbers, epub | pdf |
| jpeg, png, svg, heic, webp | each other (all 20 pairs) |
| pdf | jpeg |

### Job Status (async polling)

```php
$status = $client->getJobStatus('job_abc123');
if ($status->status === 'success') {
    echo $status->presignedUrl;
}
```

## PDF Options

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

## Authenticated Pages (plan-gated)

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

## V2 API (`$client->v2`)

The V2 namespace turns web pages into agent-ready data: render, search, extract, ingest, and monitor. All V2 endpoints require a **private API key** and are plan-gated — a disabled feature or exhausted monthly quota throws `QuotaException` (HTTP 402). `$client->v2` is a public property; `$client->v2()` is an equivalent accessor method.

### Perceive — render a URL into artifacts

```php
$op = $client->v2->perceive('https://example.com', [
    'outputs' => ['markdown', 'screenshot', 'structured'],
    'extract' => ['tables', 'metadata'],
]);
echo $op->outputs['markdown']->url;   // 15-min signed URL
print_r($op->structured);

// Re-sign artifact URLs later:
$again = $client->v2->getPerceiveOperation($op->operationId);

// Batch (<=10 URLs runs inline; larger returns "queued" — poll):
$batch = $client->v2->perceiveBatch(['https://a.com', 'https://b.com'], [
    'outputs' => ['markdown'],
    'outputMode' => 'zip',
]);
$done = $client->v2->getPerceiveBatch($batch->jobId);
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
    echo $hit->title, ' ', $hit->url, "\n";
    if ($hit->perceive !== null) {
        print_r($hit->perceive->outputs);
    }
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

### Ingest — site to RAG-ready JSONL (always async)

```php
$job = $client->v2->ingest([
    'mode' => 'sitemap',
    'url' => 'https://docs.example.com',
    'maxPages' => 100,
    'chunk' => ['maxWords' => 512, 'sentenceOverlap' => 1],
    'webhookUrl' => 'https://my.app/hooks/enconvert',
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

## Get an API Key

Sign up at [enconvert.com](https://enconvert.com) to get your API key.

## License

MIT
