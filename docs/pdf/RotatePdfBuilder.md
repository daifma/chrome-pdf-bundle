# RotatePdfBuilder

Rotate the pages of PDF files.

Requires [qpdf](../engines.md).

## Basic usage

> [!WARNING]
> Like assets, PDF files are resolved from the `assets_directory` of your application by default.
> For more information about path resolution, see the [assets documentation](../assets.md).

> [!NOTE]
> With several input files, each one is processed separately and the result is a ZIP archive
> (`application/zip`) containing one PDF per input file, like Gotenberg. See [output](post-processing.md#output-one-pdf-or-a-zip).

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->rotate()
            ->files('document.pdf')
            ->rotateAngle(RotateAngle::Rotate90)
            ->rotatePages('1-2')
            ->generate()
            ->stream()
        ;
    }
}
```

`RotateAngle` is `Daif\ChromePdfBundle\Enumeration\RotateAngle`. At least one PDF and `rotateAngle()` are
required.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to rotate (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Rotation

| Method | Description |
|---|---|
| `rotateAngle(RotateAngle\|null $rotateAngle = null)` | `RotateAngle::Rotate90`, `Rotate180` or `Rotate270` (clockwise) |
| `rotatePages(string\|null $rotatePages = null)` | Pages to rotate, e.g. `'1-3,5'`. Empty or `null` = all pages |

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## Notes

The rotation is relative to the current orientation of each page (`qpdf --rotate=+90:1-2`). To produce landscape
pages from HTML in the first place, use `landscape()` on the Chromium builders instead.
