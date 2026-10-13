# LibreOfficePdfBuilder

Convert Office documents (Word, Excel, PowerPoint, OpenDocument, RTF, text, ...) to PDF.

Requires [LibreOffice](../engines.md) (`soffice`) on the server. The post-processing options need qpdf and ExifTool,
see [post-processing](post-processing.md).

## Available extensions

`123`, `602`, `abw`, `bib`, `bmp`, `cdr`, `cgm`, `cmx`, `csv`, `cwk`, `dbf`, `dif`, `doc`, `docm`,
`docx`, `dot`, `dotm`, `dotx`, `dxf`, `emf`, `eps`, `epub`, `fodg`, `fodp`, `fods`, `fodt`, `fopd`,
`gif`, `htm`, `html`, `hwp`, `jpeg`, `jpg`, `key`, `ltx`, `lwp`, `mcw`, `met`, `mml`, `mw`, `numbers`,
`odd`, `odg`, `odm`, `odp`, `ods`, `odt`, `otg`, `oth`, `otp`, `ots`, `ott`, `pages`, `pbm`, `pcd`,
`pct`, `pcx`, `pdb`, `pdf`, `pgm`, `png`, `pot`, `potm`, `potx`, `ppm`, `pps`, `ppt`, `pptm`, `pptx`,
`psd`, `psw`, `pub`, `pwp`, `pxl`, `ras`, `rtf`, `sda`, `sdc`, `sdd`, `sdp`, `sdw`, `sgl`, `slk`,
`smf`, `stc`, `std`, `sti`, `stw`, `svg`, `svm`, `swf`, `sxc`, `sxd`, `sxg`, `sxi`, `sxm`, `sxw`,
`tga`, `tif`, `tiff`, `txt`, `uof`, `uop`, `uos`, `uot`, `vdx`, `vor`, `vsd`, `vsdm`, `vsdx`, `wb2`,
`wk1`, `wks`, `wmf`, `wpd`, `wpg`, `wps`, `xbm`, `xhtml`, `xls`, `xlsb`, `xlsm`, `xlsx`, `xlt`, `xltm`,
`xltx`, `xlw`, `xml`, `xpm`, `zabw`

The list is the one of GotenbergBundle. Whether a format really converts depends on the LibreOffice components
installed: a spreadsheet needs Calc, a presentation Impress, a drawing Draw.

## Basic usage

> [!WARNING]
> Like assets, Office files are resolved from the `assets_directory` of your application by default.
> For more information about path resolution, see the [assets documentation](../assets.md).

```php
namespace App\Controller;

use Daif\ChromePdfBundle\ChromePdfInterface;
use Symfony\Component\HttpFoundation\Response;

class YourController
{
    public function yourControllerMethod(ChromePdfInterface $chromePdf): Response
    {
        return $chromePdf->office()
            ->files('document.docx')
            ->generate()
            ->stream()
        ;
    }
}
```

You can pass more than one file. Each one is converted separately, and you get a ZIP archive with one PDF per file,
unless you call `merge()`:

```php
return $chromePdf->office()
    ->files('document_one.txt', 'document_two.odt')
    ->generate()
    ->stream() // documents.zip with two PDF files
;

return $chromePdf->office()
    ->files('document_one.txt', 'document_two.odt')
    ->merge()  // one PDF, files merged in alphanumeric order of their names
    ->generate()
    ->stream()
;
```

At least one file is required, and only the extensions above are accepted.

## Available methods

### Input

