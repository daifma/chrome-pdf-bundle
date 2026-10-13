# WatermarkPdfBuilder

Add a watermark under the content of PDF pages. The watermark can be a text, an image or the first page of another PDF.

Requires [qpdf](../engines.md). Text and image watermarks are rendered by Chrome, so Chrome must be installed too.

## Basic usage

> [!WARNING]
> Like assets, PDF files are resolved from the `assets_directory` of your application by default.
> For more information about path resolution, see the [assets documentation](../assets.md).

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->watermark()
            ->files('document.pdf')
            ->watermarkSource(WatermarkSource::Text)
            ->watermarkExpression('CONFIDENTIAL')
            ->generate()
            ->stream()
        ;
    }
}
```

`WatermarkSource` is `Daif\ChromePdfBundle\Enumeration\WatermarkSource`. At least one PDF, `watermarkSource()` and `watermarkExpression()`
are required, and a file for the `Image` and `Pdf` sources.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to watermark (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Watermark

| Method | Description |
|---|---|
| `watermarkSource(WatermarkSource $watermarkSource)` | `WatermarkSource::Text`, `Image` or `Pdf` |
| `watermarkExpression(string $watermarkExpression)` | The text for `Text`. For `Image` and `Pdf`, the file name of the watermark file |
| `watermarkFile(string\|Stringable $path)` | Image or PDF used as watermark, resolved from `assets_directory` (required for `Image` and `Pdf`) |
| `watermarkPages(string\|null $watermarkPages = null)` | Pages to mark, e.g. `'1-3'`. Empty or `null` = all pages |
| `watermarkOptions(array $watermarkOptions)` | Appearance: `font`, `points`, `color`, `opacity`, `rotation`, `scale` |

The supported option keys and their defaults are listed in
[post-processing](post-processing.md#watermark-and-stamp). Other pdfcpu keys accepted by Gotenberg (`offset`,
`position`, ...) are ignored.

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## Example with an image

```php
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;

$chromePdf->watermark()
    ->files('contract.pdf')
    ->watermarkSource(WatermarkSource::Image)
    ->watermarkFile('img/draft.png')
    ->watermarkOptions(['scale' => 0.4, 'opacity' => 0.3, 'rotation' => 45])
    ->generate()
    ->stream()
;
```

## How it works

For `Text` and `Image`, Chrome renders a one-page transparent PDF with the size of the first page of the document,
the text or image centred on it. For `Pdf`, the first page of `watermarkFile()` is used as is. qpdf then places that
page under each selected page (`qpdf --underlay`). Pages of a different size
get the watermark scaled to fit.

> [!NOTE]
> A watermark is under the content. Pages with an opaque background hide it. Use
> [StampPdfBuilder](StampPdfBuilder.md) with a low `opacity` in that case.
