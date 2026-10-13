# HtmlPdfBuilder

Convert HTML, Twig templates or raw HTML strings into PDF.

## Basic usage

> [!WARNING]
> Every HTML or Twig template you use should have a proper HTML structure:
> ```html
><!DOCTYPE html>
><html lang="en">
>  <head>
>    <meta charset="utf-8" />
>    <title>My PDF</title>
>  </head>
>  <body>
>    <!-- Your code goes here -->
>  </body>
></html>
> ```

### Twig content

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->content('content.html.twig', [
                'my_var' => 'value'
            ])
            ->generate()
            ->stream()
        ;
    }
}
```

### HTML file

> [!WARNING]
> By default, HTML files are fetched from the assets folder of your application.
> For more information about path resolution, see the [assets documentation](../assets.md).

```php
return $chromePdf->html()
    ->contentFile('../templates/content.html')
    ->generate()
    ->stream()
;
```

### Raw HTML string

Useful when the HTML comes from another renderer or from a database. The string is used as-is, without Twig.

```php
return $chromePdf->html()
    ->contentRaw('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Hi</title></head><body><h1>Hello</h1></body></html>')
    ->generate()
    ->stream()
;
```

> [!CAUTION]
> Never pass unsanitised user input to `contentRaw()`. See [security](../security.md#untrusted-html-and-markdown).

## Available methods

### Content

| Method | Description |
|---|---|
| `content(string $template, array $context = [])` | Twig template |
| `contentFile(string $path)` | HTML file (resolved from `assets_directory`) |
| `contentRaw(string $html)` | Raw HTML string |
| `header(string $template, array $context = [])` | Header Twig template |
| `headerFile(string $path)` | Header HTML file |
| `headerRaw(string $html)` | Header raw HTML string |
| `footer(string $template, array $context = [])` | Footer Twig template |
| `footerFile(string $path)` | Footer HTML file |
| `footerRaw(string $html)` | Footer raw HTML string |

See [Header / Footer](header-footer.md). They behave differently from the main content.

### Page properties

| Method | Description |
|---|---|
| `paperSize(float $width, float $height, Unit $unit = Unit::Inches)` | Paper size (default: Letter 8.5 x 11 in) |
| `paperStandardSize(PaperSizeInterface $paperSize)` | Standard size, e.g. `PaperSize::A4` |
| `paperWidth(float $width, Unit $unit = Unit::Inches)` | Paper width |
| `paperHeight(float $height, Unit $unit = Unit::Inches)` | Paper height |
| `margins(float $top, float $bottom, float $left, float $right, Unit $unit = Unit::Inches)` | All margins (default 0.39 in each) |
| `marginTop()` / `marginBottom()` / `marginLeft()` / `marginRight()` `(float $value, Unit $unit = Unit::Inches)` | One margin |
| `landscape(bool $bool = true)` | Landscape orientation |
| `scale(float $scale)` | Rendering scale, 0.1 to 2.0 (default 1.0) |
| `nativePageRanges(string\|null $ranges = null)` | Pages to print, e.g. `'1-5, 8, 11-13'` (`null` = all pages) |
| `printBackground(bool $bool = true)` | Print background graphics |
| `omitBackground(bool $bool = true)` | Transparent default background (use with `printBackground()`) |
| `preferCssPageSize(bool $bool = true)` | Use the CSS `@page { size: ... }` |
| `singlePage(bool $bool = true)` | All content on one page whose height fits the document (ignores `nativePageRanges`). With `landscape()`, the page is printed portrait with width = max(paper width, paper height) |
| `generateDocumentOutline(bool $bool = true)` | PDF bookmarks generated from headings |
| `generateTaggedPdf(bool $bool = true)` | Tagged (accessible) PDF. Recent Chromium versions tag PDFs by default, so `generateTaggedPdf(false)` may have no effect |

`Unit` supports `Inches` (`in`), `Points` (`pt`), `Pixels` (`px`), `Millimeters` (`mm`), `Centimeters` (`cm`) and
`Picas` (`pc`).

### Assets

| Method | Description |
|---|---|
| `assets(Stringable\|string ...$paths)` | Asset files (replaces previous ones) |
| `addAsset(Stringable\|string $path)` | Add one asset file |
| `downloadFrom(array $downloadFrom)` | Download remote files into the conversion directory, see [below](#downloadfrom) |

### Waiting

| Method | Description |
|---|---|
| `waitDelay(string $delay)` | Fixed delay after load, e.g. `'500ms'`, `'2s'` |
| `waitForExpression(string $expression)` | Poll a JS expression until it is truthy |
| `waitForSelector(string $selector)` | Poll until `document.querySelector($selector)` exists |
| `skipNetworkIdleEvent(bool $bool = true)` | `false` = also wait until there has been no network activity for 500 ms (default `true`) |
| `skipNetworkAlmostIdleEvent(bool $bool = true)` | `false` = also wait until at most 2 connections have been active for 500 ms (default `true`) |

All waits are bounded by the global [`timeout`](../configuration.md#timeout).

### Emulation

| Method | Description |
|---|---|
| `emulatedMediaType(EmulatedMediaType $mediaType)` | `EmulatedMediaType::Print` (default) or `::Screen` |
| `emulatedMediaFeatures(array $emulatedMediaFeatures)` | CSS media features, e.g. `[['name' => 'prefers-color-scheme', 'value' => 'dark']]` |

### Cookies & headers

| Method | Description |
|---|---|
| `cookies(array $cookies)` | Replace cookies (arrays or `Symfony\Component\HttpFoundation\Cookie`) |
| `addCookies(array $cookies)` | Add cookies |
| `setCookie(string $name, Cookie\|array $cookie)` | Set one cookie |
| `forwardCookie(string $name)` | Forward a cookie from the current request |
| `userAgent(string $userAgent)` | Override the User-Agent (see `Enumeration\UserAgent`) |
| `extraHttpHeaders(array $headers)` | Replace extra HTTP headers |
| `addExtraHttpHeaders(array $headers)` | Add extra HTTP headers |

### Error handling

| Method | Description |
|---|---|
| `failOnHttpStatusCodes(array $statusCodes)` | Fail if the **main document** returns one of these codes |
| `failOnResourceHttpStatusCodes(array $statusCodes)` | Fail if a **sub-resource** returns one of these codes |
| `ignoreResourceHttpStatusDomains(array $domains)` | Hosts (and their sub-domains) ignored by `failOnResourceHttpStatusCodes()`. `example.com`, `*.example.com`, `example.com:443` and `https://example.com/path` are all read as `example.com` |
| `failOnResourceLoadingFailed(bool $bool = true)` | Fail if a sub-resource cannot be loaded (DNS, connection, TLS, or blocked by the request filter, with the reason in the message). Cancelled requests are ignored |
| `failOnConsoleExceptions(bool $bool = true)` | Fail on uncaught JavaScript exceptions |