| Method | Description |
|---|---|
| `files(string\|Stringable ...$paths)` | Files to convert (replaces previous ones) |
| `downloadFrom(array $downloadFrom)` | Download the files to convert from URLs, see [HtmlPdfBuilder](HtmlPdfBuilder.md#downloadfrom) for the rules |
| `password(string $password)` | Password to open the source documents (written to the temporary macro, never on the command line) |
| `merge(bool $bool = true)` | Merge the resulting PDFs into one, in alphanumeric order (needs qpdf) |

### Page and export properties

These methods are passed to LibreOffice's PDF export filter. The second column is the filter option they set.

| Method | LibreOffice option | Description |
|---|---|---|
| `landscape(bool $bool = true)` | (page styles) | Landscape orientation: every portrait page style of the document is switched to landscape before the export |
| `nativePageRanges(string\|null $ranges = null)` | `PageRange` | Pages to export, e.g. `'1-4'`. Empty = all pages |
| `doNotExportFormFields(bool $bool = false)` | `ExportFormFields` | Export the fixed print representation of form fields instead of widgets |
| `allowDuplicateFieldNames(bool $bool = true)` | `AllowDuplicateFieldNames` | Allow several form fields with the same name |
| `doNotExportBookmarks(bool $bool = false)` | `ExportBookmarks` | Do not export bookmarks |
| `exportBookmarksToPdfDestination(bool $bool = true)` | `ExportBookmarksToPDFDestination` | Export bookmarks as named destinations |
| `exportPlaceholders(bool $bool = true)` | `ExportPlaceholders` | Export placeholder field markings |
| `exportNotes(bool $bool = true)` | `ExportNotes` | Export comments (notes) |
| `exportNotesPages(bool $bool = true)` | `ExportNotesPages` | Export notes pages (Impress) |
| `exportOnlyNotesPages(bool $bool = true)` | `ExportOnlyNotesPages` | Export only the notes pages (with `exportNotesPages()`) |
| `exportNotesInMargin(bool $bool = true)` | `ExportNotesInMargin` | Export comments in the page margin |
| `convertOooTargetToPdfTarget(bool $bool = true)` | `ConvertOOoTargetToPDFTarget` | Rewrite links to `.od[tpgs]` files as links to `.pdf` |
| `exportLinksRelativeFsys(bool $bool = true)` | `ExportLinksRelativeFsys` | Export `file://` links as relative links |
| `exportHiddenSlides(bool $bool = true)` | `ExportHiddenSlides` | Export hidden slides (Impress) |
| `skipEmptyPages(bool $bool = true)` | `IsSkipEmptyPages` | Skip automatically inserted empty pages (Writer) |
| `addOriginalDocumentAsStream(bool $bool = true)` | `IsAddStream` | Embed the original document in the PDF |
| `singlePageSheets(bool $bool = true)` | `SinglePageSheets` | Put each sheet on exactly one page (Calc) |
| `doNotUpdateIndexes(bool $bool = false)` | | **Ignored** (logged at debug level). Indexes are exported as saved in the document |

### Images

| Method | LibreOffice option | Description |
|---|---|---|
| `losslessImageCompression(bool $bool = true)` | `UseLosslessCompression` | Lossless (PNG-like) instead of JPEG compression |
| `quality(int $quality)` | `Quality` | JPEG quality, 1 to 100 |
| `reduceImageResolution(bool $bool = true)` | `ReduceImageResolution` | Reduce the image resolution to `maxImageResolution()` |
| `maxImageResolution(ImageResolutionDPI\|null $resolution)` | `MaxImageResolution` | 75, 150, 300, 600 or 1200 DPI |

### LibreOffice watermark

These are drawn by LibreOffice during the export, and are separate from the qpdf-based `watermark*()` options below.

| Method | LibreOffice option | Description |
|---|---|---|
| `watermarkText(string $text)` | `Watermark` | Watermark text on every page |
| `watermarkColor(string $color)` | `WatermarkColor` | Text color, e.g. `'#FF0000'` |
| `watermarkFontHeight(int $height)` | `WatermarkFontHeight` | Font height in points |
| `watermarkRotateAngle(int $angle)` | `WatermarkRotateAngle` | Rotation in tenths of a degree (`450` = 45 degrees) |
| `watermarkFontName(string $fontName)` | `WatermarkFontName` | Font name |
| `tiledWatermarkText(string $text)` | `TiledWatermark` | Tiled watermark text |

### Viewer preferences

| Method | LibreOffice option | Description |
|---|---|---|
| `initialView(InitialView\|null $initialView)` | `InitialView` | `None`, `Outlines` or `Thumbnails` |
| `initialPage(int\|null $initialPage)` | `InitialPage` | Page shown when the PDF opens |
| `magnification(Magnification\|null $magnification)` | `Magnification` | `None`, `FitPage`, `FitWidth`, `FitVisible`, `UseZoomValue` |
| `zoom(int\|null $zoom)` | `Zoom` | Zoom percentage, only with `Magnification::UseZoomValue` |
| `pageLayout(PageLayout\|null $pageLayout)` | `PageLayout` | `None`, `SinglePage`, `OneColumn`, `TwoColumns` |
| `firstPageOnLeft(bool $bool = true)` | `FirstPageOnLeft` | First page on the left in two-column layout |
| `resizeWindowToInitialPage(bool $bool = true)` | `ResizeWindowToInitialPage` | Resize the window to the first page |
| `centerWindow(bool $bool = true)` | `CenterWindow` | Center the viewer window |
| `openInFullScreenMode(bool $bool = true)` | `OpenInFullScreenMode` | Open in full screen |
| `displayPDFDocumentTitle(bool $bool = true)` | `DisplayPDFDocumentTitle` | Show the document title instead of the file name |
| `hideViewerMenubar(bool $bool = true)` | `HideViewerMenubar` | Hide the menu bar |
| `hideViewerToolbar(bool $bool = true)` | `HideViewerToolbar` | Hide the toolbar |
| `hideViewerWindowControls(bool $bool = true)` | `HideViewerWindowControls` | Hide the window controls |
| `useTransitionEffects(bool $bool = true)` | `UseTransitionEffects` | Slide transitions (Impress) |
| `openBookmarkLevels(int\|null $openBookmarkLevels)` | `OpenBookmarkLevels` | Bookmark levels open by default, `-1` = all |

The enumerations are in `Daif\ChromePdfBundle\Enumeration` (`ImageResolutionDPI`, `InitialView`, `Magnification`,
`PageLayout`). `splitUnify()` requires `SplitMode::Pages`, and `zoom()` requires `Magnification::UseZoomValue`,
otherwise `generate()` throws an `InvalidBuilderConfiguration` exception.

### PDF/A, PDF/UA and post-processing

| Method | Description |
|---|---|
| `pdfFormat(PdfFormat\|null $format)` / `pdfUniversalAccess(bool $bool = true)` | PDF/A, PDF/UA. Set directly in LibreOffice's export (`SelectPdfVersion`, `PDFUACompliance`). With `merge()`, the merged file is converted afterwards, like [ConvertPdfBuilder](ConvertPdfBuilder.md) |
| `metadata(array $metadata)` / `addMetadata(string $key, string $value)` | Metadata (ExifTool) |
| `flatten(bool $bool = true)` | Flatten forms and annotations (qpdf) |
| `rotateAngle()` / `rotatePages()` | Rotate pages (qpdf) |
| `splitMode()` / `splitSpan()` / `splitUnify()` | Split the result (qpdf) |
| `watermark*()` / `stamp*()` | Watermark and stamp (qpdf) |
| `embedFiles(string\|Stringable\|EmbeddedFile ...$paths)` | Attachments (qpdf) |
| `userPassword(string\|null $password)` / `ownerPassword(string\|null $password)` | Encryption (qpdf) |

See [post-processing](post-processing.md).

### Output and async

| Method | Description |
|---|---|
| `fileName(string $fileName, string $headerDisposition = 'inline')` | Download name and disposition |
| `processor(ProcessorInterface $processor)` | See [processing](../processing.md) |
| `generate()` | Convert now and return a `ChromePdfFileResult` (PDF, or ZIP for several files) |
| `webhook*()`, `generateAsync()` | Optional, disabled by default: convert in a Messenger worker and send the file to a URL, see [async](../async.md). Recommended for large documents, because LibreOffice is slow to start |

## Example

```php
use Daif\ChromePdfBundle\Enumeration\PdfFormat;

$chromePdf->office()
    ->files('contract.docx', 'annex.xlsx')
    ->merge()
    ->pdfFormat(PdfFormat::Pdf2b)
    ->metadata(['Title' => 'Contract 2026-042'])
    ->userPassword('s3cret')
    ->fileName('contract')
    ->generate()
    ->stream()
;
```

## Differences from GotenbergBundle

- LibreOffice runs on the same machine, one process per document, each with its own temporary profile. Every
  conversion pays LibreOffice's start-up time (often 1 to 3 seconds), where Gotenberg keeps an instance running.
  Raise [`engine_timeout`](../engines.md#configuration) for very large documents.
- Fonts used by the documents must be installed on the server, see [engines](../engines.md#troubleshooting).
- Gotenberg drives LibreOffice through its UNO API. This bundle starts `soffice` with a generated Basic macro that
  opens the document and exports it with the same PDF filter options, see [engines](../engines.md#how-libreoffice-is-run).
  `doNotUpdateIndexes()` is not implemented and is ignored: indexes are exported as saved in the document.
- Macros contained in the documents are never executed, and links to external files are not updated.
- Export options that the installed LibreOffice version does not know (recent additions such as
  `ExportNotesInMargin` on older versions) are ignored by LibreOffice without an error.
