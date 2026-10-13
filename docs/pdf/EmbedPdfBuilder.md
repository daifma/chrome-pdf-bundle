# EmbedPdfBuilder

Attach files to PDF files. This is what standards like [ZUGFeRD / Factur-X](https://fnfe-mpe.org/factur-x/) use to
carry the XML invoice inside the PDF.

Requires [qpdf](../engines.md).

## Basic usage

> [!WARNING]
> Like assets, the PDF files and the files to embed are resolved from the `assets_directory` of your application by
> default. For more information about path resolution, see the [assets documentation](../assets.md).

> [!WARNING]
> You must provide at least one file to embed.

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->embed()
            ->files('document_1.pdf')
            ->embedFiles('document.xml')
            ->generate()
            ->stream()
        ;
    }
}
```

At least one PDF and one file to embed are required.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to attach files to (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Attachments

| Method | Description |
|---|---|
| `embedFiles(string\|Stringable\|EmbeddedFile ...$paths)` | Files to attach (replaces previous ones) |

`Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile` is accepted for compatibility with GotenbergBundle:

```php
use Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile;

->embedFiles(new EmbeddedFile('/var/data/factur-x.xml', 'Data', 'text/xml'))
```

Its path is used as given (it is not resolved from `assets_directory`), so pass an absolute path. Its relationship
(`AFRelationship`) and MIME type are kept on the builder but not written to the PDF yet: qpdf sets the MIME type
from the file extension (`xml`, `json`, `csv`, `pdf`, `txt`, common images) and writes no relationship.

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## Notes

- Files are attached with `qpdf --add-attachment`. The attachment keeps the base name of the file, and an existing
  attachment with the same name is replaced.
- A complete Factur-X invoice also needs an `AFRelationship` entry (not written, see above), PDF/A-3 (`pdfFormat(PdfFormat::Pdf3b)`) and specific XMP metadata. With
  this builder alone you only get the attachment. On `html()`, `office()`, `merge()` and `split()`, `embedFiles()`
  can be combined with those options in one call, see [post-processing](post-processing.md#attachments).
