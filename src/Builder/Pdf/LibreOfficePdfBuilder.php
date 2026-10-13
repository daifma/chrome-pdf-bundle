<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\EmbedTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EncryptTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\LibreOfficeTrait;
use Daif\ChromePdfBundle\Enumeration\Magnification;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\RuntimeException;
use Daif\ChromePdfBundle\Formatter\PathHelper;

/**
 * You may have the possibility to convert Office files into PDF. Requires LibreOffice (and qpdf to merge).
 *
 * Several files are returned as a ZIP archive, unless merge() is used.
 *
 * @methodDoc files Adds office files to convert (overrides any previous files).
 *
 * @example files('document.doc', __DIR__'/../../public/document_2.odt')
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'office')]
final class LibreOfficePdfBuilder extends AbstractBuilder
{
    use EmbedTrait;
    use EncryptTrait;
    use FilesTrait;
    use LibreOfficeTrait;

    private const AVAILABLE_EXTENSIONS = [
        '123', '602', 'abw', 'bib', 'bmp', 'cdr', 'cgm', 'cmx', 'csv', 'cwk', 'dbf', 'dif', 'doc', 'docm',
        'docx', 'dot', 'dotm', 'dotx', 'dxf', 'emf', 'eps', 'epub', 'fodg', 'fodp', 'fods', 'fodt', 'fopd',
        'gif', 'htm', 'html', 'hwp', 'jpeg', 'jpg', 'key', 'ltx', 'lwp', 'mcw', 'met', 'mml', 'mw', 'numbers',
        'odd', 'odg', 'odm', 'odp', 'ods', 'odt', 'otg', 'oth', 'otp', 'ots', 'ott', 'pages', 'pbm', 'pcd',
        'pct', 'pcx', 'pdb', 'pdf', 'pgm', 'png', 'pot', 'potm', 'potx', 'ppm', 'pps', 'ppt', 'pptm', 'pptx',
        'psd', 'psw', 'pub', 'pwp', 'pxl', 'ras', 'rtf', 'sda', 'sdc', 'sdd', 'sdp', 'sdw', 'sgl', 'slk',
        'smf', 'stc', 'std', 'sti', 'stw', 'svg', 'svm', 'swf', 'sxc', 'sxd', 'sxg', 'sxi', 'sxm', 'sxw',
        'tga', 'tif', 'tiff', 'txt', 'uof', 'uop', 'uos', 'uot', 'vdx', 'vor', 'vsd', 'vsdm', 'vsdx', 'wb2',
        'wk1', 'wks', 'wmf', 'wpd', 'wpg', 'wps', 'xbm', 'xhtml', 'xls', 'xlsb', 'xlsm', 'xlsx', 'xlt', 'xltm',
        'xltx', 'xlw', 'xml', 'xpm', 'zabw',
    ];

    /**
     * Body bag keys of the LibreOffice\PagePropertiesTrait mapped to the LibreOffice PDF export filter options.
     */
    private const FILTER_OPTIONS = [
        'exportFormFields' => 'ExportFormFields',
        'allowDuplicateFieldNames' => 'AllowDuplicateFieldNames',
        'exportBookmarks' => 'ExportBookmarks',
        'exportBookmarksToPdfDestination' => 'ExportBookmarksToPDFDestination',
        'exportPlaceholders' => 'ExportPlaceholders',
        'exportNotes' => 'ExportNotes',
        'exportNotesPages' => 'ExportNotesPages',
        'exportOnlyNotesPages' => 'ExportOnlyNotesPages',
        'exportNotesInMargin' => 'ExportNotesInMargin',
        'convertOooTargetToPdfTarget' => 'ConvertOOoTargetToPDFTarget',
        'exportLinksRelativeFsys' => 'ExportLinksRelativeFsys',
        'exportHiddenSlides' => 'ExportHiddenSlides',
        'skipEmptyPages' => 'IsSkipEmptyPages',
        'addOriginalDocumentAsStream' => 'IsAddStream',
        'singlePageSheets' => 'SinglePageSheets',
        'losslessImageCompression' => 'UseLosslessCompression',
        'quality' => 'Quality',
        'reduceImageResolution' => 'ReduceImageResolution',
        'maxImageResolution' => 'MaxImageResolution',
        'nativePageRanges' => 'PageRange',
        'nativeWatermarkText' => 'Watermark',
        'nativeWatermarkColor' => 'WatermarkColor',
        'nativeWatermarkFontHeight' => 'WatermarkFontHeight',
        'nativeWatermarkRotateAngle' => 'WatermarkRotateAngle',
        'nativeWatermarkFontName' => 'WatermarkFontName',
        'nativeTiledWatermarkText' => 'TiledWatermark',
        'initialView' => 'InitialView',
        'initialPage' => 'InitialPage',
        'magnification' => 'Magnification',
        'zoom' => 'Zoom',
        'pageLayout' => 'PageLayout',
        'firstPageOnLeft' => 'FirstPageOnLeft',
        'resizeWindowToInitialPage' => 'ResizeWindowToInitialPage',
        'centerWindow' => 'CenterWindow',
        'openInFullScreenMode' => 'OpenInFullScreenMode',
        'displayPDFDocumentTitle' => 'DisplayPDFDocumentTitle',
        'hideViewerMenubar' => 'HideViewerMenubar',
        'hideViewerToolbar' => 'HideViewerToolbar',
        'hideViewerWindowControls' => 'HideViewerWindowControls',
        'useTransitionEffects' => 'UseTransitionEffects',
        'openBookmarkLevels' => 'OpenBookmarkLevels',
    ];

    protected function getAllowedFilesExtensions(): array
    {
        return self::AVAILABLE_EXTENSIONS;
    }

    protected function produce(string $workDir): array
    {
        /** @var array<string, string> $files */
        $files = $this->getBodyBag()->get('files', []);
        if ($this->isMerging()) {
            // Gotenberg merges the converted files in the alphanumerical order of their names.
            uksort($files, static fn (string $a, string $b): int => strnatcasecmp(PathHelper::basename($a), PathHelper::basename($b)));
        }

        if (true === $this->getBodyBag()->get('updateIndexes')) {
            $this->getLogger()?->debug('The "updateIndexes" option is not supported by the LibreOffice command line, it is ignored.');
        }

        $libreOffice = $this->getLibreOffice();
        $filterOptions = $this->collectFilterOptions();
        $password = $this->getBodyBag()->get('password');
        $landscape = true === $this->getBodyBag()->get('landscape');

        $pdfs = [];
        foreach (array_values($files) as $index => $path) {
            $outputDir = $workDir.\DIRECTORY_SEPARATOR.'office_'.$index;
            if (!@mkdir($outputDir, 0o700) && !is_dir($outputDir)) {
                throw new RuntimeException(\sprintf('Could not create the directory "%s".', $outputDir));
            }

            $pdfs[] = $libreOffice->convert($path, $outputDir, $filterOptions, \is_string($password) ? $password : null, $landscape);
        }

        if ($this->isMerging()) {
            $output = $workDir.\DIRECTORY_SEPARATOR.'merged.pdf';
            $this->getQpdf()->merge($pdfs, $output);

            return [$output];
        }

        return $pdfs;
    }

    protected function collectPostProcessOptions(): array
    {
        $options = parent::collectPostProcessOptions();

        // Without merge, LibreOffice exports PDF/A and PDF/UA directly (see collectFilterOptions()).
        if (!$this->isMerging()) {
            unset($options['pdfFormat'], $options['pdfUniversalAccess']);
        }

        return $options;
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get('files') === null && [] === $this->getBodyBag()->get('downloadFrom', [])) {
            throw new MissingRequiredFieldException('At least one office file is required.');
        }

        if ($this->getBodyBag()->get('splitUnify') === true && $this->getBodyBag()->get('splitMode') === SplitMode::Intervals) {
            throw new InvalidBuilderConfiguration('"splitUnify" can only be at "true" with "pages" mode for "splitMode".');
        }

        if ($this->getBodyBag()->get('zoom') !== null && $this->getBodyBag()->get('magnification') !== Magnification::UseZoomValue) {
            throw new InvalidBuilderConfiguration('"zoom" can only be set when "magnification" is set to "Magnification::UseZoomValue (4)".');
        }
    }

    /**
     * Builds the LibreOffice PDF export filter options from the body bag.
     *
     * @return array<string, bool|int|string>
     */
    private function collectFilterOptions(): array
    {
        $bag = $this->getBodyBag();
        $options = [];

        foreach (self::FILTER_OPTIONS as $key => $filterOption) {
            $value = $bag->get($key);
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }

            if (null === $value) {
                continue;
            }

            if ('nativeWatermarkColor' === $key) {
                $value = (int) hexdec(ltrim((string) $value, '#'));
            }

            $options[$filterOption] = \is_bool($value) || \is_int($value) ? $value : (string) $value;
        }

        if (!$this->isMerging()) {
            $pdfFormat = $bag->get('pdfa');
            if ($pdfFormat instanceof PdfFormat) {
                $options['SelectPdfVersion'] = match ($pdfFormat) {
                    PdfFormat::Pdf1b => 1,
                    PdfFormat::Pdf2b => 2,
                    PdfFormat::Pdf3b => 3,
                };
            }

            if (true === $bag->get('pdfua')) {
                $options['PDFUACompliance'] = true;
            }
        }

        return $options;
    }

    private function isMerging(): bool
    {
        $files = $this->getBodyBag()->get('files', []);

        return true === $this->getBodyBag()->get('merge') && \is_array($files) && \count($files) > 1;
    }
}
