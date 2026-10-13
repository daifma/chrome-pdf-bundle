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

> Generate PDFs and screenshots in Symfony with a local Chrome, Chromium or Microsoft Edge. No Docker needed.

ChromePdfBundle is a **Symfony PDF bundle**: it converts **HTML to PDF**, **Twig to PDF**, URLs and Symfony routes to
PDF, and **Markdown to PDF**, and takes PNG, JPEG or WebP **screenshots**, using a **headless Chrome**, **Chromium** or
**Microsoft Edge** that is installed on the server. It drives the browser over the Chrome DevTools Protocol from PHP.
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

## How it works

ChromePdfBundle uses the builder API of
[sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle) (`html()`, `url()`, `markdown()`,
`->generate()->stream()`, processors, Twig asset helpers). Gotenberg sends each request to a Gotenberg container over
HTTP. This bundle starts a headless Chrome process on the same machine instead and controls it through the
[Chrome DevTools Protocol](https://chromedevtools.github.io/devtools-protocol/) (CDP), using
[chrome-php/chrome](https://github.com/chrome-php/chrome).

## Why this bundle?

I wanted to use [sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle). It is a complete Symfony
bundle for PDF generation. But it needs [Gotenberg](https://gotenberg.dev/), which runs as a **Docker container**.

I work in **on-premise environments** (banking, insurance, other regulated industries), where production servers
often cannot run Docker because of security policies or infrastructure rules.

These machines usually have a browser installed already, or can get one from the standard package manager or the
vendor's MSI. Microsoft Edge is preinstalled on Windows Server 2019 and later.

So this bundle keeps the same builder API and drives the local browser directly through CDP. There is no container
and no extra HTTP service to run.

| Gotenberg-based setup | ChromePdfBundle |
|---|---|
| Docker + a running Gotenberg container | A Chrome / Chromium / Edge binary |
| HTTP calls to an external service | Local CDP connection (WebSocket) |
| Chromium **and** LibreOffice / PDF engines | Chromium only (see [feature parity](#feature-parity-with-sensiolabsgotenberg-bundle)) |

### Compared with other PHP and Symfony PDF tools

The table below lists the options people usually consider for HTML to PDF in Symfony. Information was checked
against each project's README and repository in October 2026.

| Package | Type | Needs at runtime | Rendering | Notes |
|---|---|---|---|---|
| **daif/chrome-pdf-bundle** | Symfony bundle | A Chrome, Chromium or Edge binary | The installed Chromium version | GotenbergBundle-style builders for PDF and screenshots from HTML, Twig, URL and Markdown. Chromium features only (no merge, no Office conversion). |
| [sensiolabs/gotenberg-bundle](https://github.com/sensiolabs/GotenbergBundle) | Symfony bundle | A running [Gotenberg](https://gotenberg.dev/) service, distributed as a Docker image | Chromium and LibreOffice inside Gotenberg | Does more than this bundle (merge, split, Office files, PDF/A). The better choice when you can run Docker. |
| [knplabs/knp-snappy-bundle](https://github.com/KnpLabs/KnpSnappyBundle) | Symfony bundle | The wkhtmltopdf binary | Qt WebKit | The [wkhtmltopdf](https://github.com/wkhtmltopdf/wkhtmltopdf) project is archived and no longer maintained. Its old WebKit engine has weak support for flexbox, grid and recent JavaScript. |
| [spatie/browsershot](https://github.com/spatie/browsershot) | PHP library | Node.js, npm and Puppeteer | Chromium through Puppeteer | Not a Symfony bundle: no configuration, Twig or response integration out of the box. |
| [padam87/rasterize-bundle](https://github.com/Padam87/RasterizeBundle) | Symfony bundle | Node.js and Puppeteer (default provider) | Chromium through Puppeteer | Renders HTML you pass in; needs a Node.js toolchain on the server. |
| [dreadnip/chrome-pdf-bundle](https://github.com/sanderdlm/chrome-pdf-bundle) | Symfony bundle | A Chrome or Chromium binary | Chromium through chrome-php/chrome | One `PdfGenerator` service that writes an HTML string to a PDF file, with raw print options. No URL, Markdown or screenshot builders. |
| [utafitilabs/pdf-scribe-bundle](https://github.com/utafitilabs/pdf-scribe-bundle) | Symfony bundle | A Chrome or Chromium binary, PHP 8.4, Symfony 7.3+ | Chromium | One `PdfGeneratorInterface` service for HTML or URL to PDF. No screenshots or Markdown. |
| [dompdf](https://github.com/dompdf/dompdf), [mPDF](https://github.com/mpdf/mpdf), [TCPDF](https://github.com/tecnickcom/TCPDF) | PHP libraries | PHP only | Their own layout engine | Mostly CSS 2.1: no flexbox, no grid, no JavaScript. TCPDF is marked deprecated in favour of tc-lib-pdf. |
| [chrome-php/chrome](https://github.com/chrome-php/chrome) | PHP library | A Chrome or Chromium binary | Chromium | The library this bundle is built on. No Symfony configuration, Twig rendering, builders or HTTP responses. |

There are already Symfony bundles that drive a local Chrome without Docker, but to our knowledge no other Symfony
bundle combines that with the GotenbergBundle builder API: HTML, URL and Markdown builders for both PDFs and
screenshots, processors (file, S3, Flysystem), Twig asset helpers, a web profiler panel, request filtering for
`file://` and remote URLs, and browser auto-detection on Windows including Microsoft Edge. If you know of one, please
[open an issue](https://github.com/daifma/chrome-pdf-bundle/issues) so this table can be corrected.

## How to install

### Requirements

- PHP 8.1+ with **`ext-sockets`** (chrome-php needs it to talk to the browser over a socket)
- Symfony 6.4 / 7.x / 8.x
- One of Google Chrome, Chromium or Microsoft Edge (Chromium-based) on the same machine. See the
  [Chrome installation guide](./docs/chrome-installation.md) for Linux, macOS and Windows.

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

### Twig assets

To link a static asset (image, CSS, font) from a template, use `chrome_pdf_asset()`:

```twig
<img src="{{ chrome_pdf_asset('img/logo.png') }}" alt="Logo"/>
```

## Feature parity with sensiolabs/gotenberg-bundle

The table compares this bundle with GotenbergBundle 1.4+. Chrome can render HTML and print it to PDF. It cannot
change an existing PDF, so any feature that needs a PDF engine (LibreOffice, qpdf, pdfcpu, ...) is not available.

**Legend:** ✅ supported · ⚠️ supported with differences · ❌ not supported (needs a PDF engine)

### Builders

| GotenbergBundle | Status | Notes |
|---|---|---|
| `html()` PDF | ✅ | |
| `url()` PDF (`url()`, `route()`) | ⚠️ | Only `http` / `https` targets are accepted, and they go through the [request filter](./docs/security.md#request-filtering) (`allow_list` / `deny_list`). |
| `markdown()` PDF | ⚠️ | Markdown is converted in PHP with league/commonmark (Gotenberg uses its own converter). The `{{ toHTML "file.md" }}` directive is supported, also with flexible spacing and quotes. |
| `html()` / `url()` / `markdown()` screenshots | ⚠️ | Full page by default, like Gotenberg. `clip(true)` captures only the viewport. |
| `libreOffice()` (Office → PDF) | ❌ | Needs LibreOffice. Run `soffice --headless --convert-to pdf` yourself, or use a dedicated service. |
| `merge()` | ❌ | Use [qpdf](https://qpdf.readthedocs.io/) (`qpdf --empty --pages a.pdf b.pdf -- out.pdf`), pdftk, or [setasign/fpdi](https://github.com/Setasign/FPDI). |
| `split()` | ❌ | qpdf / pdftk / FPDI. For one Chrome render, `nativePageRanges()` limits which pages are printed. |
| `flatten()` | ❌ | qpdf `--flatten-annotations=all`, pdftk `flatten`. |
| `encrypt()` | ❌ | qpdf `--encrypt`, pdftk `owner_pw`/`user_pw`, setasign SetaPDF-Core. |
| `embed()` (attachments) | ❌ | qpdf `--add-attachment`, pdftk `attach_files`. |
| `watermark()` / `stamp()` | ❌ | Put the watermark in the HTML (CSS `position: fixed` is repeated on every page), or post-process with pdftk `background`/`stamp` or FPDI. |
| `rotate()` | ❌ | qpdf `--rotate`, pdftk `cat 1-endeast`. Use `landscape()` when you generate the PDF. |
| `convert()` (PDF/A, PDF/UA) | ❌ | Needs a PDF engine (Ghostscript, veraPDF-validated pipelines). `generateTaggedPdf()` produces a tagged PDF, which helps accessibility but is not PDF/UA certification. |

### Builder options

| GotenbergBundle option | Status | Notes |
|---|---|---|
| `content` / `contentFile` / `contentRaw` | ✅ | |
| `header` / `footer` / `headerFile` / `footerFile` / `headerRaw` / `footerRaw` | ⚠️ | Rendered by Chrome's native header/footer template. Assets used in them are inlined as `data:` URIs. Header and footer need margins, and support only a limited subset of CSS. See [header-footer](./docs/pdf/header-footer.md). |
| `assets` / `addAsset` | ⚠️ | Copied next to the rendered `index.html` under their basename. Two different files with the same basename raise an error instead of overwriting each other. |
| `downloadFrom` | ⚠️ | Files are downloaded by PHP into the conversion directory, so the page can reference them like assets. They are not attached to the output PDF (that needs a PDF engine). |
| paper size, margins, `landscape`, `scale`, `nativePageRanges`, `printBackground`, `omitBackground`, `preferCssPageSize`, `singlePage`, `generateDocumentOutline`, `generateTaggedPdf` | ✅ | Every `Unit` is supported (`in`, `pt`, `px`, `mm`, `cm`, `pc`). |
| `waitDelay` / `waitForExpression` / `waitForSelector` | ✅ | The expression or selector is polled until it is truthy or present, up to the global `timeout`. |
| `skipNetworkIdleEvent` / `skipNetworkAlmostIdleEvent` | ✅ | Both default to `true` (wait for `load` only). Set one to `false` to also wait for the network to be idle or almost idle. |
| `emulatedMediaType` / `emulatedMediaFeatures` | ✅ | |
| `cookies` / `setCookie` / `addCookies` / `forwardCookie` | ✅ | `Symfony\Component\HttpFoundation\Cookie` objects are accepted. |
| `userAgent` / `extraHttpHeaders` / `addExtraHttpHeaders` | ✅ | |
| `failOnHttpStatusCodes` / `failOnResourceHttpStatusCodes` / `ignoreResourceHttpStatusDomains` / `failOnResourceLoadingFailed` / `failOnConsoleExceptions` | ✅ | `499` means the whole `400–499` range, as in Gotenberg. Failures throw `BrowserException`. |
| screenshot `width` / `height` / `clip` / `format` / `quality` / `omitBackground` / `optimizeForSpeed` / `deviceScaleFactor` | ⚠️ | `quality` is ignored for PNG. `clip` defaults to `false` (full page). |
| `fileName` / `processor` / `disposition`, `stream()` / `process()` | ✅ | Screenshots are served with the matching `image/png`, `image/jpeg` or `image/webp` Content-Type. |
| `metadata` / `addMetadata` | ❌ | Chrome only writes `Title` (from `<title>`) and `Creator`/`Producer`. Use exiftool or qpdf to set other metadata afterwards. |
| `pdfFormat` / `pdfUniversalAccess` (PDF/A, PDF/UA) | ❌ | Needs a PDF engine. |
| `split*`, `flatten`, `encrypt`, `embeds`, `watermark*`, `stamp*`, `rotate*` options on Chromium builders | ❌ | Post-process the PDF (see above). |

### Platform

| GotenbergBundle feature | Status | Notes |
|---|---|---|
| Webhooks / `generateAsync()` | ❌ | There is no remote service to call back. Use [Symfony Messenger](https://symfony.com/doc/current/messenger.html): dispatch a message, and have the handler generate the file with a `FileProcessor` or a `FlysystemProcessor`. |
| Default options per builder in YAML | ✅ | `default_options.pdf.{html,url,markdown}` and `default_options.screenshot.{html,url,markdown}`. |
| `request_context.base_uri` for `route()` | ✅ | |
| `controller_listener` (return a result from a controller) | ✅ | |
| Processors (File, Null, Tempfile, Chain, InMemory, Flysystem, AsyncAws S3) | ✅ | |
| Web profiler panel | ✅ | |
| Gotenberg version detection / per-version warnings | ❌ | Not applicable: the features you get depend on the installed browser version. |
| HTTP client / DSN configuration | ⚠️ | Replaced by `chrome_binary`, `timeout`, `browser_options`, ... |

## Documentation

1. [Chrome installation guide (Linux, macOS, Windows)](./docs/chrome-installation.md)
2. [Configuration reference](./docs/configuration.md)
3. [Security](./docs/security.md)
4. [Processing (saving, streaming, S3...)](./docs/processing.md)
5. [Working with assets](./docs/assets.md)
6. [Working with fonts](./docs/fonts.md)
7. [Testing](./docs/testing.md)
8. [Upgrade notes](./UPGRADE.md) and [Changelog](./CHANGELOG.md)

### PDF

1. [HTML Builder](./docs/pdf/HtmlPdfBuilder.md)
2. [URL Builder](./docs/pdf/UrlPdfBuilder.md)
3. [Markdown Builder](./docs/pdf/MarkdownPdfBuilder.md)
4. [Header / Footer](./docs/pdf/header-footer.md)

### Screenshot

1. [HTML Builder](./docs/screenshot/HtmlScreenshotBuilder.md)
2. [URL Builder](./docs/screenshot/UrlScreenshotBuilder.md)
3. [Markdown Builder](./docs/screenshot/MarkdownScreenshotBuilder.md)

## Credits

The builder API, processors and much of the documentation structure come from
[sensiolabs/GotenbergBundle](https://github.com/sensiolabs/GotenbergBundle) (MIT).

## Licence

MIT License (MIT): see the [License File](LICENSE) for more details.
