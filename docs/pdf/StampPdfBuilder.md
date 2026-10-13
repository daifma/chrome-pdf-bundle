# StampPdfBuilder

Add a stamp over the content of PDF pages. The stamp can be a text, an image or the first page of another PDF.

Requires [qpdf](../engines.md). Text and image stamps are rendered by Chrome, so Chrome must be installed too.

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
        return $chromePdf->stamp()
            ->files('document.pdf')
            ->stampSource(StampSource::Text)
            ->stampExpression('APPROVED')
            ->generate()
            ->stream()
        ;
    }
}
```

`StampSource` is `Daif\ChromePdfBundle\Enumeration\StampSource`. At least one PDF, `stampSource()` and `stampExpression()`
are required, and a file for the `Image` and `Pdf` sources.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to stamp (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Stamp

| Method | Description |
|---|---|
| `stampSource(StampSource $stampSource)` | `StampSource::Text`, `Image` or `Pdf` |
| `stampExpression(string $stampExpression)` | The text for `Text`. For `Image` and `Pdf`, the file name of the stamp file |
| `stampFile(string\|Stringable $path)` | Image or PDF used as stamp, resolved from `assets_directory` (required for `Image` and `Pdf`) |
| `stampPages(string\|null $stampPages = null)` | Pages to mark, e.g. `'1-3'`. Empty or `null` = all pages |
| `stampOptions(array $stampOptions)` | Appearance: `font`, `points`, `color`, `opacity`, `rotation`, `scale` |

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
use Daif\ChromePdfBundle\Enumeration\StampSource;

$chromePdf->stamp()
    ->files('contract.pdf')
    ->stampSource(StampSource::Image)
    ->stampFile('img/approved.png')
    ->stampOptions(['scale' => 0.4, 'opacity' => 0.8, 'rotation' => 0])
    ->generate()
    ->stream()
;
```

## How it works

For `Text` and `Image`, Chrome renders a one-page transparent PDF with the size of the first page of the document,
the text or image centred on it. For `Pdf`, the first page of `stampFile()` is used as is. qpdf then places that
page over each selected page (`qpdf --overlay`). Pages of a different size
get the stamp scaled to fit.