A code ending in `99` means the whole hundred: `[499]` matches 400–499, `[599]` matches 500–599 (as in Gotenberg).
Failures throw `Daif\ChromePdfBundle\Exception\BrowserException` with a message naming the URL and status.

### Output

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition (`HeaderUtils::DISPOSITION_ATTACHMENT` / `_INLINE`) |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run Chrome and return a `ChromePdfFileResult` |

## Examples

### A4 landscape with background

```php
use Daif\ChromePdfBundle\Enumeration\PaperSize;

$chromePdf->html()
    ->content('report.html.twig', ['data' => $data])
    ->paperStandardSize(PaperSize::A4)
    ->landscape()
    ->printBackground()
    ->generate()
    ->stream()
;
```

### Margins in millimetres

```php
use Daif\ChromePdfBundle\Enumeration\Unit;

$chromePdf->html()
    ->content('invoice.html.twig', ['invoice' => $invoice])
    ->margins(20, 20, 15, 15, Unit::Millimeters)
    ->scale(0.9)
    ->generate()
    ->stream()
;
```

### Wait for a JavaScript chart

```php
$chromePdf->html()
    ->content('chart.html.twig')
    ->waitForSelector('#chart svg')          // or ->waitForExpression('window.chartReady === true')
    ->generate()
    ->stream()
;
```

### Dark mode, screen media

```php
use Daif\ChromePdfBundle\Enumeration\EmulatedMediaType;

$chromePdf->html()
    ->content('dashboard.html.twig')
    ->emulatedMediaType(EmulatedMediaType::Screen)
    ->emulatedMediaFeatures([
        ['name' => 'prefers-color-scheme', 'value' => 'dark'],
        ['name' => 'prefers-reduced-motion', 'value' => 'reduce'],
    ])
    ->printBackground()
    ->generate()
    ->stream()
;
```

### Strict rendering

