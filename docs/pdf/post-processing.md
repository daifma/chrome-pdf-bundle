# Post-processing options

The PDF builders can change the generated PDF after it is produced: split it, convert it to PDF/A, write metadata,
flatten, rotate, add a watermark or a stamp, attach files, add bookmarks and encrypt it. The method names are the
ones of GotenbergBundle. Gotenberg runs these steps with its PDF engines inside the container. This bundle runs them
with local tools, see [engines.md](../engines.md).

| Option | Methods | Tool |
|---|---|---|
| Split | `splitMode()`, `splitSpan()`, `splitUnify()` | qpdf |
| PDF/A, PDF/UA | `pdfFormat()`, `pdfUniversalAccess()` | LibreOffice |
| Metadata | `metadata()`, `addMetadata()` | ExifTool |
| Flatten | `flatten()` | qpdf |
| Rotate | `rotateAngle()`, `rotatePages()` | qpdf |
| Watermark (under the content) | `watermarkSource()`, `watermarkExpression()`, `watermarkFile()`, `watermarkPages()`, `watermarkOptions()` | qpdf, plus Chrome for text and image sources |
| Stamp (over the content) | `stampSource()`, `stampExpression()`, `stampFile()`, `stampPages()`, `stampOptions()` | qpdf, plus Chrome for text and image sources |
| Bookmarks | `bookmarks()`, `addBookmark()`, `autoIndexBookmarks()` | qpdf 11+ |
| Attachments | `embedFiles()` | qpdf |
| Encryption | `userPassword()`, `ownerPassword()` | qpdf |

Which builder has which option:

| Builder | Split | PDF/A | Metadata | Flatten | Rotate | Watermark / Stamp | Bookmarks | Attachments | Encryption |
|---|---|---|---|---|---|---|---|---|---|
| `html()` | yes | yes | yes | yes | yes | yes | | yes | yes |
| `url()`, `markdown()` | yes | yes | yes | yes | yes | yes | | | yes |
| `office()` | yes | yes | yes | yes | yes | yes | | yes | yes |
| `merge()` | | yes | yes | yes | | yes | yes | yes | yes |
| `split()` | yes | yes | yes | yes | | yes | | yes | yes |
| `convert()` | | yes | | yes | | | | | |
| `flatten()`, `rotate()`, `encrypt()`, `embed()`, `watermark()`, `stamp()` | only the option of the builder itself | | | | | | | | |

Screenshot builders have none of them.

When no post-processing option is set, no external tool is called and the Chromium builders behave exactly as
before.

## Order of the steps

The steps run in the same order as in Gotenberg, whatever the order of the method calls:

1. split
2. PDF/A and PDF/UA conversion
3. metadata
4. flatten
5. rotate
6. watermark
7. stamp
8. bookmarks
9. attachments
10. encryption (always last, so every previous step can still read the file)

When the split produces several files, steps 2 to 10 are applied to each of them.

## Output: one PDF or a ZIP

`generate()` returns a `ChromePdfFileResult` in every case:

- one output file: the PDF, as before;
- several output files (a split without `splitUnify()`, `office()` with several files and without `merge()`, or a
  file builder given several input PDFs): **one ZIP archive** with the PDFs inside, like Gotenberg.
  The Content-Type is `application/zip` and the default file name is `<fileName>.zip`, or `documents.zip` without a
  `fileName()`.

```php
$result = $chromePdf->html()
    ->content('report.html.twig')
    ->splitMode(SplitMode::Intervals)
    ->splitSpan('1')
    ->fileName('report')
    ->generate();

$result->getContentType(); // application/zip
$result->stream();         // report.zip, one PDF per page
```

The ZIP output needs PHP's `zip` extension.

## Split

```php
use Daif\ChromePdfBundle\Enumeration\SplitMode;

$chromePdf->html()
    ->content('book.html.twig')
    ->splitMode(SplitMode::Pages)
    ->splitSpan('1-2,5')
    ->splitUnify()          // one PDF with pages 1, 2 and 5 instead of a ZIP
    ->generate()
    ->stream();
```

| Method | Description |
|---|---|
| `splitMode(SplitMode\|null $splitMode = null)` | `SplitMode::Intervals`: cut every N pages. `SplitMode::Pages`: extract page ranges |
| `splitSpan(string $splitSpan)` | Intervals mode: the number of pages per file, e.g. `'2'`. Pages mode: the ranges, e.g. `'1-3,5'` |
| `splitUnify(bool $bool = true)` | Pages mode only: put the extracted pages in one PDF instead of one file per range |

