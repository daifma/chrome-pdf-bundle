# ChromePdfBundle

[![Unit tests](https://github.com/daifma/chrome-pdf-bundle/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/daifma/chrome-pdf-bundle/actions/workflows/unit-tests.yml)
[![Static analysis](https://github.com/daifma/chrome-pdf-bundle/actions/workflows/static.yml/badge.svg)](https://github.com/daifma/chrome-pdf-bundle/actions/workflows/static.yml)
[![Latest version](https://img.shields.io/packagist/v/daif/chrome-pdf-bundle?include_prereleases&label=packagist)](https://packagist.org/packages/daif/chrome-pdf-bundle)
[![Total downloads](https://img.shields.io/packagist/dt/daif/chrome-pdf-bundle)](https://packagist.org/packages/daif/chrome-pdf-bundle)
[![PHP version](https://img.shields.io/packagist/dependency-v/daif/chrome-pdf-bundle/php)](https://packagist.org/packages/daif/chrome-pdf-bundle)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207%20%7C%208-black?logo=symfony)](https://symfony.com)
[![Platforms](https://img.shields.io/badge/platforms-Linux%20%7C%20macOS%20%7C%20Windows-blue)](./docs/chrome-installation.md)
[![PHPStan level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](./phpstan.dist.neon)
[![License](https://img.shields.io/packagist/l/daif/chrome-pdf-bundle)](LICENSE)

> Generate, merge, convert and transform PDFs in Symfony with a local Chrome, qpdf, ExifTool and LibreOffice.
> No Docker needed.

ChromePdfBundle is a **Symfony PDF bundle**: it converts **HTML to PDF**, **Twig to PDF**, URLs and Symfony routes to
PDF, and **Markdown to PDF**, and takes PNG, JPEG or WebP **screenshots**, using a **headless Chrome**, **Chromium** or
**Microsoft Edge** that is installed on the server. With **qpdf**, **ExifTool** and **LibreOffice** installed next to
it, it also **merges**, **splits**, **rotates**, **flattens**, **encrypts** and **watermarks** PDFs, attaches files,
writes metadata, produces **PDF/A** and converts **Office documents (Word, Excel, PowerPoint, ODF) to PDF**. Each tool
is optional: you install only what the features you use need.

There is **no Docker**, **no Node.js**, no Gotenberg server and no wkhtmltopdf binary to install, and it runs on Linux,
macOS and Windows. The builder API follows sensiolabs/gotenberg-bundle, so it works as a **Gotenberg alternative**
when you cannot run containers, and as a **wkhtmltopdf alternative** (KnpSnappyBundle) or **Browsershot alternative**
when you need current CSS (flexbox, grid, web fonts) and JavaScript in your PDFs.

## Quick start

```bash
composer require daif/chrome-pdf-bundle
```

```php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

public function invoice(ChromePdfInterface $chromePdf): Response
{
    return $chromePdf->html()
        ->content('invoice.html.twig', ['invoice' => $invoice])
        ->fileName('invoice')
        ->generate()
        ->stream()
    ;
}
```

The browser is detected automatically (`CHROME_PATH`, then the usual install locations, Windows included). If PHP runs
as `root`, read the [sandbox note](#minimal-configuration) first. Full details are in [How to install](#how-to-install)
and [Basic Usage](#basic-usage).

Merging, splitting, Office conversion and the other PDF features need qpdf, ExifTool or LibreOffice: see
[PDF engines](./docs/engines.md).

## How it works

ChromePdfBundle uses the builder API of
[sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle) (`html()`, `url()`, `markdown()`,
`office()`, `merge()`, ..., `->generate()->stream()`, processors, Twig asset helpers). GotenbergBundle sends each
request to a Gotenberg container over HTTP, and Gotenberg runs Chromium, LibreOffice and PDF engines inside it. This
bundle runs the equivalent tools on the same machine instead:

- **Chrome, Chromium or Edge** renders HTML, controlled through the
  [Chrome DevTools Protocol](https://chromedevtools.github.io/devtools-protocol/) (CDP) with
  [chrome-php/chrome](https://github.com/chrome-php/chrome).
- **[qpdf](https://qpdf.readthedocs.io/)** merges, splits, rotates, flattens, encrypts, attaches files, applies
  watermarks and stamps, and writes bookmarks.
- **[ExifTool](https://exiftool.org/)** writes metadata.
- **[LibreOffice](https://www.libreoffice.org/)** converts Office documents and produces PDF/A and PDF/UA.

qpdf, ExifTool and LibreOffice are started as short-lived processes when a feature needs them, and found
automatically like Chrome. Optionally, `generateAsync()` and webhooks can go through
[Symfony Messenger](./docs/async.md) (disabled by default).

## Why this bundle?

I wanted to use [sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle). It is a complete Symfony
bundle for PDF generation. But it needs [Gotenberg](https://gotenberg.dev/), which runs as a **Docker container**.

I work in **on-premise environments** (banking, insurance, other regulated industries), where production servers
often cannot run Docker because of security policies or infrastructure rules.

These machines usually have a browser installed already, or can get one from the standard package manager or the
vendor's MSI. Microsoft Edge is preinstalled on Windows Server 2019 and later. qpdf, ExifTool and LibreOffice are
also available from the standard package managers (apt, dnf, apk, Homebrew, Chocolatey, winget) or as MSI installers.

So this bundle keeps the same builder API and calls these local tools directly. There is no container and no extra
HTTP service to run.

| Gotenberg-based setup | ChromePdfBundle |
|---|---|
| Docker + a running Gotenberg container | A Chrome / Chromium / Edge binary, plus qpdf, ExifTool and LibreOffice if you need their features |
| HTTP calls to an external service | Local CDP connection (WebSocket) and local processes |
| Chromium, LibreOffice and PDF engines (pdfcpu, qpdf, ExifTool) in the container | The same kinds of tools, installed on the host (see [feature parity](#feature-parity-with-sensiolabsgotenberg-bundle)) |
| Webhooks called by Gotenberg | Optional `generateAsync()` through Symfony Messenger (opt-in), the worker calls the webhook |
| LibreOffice kept running by Gotenberg | One LibreOffice process per conversion, slower to start |

### Compared with other PHP and Symfony PDF tools

The table below lists the options people usually consider for HTML to PDF in Symfony. Information was checked
against each project's README and repository in October 2026.

| Package | Type | Needs at runtime | Rendering | Notes |
|---|---|---|---|---|
| **daif/chrome-pdf-bundle** | Symfony bundle | A Chrome, Chromium or Edge binary; qpdf, ExifTool and LibreOffice for the PDF and Office features | The installed Chromium and LibreOffice versions | GotenbergBundle-style builders for PDF and screenshots from HTML, Twig, URL and Markdown, plus merge, split, rotate, flatten, encrypt, attachments, watermark, metadata, PDF/A and Office to PDF. |
| [sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle) | Symfony bundle | A running [Gotenberg](https://gotenberg.dev/) service, distributed as a Docker image | Chromium and LibreOffice inside Gotenberg | Same builder API. Gotenberg keeps LibreOffice running and ships every tool in one tested image, so it is faster for Office conversions and simpler to install when you can run Docker. |
| [knplabs/knp-snappy-bundle](https://github.com/KnpLabs/KnpSnappyBundle) | Symfony bundle | The wkhtmltopdf binary | Qt WebKit | The [wkhtmltopdf](https://github.com/wkhtmltopdf/wkhtmltopdf) project is archived and no longer maintained. Its old WebKit engine has weak support for flexbox, grid and recent JavaScript. |
| [spatie/browsershot](https://github.com/spatie/browsershot) | PHP library | Node.js, npm and Puppeteer | Chromium through Puppeteer | Not a Symfony bundle: no configuration, Twig or response integration out of the box. |
| [padam87/rasterize-bundle](https://github.com/Padam87/RasterizeBundle) | Symfony bundle | Node.js and Puppeteer (default provider) | Chromium through Puppeteer | Renders HTML you pass in; needs a Node.js toolchain on the server. |
| [dreadnip/chrome-pdf-bundle](https://github.com/sanderdlm/chrome-pdf-bundle) | Symfony bundle | A Chrome or Chromium binary | Chromium through chrome-php/chrome | One `PdfGenerator` service that writes an HTML string to a PDF file, with raw print options. No URL, Markdown or screenshot builders. |
| [utafitilabs/pdf-scribe-bundle](https://github.com/utafitilabs/pdf-scribe-bundle) | Symfony bundle | A Chrome or Chromium binary, PHP 8.4, Symfony 7.3+ | Chromium | One `PdfGeneratorInterface` service for HTML or URL to PDF. No screenshots or Markdown. |
| [dompdf](https://github.com/dompdf/dompdf), [mPDF](https://github.com/mpdf/mpdf), [TCPDF](https://github.com/tecnickcom/TCPDF) | PHP libraries | PHP only | Their own layout engine | Mostly CSS 2.1: no flexbox, no grid, no JavaScript. TCPDF is marked deprecated in favour of tc-lib-pdf. |
| [chrome-php/chrome](https://github.com/chrome-php/chrome) | PHP library | A Chrome or Chromium binary | Chromium | The library this bundle is built on. No Symfony configuration, Twig rendering, builders or HTTP responses. |

There are already Symfony bundles that drive a local Chrome without Docker, but to our knowledge no other Symfony
bundle combines that with the GotenbergBundle builder API: HTML, URL and Markdown builders for both PDFs and
screenshots, PDF manipulation and Office conversion with local tools, processors (file, S3, Flysystem), async
generation with webhooks, Twig asset helpers, a web profiler panel, request filtering for `file://` and remote URLs,
and auto-detection of the browser and the tools on Windows. If you know of one, please
[open an issue](https://github.com/daifma/chrome-pdf-bundle/issues) so this table can be corrected.

## How to install

### Requirements

- PHP 8.1+ with **`ext-sockets`** (chrome-php needs it to talk to the browser over a socket)
- Symfony 6.4 / 7.x / 8.x
- `ext-zip` (only used when a builder returns several files as a ZIP archive)
- One of Google Chrome, Chromium or Microsoft Edge (Chromium-based) on the same machine. See the
  [Chrome installation guide](./docs/chrome-installation.md) for Linux, macOS and Windows.
- Optional, only for the features that need them: qpdf, ExifTool, LibreOffice. See [PDF engines](./docs/engines.md).

```bash
composer require daif/chrome-pdf-bundle
```

### Enable the bundle

If you don't use Symfony Flex, register the bundle yourself:

```php
// config/bundles.php

return [
    // ...
    Daif\ChromePdfBundle\DaifChromePdfBundle::class => ['all' => true],
];
```

### Minimal configuration

```yaml
# config/packages/daif_chrome_pdf.yaml

daif_chrome_pdf:
    assets_directory: '%kernel.project_dir%/assets'
    # chrome_binary: '%env(default::CHROME_PATH)%'   # optional, auto-detected when null
    # timeout: 30000                                 # ms
    # qpdf_binary: ~, exiftool_binary: ~, libreoffice_binary: ~   # optional, auto-detected when null
```

The bundle detects the browser automatically: first the `CHROME_PATH` environment variable, then the usual install
locations for Chrome, Chromium and Edge (Windows paths included). See the
[configuration reference](./docs/configuration.md) for every option.

> [!IMPORTANT]
> Chrome's sandbox is **enabled** by default. If PHP runs as `root` (common in containers and on some CI runners),
> Chrome will not start unless you set `no_sandbox: true`. Read [security](./docs/security.md) before you change it.

## Basic Usage

### PDF from a Twig template

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController
{
    public function invoice(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->content('invoice.html.twig', ['invoice' => $invoice])
            ->fileName('invoice')
            ->generate()
            ->stream()
        ;
    }
}
```

### PDF from raw HTML

```php
return $chromePdf->html()
    ->contentRaw('<!DOCTYPE html><html><body><h1>Hello</h1></body></html>')
    ->generate()
    ->stream()
;
```

### PDF from a URL or a Symfony route

```php
return $chromePdf->url()
    ->url('https://example.com/report')     // or ->route('report_show', ['id' => 42])
    ->generate()
    ->stream()
;
```

### PDF from Markdown

```php
return $chromePdf->markdown()
    ->wrapper('wrapper.html.twig')   // contains {% verbatim %}{{ toHTML "content.md" }}{% endverbatim %}
    ->files('content.md')
    ->generate()
    ->stream()
;
```

### Screenshot

```php
use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;

return $chromeScreenshot->html()
    ->content('page.html.twig')
    ->width(1280)
    ->format(ScreenshotFormat::Png)
    ->generate()
    ->stream()   // Content-Type: image/png
;
```

### Merge PDFs

```php
return $chromePdf->merge()
    ->files('1-cover.pdf', '2-report.pdf')   // needs qpdf; merged in file name order
    ->generate()
    ->stream()
;
```

### Office document to PDF

```php
return $chromePdf->office()
    ->files('contract.docx')             // needs LibreOffice
    ->generate()
    ->stream()
;
```

### Post-processing a generated PDF

```php
use Daif\ChromePdfBundle\Enumeration\PdfFormat;

return $chromePdf->html()
    ->content('invoice.html.twig', ['invoice' => $invoice])
    ->pdfFormat(PdfFormat::Pdf3b)        // LibreOffice
    ->metadata(['Author' => 'ACME'])     // ExifTool
    ->embedFiles('factur-x.xml')         // qpdf
    ->generate()
    ->stream()
;
```

### Generate in the background and send the file to a webhook (optional)

Disabled by default. Install `symfony/messenger` and `symfony/http-client`, set `daif_chrome_pdf.async.enabled: true`,
then:

```php
$chromePdf->html()
    ->content('report.html.twig')
    ->webhookUrl('https://example.com/webhook/pdf-ready')
    ->generateAsync();                   // LogicException unless async is enabled
```

### Twig assets

To link a static asset (image, CSS, font) from a template, use `chrome_pdf_asset()`:

```twig
<img src="{{ chrome_pdf_asset('img/logo.png') }}" alt="Logo"/>
```

## Feature parity with sensiolabs/gotenberg-bundle

The table compares this bundle with GotenbergBundle 1.4+. Chrome renders HTML and prints it to PDF. The features that
need a PDF engine or LibreOffice call local tools instead of Gotenberg's container. The "Needs" column says which one;
a missing tool only disables the features that use it, with an `EngineException` telling what to install.

**Legend:** ✅ supported · ⚠️ supported with differences · ❌ not supported

### Builders

| GotenbergBundle | Status | Needs | Notes |
|---|---|---|---|
| `html()` PDF | ✅ | Chrome | |
| `url()` PDF (`url()`, `route()`) | ⚠️ | Chrome | Only `http` / `https` targets are accepted, and they go through the [request filter](./docs/security.md#request-filtering) (`allow_list` / `deny_list`). |
| `markdown()` PDF | ⚠️ | Chrome | Markdown is converted in PHP with league/commonmark (Gotenberg uses its own converter). The `{{ toHTML "file.md" }}` directive is supported, also with flexible spacing and quotes. |
| `html()` / `url()` / `markdown()` screenshots | ⚠️ | Chrome | Full page by default, like Gotenberg. `clip(true)` captures only the viewport. |
| `office()` (Office to PDF) | ⚠️ | LibreOffice | Same options. One `soffice` process per document, so each conversion pays LibreOffice's start-up time. See [LibreOfficePdfBuilder](./docs/pdf/LibreOfficePdfBuilder.md#differences-from-gotenbergbundle). |
| `merge()` | ⚠️ | qpdf | Files are merged in file name order, as in Gotenberg. Bookmarks need qpdf 11+. `autoIndexBookmarks()` adds one bookmark per file but does not import the bookmarks of the inputs. |
| `split()` | ✅ | qpdf | Several output files are returned as a ZIP, like Gotenberg. |
| `flatten()` | ✅ | qpdf | |
| `encrypt()` | ✅ | qpdf | AES-256. |
| `embed()` (attachments) | ✅ | qpdf | |
| `rotate()` | ✅ | qpdf | |
| `watermark()` / `stamp()` | ⚠️ | qpdf (+ Chrome for text and image) | Supported options: `font`, `points`, `color`, `opacity`, `rotation`, `scale`. Other pdfcpu options are ignored. |
| `convert()` (PDF/A, PDF/UA) | ⚠️ | LibreOffice | Re-export through LibreOffice Draw, as in Gotenberg. The output is not validated by the bundle. |

### Builder options

| GotenbergBundle option | Status | Needs | Notes |
|---|---|---|---|
| `content` / `contentFile` / `contentRaw` | ✅ | | |
| `header` / `footer` / `headerFile` / `footerFile` / `headerRaw` / `footerRaw` | ⚠️ | | Rendered by Chrome's native header/footer template. Assets used in them are inlined as `data:` URIs. Header and footer need margins, and support only a limited subset of CSS. See [header-footer](./docs/pdf/header-footer.md). |
| `assets` / `addAsset` | ⚠️ | | Copied next to the rendered `index.html` under their basename. Two different files with the same basename raise an error instead of overwriting each other. |
| `downloadFrom` | ✅ | `symfony/http-client` | Downloaded by PHP. Chromium builders get them as assets, file builders as input files. |
| paper size, margins, `landscape`, `scale`, `nativePageRanges`, `printBackground`, `omitBackground`, `preferCssPageSize`, `singlePage`, `generateDocumentOutline`, `generateTaggedPdf` | ✅ | | Every `Unit` is supported (`in`, `pt`, `px`, `mm`, `cm`, `pc`). |
| `waitDelay` / `waitForExpression` / `waitForSelector` | ✅ | | The expression or selector is polled until it is truthy or present, up to the global `timeout`. |
| `skipNetworkIdleEvent` / `skipNetworkAlmostIdleEvent` | ✅ | | Both default to `true` (wait for `load` only). Set one to `false` to also wait for the network to be idle or almost idle. |
| `emulatedMediaType` / `emulatedMediaFeatures` | ✅ | | |
| `cookies` / `setCookie` / `addCookies` / `forwardCookie` | ✅ | | `Symfony\Component\HttpFoundation\Cookie` objects are accepted. |
| `userAgent` / `extraHttpHeaders` / `addExtraHttpHeaders` | ✅ | | |
| `failOnHttpStatusCodes` / `failOnResourceHttpStatusCodes` / `ignoreResourceHttpStatusDomains` / `failOnResourceLoadingFailed` / `failOnConsoleExceptions` | ✅ | | `499` means the whole `400-499` range, as in Gotenberg. Failures throw `BrowserException`. |
| screenshot `width` / `height` / `clip` / `format` / `quality` / `omitBackground` / `optimizeForSpeed` / `deviceScaleFactor` | ⚠️ | | `quality` is ignored for PNG. `clip` defaults to `false` (full page). |
| `fileName` / `processor` / `disposition`, `stream()` / `process()` | ✅ | | Screenshots are served with the matching `image/png`, `image/jpeg` or `image/webp` Content-Type, ZIP results with `application/zip`. |
| `metadata` / `addMetadata` | ✅ | ExifTool | Any ExifTool-writable tag, including custom XMP namespaces. |
| `pdfFormat` / `pdfUniversalAccess` (PDF/A, PDF/UA) | ⚠️ | LibreOffice | On Chromium output, the PDF is re-exported through LibreOffice Draw. Check the result with veraPDF if you need compliance. |
| `splitMode` / `splitSpan` / `splitUnify` | ✅ | qpdf | |
| `flatten` | ✅ | qpdf | |
| `rotateAngle` / `rotatePages` | ✅ | qpdf | |
| `watermark*` / `stamp*` | ⚠️ | qpdf (+ Chrome) | See the `watermark()` / `stamp()` row above. |
| `embedFiles` | ✅ | qpdf | |
| `userPassword` / `ownerPassword` | ✅ | qpdf | |
| `bookmarks` / `addBookmark` / `autoIndexBookmarks` | ⚠️ | qpdf 11+ | Bookmarks of the input files are not kept. |
| LibreOffice export options (`landscape`, `nativePageRanges`, `quality`, `maxImageResolution`, viewer preferences, LibreOffice watermark, ...) | ✅ | LibreOffice | Passed to LibreOffice's PDF export filter. See [LibreOfficePdfBuilder](./docs/pdf/LibreOfficePdfBuilder.md). |

### Platform

| GotenbergBundle feature | Status | Needs | Notes |
|---|---|---|---|
| Webhooks / `generateAsync()` | ⚠️ | opt-in: `async.enabled: true`, `symfony/messenger`, `symfony/http-client` | Optional, disabled by default. A Messenger worker generates the file and sends it to the webhook URL. Same builder methods and `webhook` configuration. Processors are not carried over. See [async](./docs/async.md). |
| Default options per builder in YAML | ✅ | | `default_options.pdf.*` (html, url, markdown, office, merge, split, convert, flatten, encrypt, embed, rotate, watermark, stamp) and `default_options.screenshot.*`. |
| `request_context.base_uri` for `route()` | ✅ | | Also used for webhook routes. |
| `controller_listener` (return a result from a controller) | ✅ | | |
| Processors (File, Null, Tempfile, Chain, InMemory, Flysystem, AsyncAws S3) | ✅ | | |
| Web profiler panel | ✅ | | |
| Gotenberg version detection / per-version warnings | ❌ | | Not applicable: the features you get depend on the installed browser and tool versions. |
| HTTP client / DSN configuration | ⚠️ | | Replaced by `chrome_binary`, `qpdf_binary`, `exiftool_binary`, `libreoffice_binary`, `timeout`, `engine_timeout`, `browser_options`, ... |

## Documentation

1. [Chrome installation guide (Linux, macOS, Windows)](./docs/chrome-installation.md)
2. [PDF engines: qpdf, ExifTool, LibreOffice (Linux, macOS, Windows)](./docs/engines.md)
3. [Configuration reference](./docs/configuration.md)
4. [Security](./docs/security.md)
5. [Processing (saving, streaming, S3...)](./docs/processing.md)
6. [Async generation and webhooks (optional)](./docs/async.md)
7. [Working with assets](./docs/assets.md)
8. [Working with fonts](./docs/fonts.md)
9. [Testing](./docs/testing.md)
10. [Upgrade notes](./UPGRADE.md) and [Changelog](./CHANGELOG.md)

### PDF

1. [HTML Builder](./docs/pdf/HtmlPdfBuilder.md)
2. [URL Builder](./docs/pdf/UrlPdfBuilder.md)
3. [Markdown Builder](./docs/pdf/MarkdownPdfBuilder.md)
4. [Header / Footer](./docs/pdf/header-footer.md)
5. [Post-processing options (split, PDF/A, metadata, watermark, encryption...)](./docs/pdf/post-processing.md)
6. [LibreOffice Builder (Office to PDF)](./docs/pdf/LibreOfficePdfBuilder.md)
7. [Merge Builder](./docs/pdf/MergePdfBuilder.md)
8. [Split Builder](./docs/pdf/SplitPdfBuilder.md)
9. [Convert Builder (PDF/A, PDF/UA)](./docs/pdf/ConvertPdfBuilder.md)
10. [Flatten Builder](./docs/pdf/FlattenPdfBuilder.md)
11. [Encrypt Builder](./docs/pdf/EncryptPdfBuilder.md)
12. [Embed Builder (attachments)](./docs/pdf/EmbedPdfBuilder.md)
13. [Rotate Builder](./docs/pdf/RotatePdfBuilder.md)
14. [Watermark Builder](./docs/pdf/WatermarkPdfBuilder.md)
15. [Stamp Builder](./docs/pdf/StampPdfBuilder.md)

### Screenshot

1. [HTML Builder](./docs/screenshot/HtmlScreenshotBuilder.md)
2. [URL Builder](./docs/screenshot/UrlScreenshotBuilder.md)
3. [Markdown Builder](./docs/screenshot/MarkdownScreenshotBuilder.md)

## Credits

The builder API, processors and much of the documentation structure come from
[sensiolabs/GotenbergBundle](https://github.com/sensiolabs/GotenbergBundle) (MIT).

## Licence

MIT License (MIT): see the [License File](LICENSE) for more details.