```php
$chromePdf->html()
    ->content('report.html.twig')
    ->failOnResourceHttpStatusCodes([499, 599])
    ->ignoreResourceHttpStatusDomains(['analytics.example.com'])
    ->failOnResourceLoadingFailed()
    ->failOnConsoleExceptions()
    ->generate()
    ->stream()
;
```

### `downloadFrom`

`downloadFrom()` fetches remote files **in PHP** (Symfony HttpClient, requests sent in parallel) right before the
conversion, and adds them as assets: they are copied next to `index.html`. Reference them by file name in your HTML.
This is useful for files behind authentication.

- Requires `symfony/http-client` (`framework.http_client` enabled). Without it, a `LogicException` is thrown.
- The file name comes from the response's `Content-Disposition` header, or else from the last segment of the URL.
  Two URLs that resolve to the same file name throw a `DownloadFromException`.
- Only `http` and `https` URLs are accepted. Each URL is checked against `allow_list` / `deny_list` before any
  request is sent, and redirects are not followed (a 3xx response is an error), so a redirect cannot leave the allow list.
- A response status of 300 or more, or a network error, throws a `DownloadFromException`.
- Files are written under `temp_dir` (default: the system temp directory) in a `0700` directory, and deleted after the
  conversion.
- With the Markdown builders, downloaded `.md` files are added to the Markdown files instead of the assets.

```php
$chromePdf->html()
    ->contentRaw('<!DOCTYPE html><html><body><img src="logo.png"></body></html>')
    ->downloadFrom([
        ['url' => 'https://cdn.example.com/brand/logo.png', 'extraHttpHeaders' => ['Authorization' => 'Bearer xxx']],
    ])
    ->generate()
    ->stream()
;
```

> [!NOTE]
> In Gotenberg, `downloadFrom` replaces uploaded *input files* (for example, PDFs to merge). Chrome cannot merge or
> embed files, so here it only provides resources for the page.

## Differences from GotenbergBundle

The API is the same, but Chrome runs locally instead of inside Gotenberg, so a few things behave differently:

- **Header and footer** are Chrome's native header/footer templates (as in Gotenberg). They need top/bottom margins,
  their CSS must be inline, and assets used in them are inlined as `data:` URIs. See [header-footer](header-footer.md).
- **Markdown**: the `{{ toHTML "file.md" }}` directive must be protected from Twig (`{% verbatim %}`) inside a Twig
  wrapper. See [MarkdownPdfBuilder](MarkdownPdfBuilder.md#twig-wrapper).
- **Screenshots** capture the full page by default, like Gotenberg; `clip()` limits the capture to the viewport.
- **`downloadFrom()`** only provides resources for the page. It cannot attach files to the PDF.
- **No PDF engine**: `merge`, `split`, `flatten`, `encrypt`, `embed`, `watermark`, `stamp`, `rotate`, `metadata`,
  `pdfFormat` and `pdfUniversalAccess` don't exist. See the [feature parity table](../../README.md#feature-parity-with-sensiolabsgotenberg-bundle).
- **Errors**: every engine failure (timeout, blocked URL, fail-on rule, browser crash) throws
  `Daif\ChromePdfBundle\Exception\BrowserException`, instead of an HTTP client error from Gotenberg.

## Troubleshooting

See [Chrome installation → Troubleshooting](../chrome-installation.md#troubleshooting) for timeouts, sandbox errors and
fonts. The most common rendering problems:

- **Images or CSS missing**: the path must be relative to `index.html`, so use `chrome_pdf_asset()` or `assets()`.
  `file://` URLs outside the conversion directory are blocked (see [security](../security.md#local-files-file)).
- **Blank charts**: the JS has not finished. Use `waitForSelector()` / `waitForExpression()`.
- **Header/footer not visible**: add margins (see [header-footer](header-footer.md)).
- **`Timed out after 30000 ms ...`**: the message says what the bundle was waiting for. Raise
  [`timeout`](../configuration.md#timeout), keep `skipNetworkIdleEvent()` at its default (`true`), or fix the
  selector or expression that never matches.
- **Chrome does not start as root** (`Running as root without --no-sandbox is not supported`): run PHP as a normal
  user, or set `no_sandbox: true` (see [security](../security.md#chrome-sandbox)).
- **Squares instead of letters, wrong font**: install fonts on the server, or embed them with
  `chrome_pdf_font_face()` (see [fonts](../fonts.md)).