## PDF/A and PDF/UA

```php
use Daif\ChromePdfBundle\Enumeration\PdfFormat;

$chromePdf->html()
    ->content('invoice.html.twig')
    ->pdfFormat(PdfFormat::Pdf3b)
    ->pdfUniversalAccess()
    ->generate()
    ->stream();
```

| Method | Description |
|---|---|
| `pdfFormat(PdfFormat\|null $format)` | `PdfFormat::Pdf1b` (`PDF/A-1b`), `Pdf2b` (`PDF/A-2b`) or `Pdf3b` (`PDF/A-3b`) |
| `pdfUniversalAccess(bool $bool = true)` | PDF/UA |

For PDFs produced by Chrome (and for `convert()`), the file is opened with LibreOffice Draw and exported again with
the PDF/A or PDF/UA filter. Gotenberg does the same with LibreOffice. Draw's PDF import turns the page content into
drawing objects: the result usually looks the same, but text can be split into separate runs, and complex layouts
may move slightly. Check a few documents before you rely on it, and validate the output with
[veraPDF](https://verapdf.org/) if you need compliance. The bundle does not validate PDF/A or PDF/UA itself.

For `office()`, the PDF/A and PDF/UA options are passed to LibreOffice's own export instead, which keeps the
document structure.

## Metadata

```php
$chromePdf->html()
    ->content('invoice.html.twig')
    ->metadata(['Author' => 'ACME', 'Title' => 'Invoice 42'])
    ->addMetadata('Subject', 'Invoice')
    ->generate()
    ->stream();
```

| Method | Description |
|---|---|
| `metadata(array $metadata)` | Replace the metadata to write |
| `addMetadata(string $key, string $value)` | Add one entry |

Keys are PDF or XMP tag names, optionally prefixed with a `PDF:`, `XMP:` or `XMP-<namespace>:` group: `Author`, `Title`, `Subject`, `Keywords`, `Creator`, `Producer`, `CreationDate`,
`ModDate`, `Trapped` (`True`, `False`, `Unknown`), `Copyright`, `Marked`, `PDFVersion`, and any other PDF or XMP tag ExifTool can write,
including custom XMP namespaces such as `XMP-fx:DocumentType` for Factur-X. Dates use ExifTool's format, e.g.
`2026:10:13 09:30:00+02:00`. Values can be strings, booleans, numbers or lists of strings (for list tags such as
`Keywords`); an empty string deletes the tag. Values are passed to ExifTool in a UTF-8 argument file, so non-ASCII
text works on every OS. Metadata can also be set per builder in YAML (`default_options.pdf.<builder>.metadata`).

## Flatten

`flatten()` merges form fields and annotations into the page content, so they can no longer be edited.

## Rotate

```php
use Daif\ChromePdfBundle\Enumeration\RotateAngle;

$chromePdf->html()
    ->content('table.html.twig')
    ->rotateAngle(RotateAngle::Rotate90)
    ->rotatePages('2-3')    // null or omitted = all pages
    ->generate()
    ->stream();
```

The angle is `RotateAngle::Rotate90`, `Rotate180` or `Rotate270`. Pages use qpdf's range syntax (`'1-3,5'`,
`'r1'` for the last page).

## Watermark and stamp

A watermark is drawn **under** the page content, a stamp **over** it. Both take the same options:

| Method | Description |
|---|---|
| `watermarkSource(WatermarkSource $source)` / `stampSource(StampSource $source)` | `Text`, `Image` or `Pdf` |
| `watermarkExpression(string $expression)` / `stampExpression(string $expression)` | The text for `Text`. For `Image` and `Pdf`, the file name set with `*File()` |
| `watermarkFile(string\|Stringable $path)` / `stampFile(string\|Stringable $path)` | Image or PDF file, resolved from `assets_directory` (required for `Image` and `Pdf`) |
| `watermarkPages(string\|null $pages = null)` / `stampPages(string\|null $pages = null)` | Pages to mark, e.g. `'1-3'`. Empty or `null` = all pages |
| `watermarkOptions(array $options)` / `stampOptions(array $options)` | Appearance, see below |

