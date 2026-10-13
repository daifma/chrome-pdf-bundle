# SplitPdfBuilder

Split PDF files by intervals or page ranges.

Requires [qpdf](../engines.md). Metadata needs ExifTool, PDF/A and PDF/UA need LibreOffice.

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
        return $chromePdf->split()
            ->files('document.pdf')
            ->splitMode(SplitMode::Pages)
            ->splitSpan('1-2')
            ->splitUnify()
            ->generate()
            ->stream()
        ;
    }
}
```

`SplitMode` is `Daif\ChromePdfBundle\Enumeration\SplitMode`. At least one PDF, `splitMode()` and `splitSpan()` are
required, otherwise `generate()` throws a `MissingRequiredFieldException`.

## Modes

| Mode | `splitSpan()` | Result |
|---|---|---|
| `SplitMode::Intervals` | Number of pages per file, e.g. `'2'` | Pages 1-2, 3-4, ... each in its own PDF |
| `SplitMode::Pages` | Page ranges, e.g. `'1-3,5'` | One PDF per range, or one PDF with all the ranges with `splitUnify()` |

When the split produces more than one file (or when several input files are given), the result is a ZIP archive
(`application/zip`, default name `<fileName>.zip` or `documents.zip`). One single file is returned as a PDF.
See [output](post-processing.md#output-one-pdf-or-a-zip).

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to split (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the input PDFs from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Split

| Method | Description |
|---|---|
| `splitMode(SplitMode\|null $splitMode = null)` | `SplitMode::Intervals` or `SplitMode::Pages` |
| `splitSpan(string $splitSpan)` | Interval size or page ranges, depending on the mode |
| `splitUnify(bool $bool = true)` | Pages mode only: put all extracted pages in one PDF (default `false`) |

### Post-processing

Applied to each produced file:

| Method | Description |
|---|---|
| `pdfFormat(PdfFormat\|null $format)` / `pdfUniversalAccess(bool $bool = true)` | PDF/A, PDF/UA |
| `metadata(array $metadata)` / `addMetadata(string $key, string $value)` | Metadata |
| `flatten(bool $bool = true)` | Flatten forms and annotations |
| `watermark*()` / `stamp*()` | Watermark and stamp |
| `embedFiles(string\|Stringable\|EmbeddedFile ...$paths)` | Attachments |
| `userPassword(string\|null $password)` / `ownerPassword(string\|null $password)` | Encryption |

See [post-processing](post-processing.md).

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Run the conversion now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## Example: one file per page, saved to disk

```php
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Symfony\Component\Filesystem\Filesystem;

$chromePdf->split()
    ->files('statement.pdf')
    ->splitMode(SplitMode::Intervals)
    ->splitSpan('1')
    ->fileName('statement-pages')
    ->processor(new FileProcessor(new Filesystem(), '%kernel.project_dir%/var/split'))
    ->generate()
    ->process()    // writes statement-pages.zip
;
```
