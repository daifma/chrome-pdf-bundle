# ConvertPdfBuilder

Convert existing PDF files to PDF/A and/or PDF/UA.

Requires [LibreOffice](../engines.md) (the Draw component). `flatten()` needs qpdf.

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
        return $chromePdf->convert()
            ->files(
                'document.pdf',
                'document_2.pdf',
            )
            ->pdfFormat(PdfFormat::Pdf2b)
            ->generate()
            ->stream()
        ;
    }
}
```

`PdfFormat` is `Daif\ChromePdfBundle\Enumeration\PdfFormat`. At least one PDF is required, and at least one of
`pdfFormat()` or `pdfUniversalAccess()`.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to convert (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Conversion

| Method | Description |
|---|---|
| `pdfFormat(PdfFormat\|null $format)` | `PdfFormat::Pdf1b`, `Pdf2b` or `Pdf3b` |
| `pdfUniversalAccess(bool $bool = true)` | PDF/UA |
| `flatten(bool $bool = true)` | Flatten forms and annotations |

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## How it works and limits

Each PDF is opened with LibreOffice Draw and exported again with the PDF/A or PDF/UA filter, as Gotenberg does
with LibreOffice. Draw's PDF import converts the page content into drawing objects:

- the result usually looks the same, but text can be split into separate runs and complex layouts can move slightly;
- form fields and some annotations may not survive the import;
- the bundle does not validate the output. If you need compliance, check it with [veraPDF](https://verapdf.org/).

For a PDF that you generate with this bundle, setting `pdfFormat()` on the `html()`, `url()` or `markdown()`
builder gives the same result in one call.