```php
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;

$chromePdf->html()
    ->content('contract.html.twig')
    ->watermarkSource(WatermarkSource::Text)
    ->watermarkExpression('DRAFT')
    ->watermarkOptions(['color' => '#cc0000', 'opacity' => 0.2, 'points' => 96])
    ->stampSource(StampSource::Image)
    ->stampFile('img/approved.png')
    ->stampPages('1')
    ->stampOptions(['scale' => 0.3, 'rotation' => -15])
    ->generate()
    ->stream();
```

How it works: for `Text` and `Image`, Chrome renders a one-page transparent PDF, the size of the first page of the
document, with the text or image centred. For `Pdf`, the first page of the given PDF is used as is. qpdf then puts
that page under (watermark) or over (stamp) every selected page.

Supported `*Options()` keys:

| Key | Applies to | Default | Description |
|---|---|---|---|
| `font` | text | `Helvetica, Arial, sans-serif` | CSS `font-family` |
| `points` | text | `48` | Font size in points |
| `color` | text | `#808080` | Any CSS color |
| `opacity` | text, image | `0.5` watermark, `1` stamp | 0 to 1 |
| `rotation` | text, image | `45` watermark, `0` stamp | Degrees, counterclockwise |
| `scale` | image | `0.5` | Image width as a fraction of the page width (0 to 1) |

Gotenberg passes these options to pdfcpu, which accepts more keys (`offset`, `position`, `strokecolor`, ...). Keys
not listed above are ignored. For a `Pdf` source, the options are ignored: design the stamp PDF the way you want it.

> [!NOTE]
> A watermark is under the content. If the page has an opaque background (for example a template with a white
> `background` and `printBackground()`), the watermark is hidden. Use a stamp with a low `opacity` instead.

## Bookmarks

Available on `merge()`:

```php
$chromePdf->merge()
    ->files('1-cover.pdf', '2-report.pdf')   // merged in file name order
    ->bookmarks([
        ['title' => 'Cover', 'page' => 1],
        ['title' => 'Report', 'page' => 2, 'children' => [['title' => 'Summary', 'page' => 3]]],
    ])
    ->generate()
    ->stream();
```

| Method | Description |
|---|---|
| `bookmarks(array $bookmarks)` | Replace the bookmarks. Pages are 1-based, `children` nests entries. A map of file name to list gives pages relative to each input file |
| `addBookmark(string $title, int $page, array $children = [])` | Add one entry |
| `autoIndexBookmarks(bool $bool = true)` | Keep the bookmarks of each merged file, with their pages shifted to the file position |

Bookmarks of the input files are not kept. See [MergePdfBuilder](MergePdfBuilder.md#bookmarks).

The Chromium builders can already produce bookmarks from the HTML headings with `generateDocumentOutline()`.

## Attachments

```php
use Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile;

$chromePdf->html()
    ->content('invoice.html.twig')
    ->embedFiles('factur-x.xml', 'data.json')
    // or ->embedFiles(new EmbeddedFile('/abs/path/factur-x.xml', 'Data', 'text/xml'))
    ->generate()
    ->stream();
```

File names are resolved from `assets_directory`, like assets (an `EmbeddedFile` path is used as given). They are
attached with qpdf `--add-attachment`; the MIME type comes from the file extension, and the relationship of an
`EmbeddedFile` is not written. A complete Factur-X or ZUGFeRD invoice also needs `pdfFormat(PdfFormat::Pdf3b)`, the
required XMP metadata and an `AFRelationship` entry, so validate the result with a dedicated tool.

## Encryption

```php
$chromePdf->html()
    ->content('payslip.html.twig')
    ->userPassword('open-me')
    ->ownerPassword('change-me')   // optional, defaults to the user password
    ->generate()
    ->stream();
```

The PDF is encrypted with AES-256. The user password is needed to open the file; the owner password grants full
permissions. Passwords are marked `#[\SensitiveParameter]`, and are given to qpdf through a temporary argument file,
so they do not appear in the process list or in error messages.

## Default options in YAML

Every option above has a configuration node (`split_mode`, `split_span`, `split_unify`, `pdf_format`,
`pdf_universal_access`, `metadata`, `flatten`, `rotate_angle`, `rotate_pages`, `watermark_*`, `stamp_*`, ...), so you
can set it for a builder in `default_options`. See the [configuration reference](../configuration.md).
