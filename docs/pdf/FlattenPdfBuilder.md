# FlattenPdfBuilder

Flatten PDF files: form fields and annotations are merged into the page content, so they can no longer be edited.

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
        return $chromePdf->flatten()
            ->files(
                'document_1.pdf',
                'document_2.pdf',
            )
            ->generate()
            ->stream()
        ;
    }
}
```

At least one PDF is required.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to flatten (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## How it works

qpdf runs with `--generate-appearances --flatten-annotations=all`: appearance streams are generated for form fields
that lack them, then every annotation is drawn into the page.
