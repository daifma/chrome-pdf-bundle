# HtmlPdfBuilder

Convert HTML or Twig files into PDF.

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

### HTML content

The HTML file to convert into PDF.

> [!WARNING]
> By default, the HTML files are fetched from the assets folder of
> your application.
> For more information about path resolution go to [assets documentation](../assets.md).

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->html()
            ->contentFile('../templates/content.html')
            ->generate()
            ->stream()
         ;
    }
}
```

### Twig content

The Twig file to convert into PDF.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;

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

## Available methods

### Page properties

| Method | Description |
|---|---|
| `paperSize(float $width, float $height, Unit $unit)` | Set paper size (default: Letter 8.5x11 inches) |
| `paperStandardSize(PaperSizeInterface $paperSize)` | Set standard paper size (e.g., `PaperSize::A4`) |
| `paperWidth(float $width, Unit $unit)` | Set paper width |
| `paperHeight(float $height, Unit $unit)` | Set paper height |
| `margins(float $top, float $bottom, float $left, float $right, Unit $unit)` | Set all margins |
| `marginTop(float $top, Unit $unit)` | Set top margin |
| `marginBottom(float $bottom, Unit $unit)` | Set bottom margin |
| `marginLeft(float $left, Unit $unit)` | Set left margin |
| `marginRight(float $right, Unit $unit)` | Set right margin |
| `landscape(bool $bool)` | Set landscape orientation |
| `scale(float $scale)` | Set page scale (e.g., 1.0) |
| `nativePageRanges(string $ranges)` | Page ranges (e.g., "1-5, 8") |
| `printBackground(bool $bool)` | Print background graphics |
| `omitBackground(bool $bool)` | Transparent background |
| `preferCssPageSize(bool $bool)` | Prefer CSS-defined page size |
| `singlePage(bool $bool)` | Print all content on a single page |

### Content

| Method | Description |
|---|---|
| `content(string $template, array $context)` | Set Twig template |
| `contentFile(string $path)` | Set HTML file |
| `header(string $template, array $context)` | Set header Twig template |
| `headerFile(string $path)` | Set header HTML file |
| `footer(string $template, array $context)` | Set footer Twig template |
| `footerFile(string $path)` | Set footer HTML file |

### Assets

| Method | Description |
|---|---|
| `assets(Stringable\|string ...$paths)` | Set asset files (overrides previous) |
| `addAsset(Stringable\|string $path)` | Add a single asset file |

### Wait / Rendering

| Method | Description |
|---|---|
| `waitDelay(string $delay)` | Wait duration before converting (e.g., "5s") |
| `waitForExpression(string $expression)` | Wait for JS expression to return true |
| `emulatedMediaType(EmulatedMediaType $mediaType)` | Emulate "screen" or "print" media |
| `skipNetworkIdleEvent(bool $bool)` | Skip network idle for faster conversion |

### Cookies & Headers

| Method | Description |
|---|---|
| `cookies(array $cookies)` | Set cookies for Chromium |
| `addCookies(array $cookies)` | Add cookies |
| `setCookie(string $name, Cookie\|array $cookie)` | Set a single cookie |
| `forwardCookie(string $name)` | Forward a cookie from the current request |
| `extraHttpHeaders(array $headers)` | Set extra HTTP headers |
| `addExtraHttpHeaders(array $headers)` | Add extra HTTP headers |
| `userAgent(string $userAgent)` | Override User-Agent header |

### Error handling

| Method | Description |
|---|---|
| `failOnHttpStatusCodes(array $statusCodes)` | Fail on specific HTTP status codes |
| `failOnConsoleExceptions(bool $bool)` | Fail on Chromium console exceptions |

## Examples

### A4 landscape with background

```php
$chromePdf->html()
    ->content('report.html.twig', ['data' => $data])
    ->paperStandardSize(PaperSize::A4)
    ->landscape()
    ->printBackground()
    ->generate()
    ->stream()
;
```

### Custom margins and scale

```php
$chromePdf->html()
    ->content('invoice.html.twig', ['invoice' => $invoice])
    ->margins(0.5, 0.5, 0.5, 0.5, Unit::Inches)
    ->scale(0.9)
    ->generate()
    ->stream()
;
```

### Wait for JavaScript rendering

```php
$chromePdf->html()
    ->content('chart.html.twig')
    ->waitForExpression("window.chartReady === true")
    ->generate()
    ->stream()
;
```
