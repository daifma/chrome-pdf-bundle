# MergePdfBuilder

Merge several PDF files into one.

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
        return $chromePdf->merge()
            ->files(
                'document.pdf',
                'document_2.pdf',
            )
            ->generate()
            ->stream()
        ;
    }
}
```

At least one PDF is required (from `files()` or `downloadFrom()`), otherwise `generate()` throws a
`MissingRequiredFieldException`.

> [!NOTE]
> Like Gotenberg, the files are merged in the **alphanumeric order of their file names** (natural order, case
> insensitive: `2.pdf` before `10.pdf`), not in the order of the `files()` arguments. Prefix the names
> (`01_cover.pdf`, `02_report.pdf`) to control the order.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | PDF files to merge, sorted by file name (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the PDFs to merge from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |

### Bookmarks

| Method | Description |
|---|---|
| `bookmarks(array $bookmarks)` | A list applies to the merged file, e.g. `[['title' => 'Intro', 'page' => 1, 'children' => [...]]]`. A map of file name to list (`['a.pdf' => [...], 'b.pdf' => [...]]`) gives pages relative to each file, shifted to its position in the merged file. Pages are 1-based |
| `addBookmark(string $title, int $page, array $children = [])` | Add one bookmark to the list |
| `autoIndexBookmarks(bool $bool = true)` | Extract the bookmarks already present in each input file and shift their pages to the file position in the merged document (default `false`) |

Bookmarks already present in the input files are **not** kept: qpdf drops the outlines of the inputs when it merges
them. Bookmarks need qpdf 11 or later.

### Post-processing

| Method | Description |
|---|---|
| `pdfFormat(PdfFormat\|null $format)` / `pdfUniversalAccess(bool $bool = true)` | PDF/A, PDF/UA |
| `metadata(array $metadata)` / `addMetadata(string $key, string $value)` | Metadata |
| `flatten(bool $bool = true)` | Flatten forms and annotations |
| `watermark*()` / `stamp*()` | Watermark and stamp |
| `embedFiles(string\|Stringable\|EmbeddedFile ...$paths)` | Attachments |
| `userPassword(string\|null $password)` / `ownerPassword(string\|null $password)` | Encryption |

See [post-processing](post-processing.md) for the details of each option.

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Merge now and return a `ChromePdfFileResult` |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: generate in a Messenger worker and send the file to a URL, see [async](../async.md) |

## Example

```php
$chromePdf->merge()
    ->files('01_cover.pdf', '02_chapter-1.pdf', '03_chapter-2.pdf')
    ->bookmarks([
        '02_chapter-1.pdf' => [['title' => 'Summary', 'page' => 2]],   // page 2 of chapter 1
    ])
    ->autoIndexBookmarks()   // keeps the bookmarks of the three files, then adds "Summary"
    ->metadata(['Title' => 'Annual report', 'Author' => 'ACME'])
    ->fileName('annual-report')
    ->generate()
    ->stream()
;
```

## Differences from GotenbergBundle

- Merging is done locally by qpdf (`qpdf --empty --pages ...`) instead of Gotenberg's PDF engines.
- Bookmarks are read and written with qpdf (JSON v2) instead of pdfcpu.
