<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\LibreOffice;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\ImageResolutionDPI;
use Daif\ChromePdfBundle\Enumeration\InitialView;
use Daif\ChromePdfBundle\Enumeration\Magnification;
use Daif\ChromePdfBundle\Enumeration\PageLayout;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\IntegerNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * LibreOffice PDF export options, passed to soffice as PDF export filter options.
 *
 * doNotUpdateIndexes() has no PDF export filter equivalent: soffice always updates the indexes from the
 * command line, the option is ignored. exportNotesInMargin() is not supported by LibreOffice 7.4 and older
 * (ignored by them).
 *
 * @package Behavior\\LibreOffice
 */
trait PagePropertiesTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Set the password for opening the source file.
     *
     * @example password('My password')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('password'))]
    public function password(#[\SensitiveParameter] string $password): static
    {
        $this->getBodyBag()->set('password', $password);

        return $this;
    }

    /**
     * Set the paper orientation to landscape.
     *
     * @example landscape() // is same as `->landscape(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('landscape'))]
    public function landscape(bool $bool = true): static
    {
        $this->getBodyBag()->set('landscape', $bool);

        return $this;
    }

    /**
     * Page ranges to print, e.g., '1-4' - empty means all pages.
     *
     * @example nativePageRanges('1-5')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('native_page_ranges'))]
    public function nativePageRanges(string|null $ranges = null): static
    {
        if (!$ranges) {
            $this->getBodyBag()->unset('nativePageRanges');
        } else {
            ValidatorFactory::range($ranges);
            $this->getBodyBag()->set('nativePageRanges', $ranges);
        }

        return $this;
    }

    /**
     * Specify whether form fields are exported as widgets or only their fixed print representation is exported.
     *
     * @example doNotExportFormFields() // is same as `->doNotExportFormFields(false)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('do_not_export_form_fields'))]
    public function doNotExportFormFields(bool $bool = false): static
    {
        $this->getBodyBag()->set('exportFormFields', $bool);

        return $this;
    }

    /**
     * Specify whether multiple form fields exported are allowed to have the same field name.
     *
     * @example allowDuplicateFieldNames()  // is same as `->allowDuplicateFieldNames(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('allow_duplicate_field_names'))]
    public function allowDuplicateFieldNames(bool $bool = true): static
    {
        $this->getBodyBag()->set('allowDuplicateFieldNames', $bool);

        return $this;
    }

    /**
     * Specify if bookmarks are exported to PDF.
     *
     * @example doNotExportBookmarks()  // is same as `->doNotExportBookmarks(false)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('do_not_export_bookmarks'))]
    public function doNotExportBookmarks(bool $bool = false): static
    {
        $this->getBodyBag()->set('exportBookmarks', $bool);

        return $this;
    }

    /**
     * Specify that the bookmarks contained in the source LibreOffice file should be exported to the PDF file as Named Destination.
     *
     * @example exportBookmarksToPdfDestination()  // is same as `->exportBookmarksToPdfDestination(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_bookmarks_to_pdf_destination'))]
    public function exportBookmarksToPdfDestination(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportBookmarksToPdfDestination', $bool);

        return $this;
    }

    /**
     * Export the placeholders fields visual markings only. The exported placeholder is ineffective.
     *
     * @example exportPlaceholders()  // is same as `->exportPlaceholders(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_placeholders'))]
    public function exportPlaceholders(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportPlaceholders', $bool);

        return $this;
    }

    /**
     * Specify if notes are exported to PDF.
     *
     * @example exportNotes()  // is same as `->exportNotes(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_notes'))]
    public function exportNotes(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportNotes', $bool);

        return $this;
    }

    /**
     * Specify if notes pages are exported to PDF. Notes pages are available in Impress documents only.
     *
     * @example exportNotesPages()  // is same as `->exportNotesPages(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_notes_pages'))]
    public function exportNotesPages(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportNotesPages', $bool);

        return $this;
    }

    /**
     * Specify, if the form field exportNotesPages is set to true, if only notes pages are exported to PDF.
     *
     * @example exportOnlyNotesPages()  // is same as `->exportOnlyNotesPages(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_only_notes_pages'))]
    public function exportOnlyNotesPages(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportOnlyNotesPages', $bool);

        return $this;
    }

    /**
     * Specify if notes in margin are exported to PDF.
     *
     * @example exportNotesInMargin()  // is same as `->exportNotesInMargin(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_notes_in_margin'))]
    public function exportNotesInMargin(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportNotesInMargin', $bool);

        return $this;
    }

    /**
     * Specify that the target documents with .od[tpgs] extension, will have that extension changed to .pdf when the link is exported to PDF. The source document remains untouched.
     *
     * @example convertOooTargetToPdfTarget()  // is same as `->convertOooTargetToPdfTarget(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('convert_ooo_target_to_pdf_target'))]
    public function convertOooTargetToPdfTarget(bool $bool = true): static
    {
        $this->getBodyBag()->set('convertOooTargetToPdfTarget', $bool);

        return $this;
    }

    /**
     * Specify that the file system related hyperlinks (file:// protocol) present in the document will be exported as relative to the source document location.
     *
     * @example exportLinksRelativeFsys()  // is same as `->exportLinksRelativeFsys(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_links_relative_fsys'))]
    public function exportLinksRelativeFsys(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportLinksRelativeFsys', $bool);

        return $this;
    }

    /**
     * Export, for LibreOffice Impress, slides that are not included in slide shows.
     *
     * @example exportHiddenSlides()  // is same as `->exportHiddenSlides(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('export_hidden_slides'))]
    public function exportHiddenSlides(bool $bool = true): static
    {
        $this->getBodyBag()->set('exportHiddenSlides', $bool);

        return $this;
    }

    /**
     * Specify that automatically inserted empty pages are suppressed. This option is active only if storing Writer documents.
     *
     * @example skipEmptyPages()  // is same as `->skipEmptyPages(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('skip_empty_pages'))]
    public function skipEmptyPages(bool $bool = true): static
    {
        $this->getBodyBag()->set('skipEmptyPages', $bool);

        return $this;
    }

    /**
     * Specify that a stream is inserted to the PDF file which contains the original document for archiving purposes.
     *
     * @example addOriginalDocumentAsStream()  // is same as `->addOriginalDocumentAsStream(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('add_original_document_as_stream'))]
    public function addOriginalDocumentAsStream(bool $bool = true): static
    {
        $this->getBodyBag()->set('addOriginalDocumentAsStream', $bool);

        return $this;
    }

    /**
     * Ignore each sheet’s paper size, print ranges and shown/hidden status and puts every sheet (even hidden sheets) on exactly one page.
     *
     * @example singlePageSheets()  // is same as `->singlePageSheets(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('single_page_sheets'))]
    public function singlePageSheets(bool $bool = true): static
    {
        $this->getBodyBag()->set('singlePageSheets', $bool);

        return $this;
    }

    /**
     * Merge alphanumerically the resulting PDFs.
     *
     * @example merge() // is same as ->merge(true)
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('merge'))]
    public function merge(bool $bool = true): static
    {
        $this->getBodyBag()->set('merge', $bool);

        return $this;
    }

    /**
     * Specify if images are exported to PDF using a lossless compression format like PNG or compressed using the JPEG format.
     *
     * @example losslessImageCompression()  // is same as `->losslessImageCompression(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('lossless_image_compression'))]
    public function losslessImageCompression(bool $bool = true): static
    {
        $this->getBodyBag()->set('losslessImageCompression', $bool);

        return $this;
    }

    /**
     * Specify the quality of the JPG export. A higher value produces a higher-quality image and a larger file. Between 1 and 100.
     *
     * @param int<0, 100> $quality
     *
     * @example quality(75)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('quality', min: 0, max: 100))]
    public function quality(int $quality): static
    {
        ValidatorFactory::quality($quality);
        $this->getBodyBag()->set('quality', $quality);

        return $this;
    }

    /**
     * Specify if the resolution of each image is reduced to the resolution specified by the form field maxImageResolution.
     *
     * @example reduceImageResolution()  // is same as `->reduceImageResolution(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('reduce_image_resolution'))]
    public function reduceImageResolution(bool $bool = true): static
    {
        $this->getBodyBag()->set('reduceImageResolution', $bool);

        return $this;
    }

    /**
     * If the form field reduceImageResolution is set to true, tell if all images will be reduced to the given value in DPI. Possible values are: 75, 150, 300, 600 and 1200.
     *
     * @example maxImageResolution(ImageResolutionDPI::DPI300)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('max_image_resolution', enumClass: ImageResolutionDPI::class))]
    public function maxImageResolution(ImageResolutionDPI|null $resolution): static
    {
        if (!$resolution) {
            $this->getBodyBag()->unset('maxImageResolution');
        } else {
            $this->getBodyBag()->set('maxImageResolution', $resolution);
        }

        return $this;
    }

    /**
     * Specify whether to update the indexes before conversion, keeping in mind that doing so might result in missing links in the final PDF.
     *
     * @example doNotUpdateIndexes() // is same as `->doNotUpdateIndexes(false)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('update_indexes'))]
    public function doNotUpdateIndexes(bool $bool = false): static
    {
        $this->getBodyBag()->set('updateIndexes', $bool);

        return $this;
    }

    /**
     * Set the watermark text to render on every page during PDF export.
     *
     * @example watermarkText('CONFIDENTIAL')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_text'))]
    public function watermarkText(string $text): static
    {
        $this->getBodyBag()->set('nativeWatermarkText', $text);

        return $this;
    }

    /**
     * Set the watermark text color as a hex string (e.g., '#FF0000').
     *
     * @example watermarkColor('#FF0000')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_color'))]
    public function watermarkColor(string $color): static
    {
        ValidatorFactory::hexColor($color);

        $this->getBodyBag()->set('nativeWatermarkColor', $color);

        return $this;
    }

    /**
     * Set the watermark font height in points.
     *
     * @example watermarkFontHeight(50)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('watermark_font_height'))]
    public function watermarkFontHeight(int $height): static
    {
        $this->getBodyBag()->set('nativeWatermarkFontHeight', $height);

        return $this;
    }

    /**
     * Set the watermark rotation angle in tenths of a degree (e.g., 450 = 45°).
     *
     * @example watermarkRotateAngle(-450)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('watermark_rotate_angle'))]
    public function watermarkRotateAngle(int $angle): static
    {
        $this->getBodyBag()->set('nativeWatermarkRotateAngle', $angle);

        return $this;
    }

    /**
     * Set the watermark font name.
     *
     * @example watermarkFontName('Liberation Sans')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_font_name'))]
    public function watermarkFontName(string $fontName): static
    {
        $this->getBodyBag()->set('nativeWatermarkFontName', $fontName);

        return $this;
    }

    /**
     * Set a tiled watermark text rendered across every page during PDF export.
     *
     * @example tiledWatermarkText('DRAFT')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('tiled_watermark_text'))]
    public function tiledWatermarkText(string $text): static
    {
        $this->getBodyBag()->set('nativeTiledWatermarkText', $text);

        return $this;
    }

    /**
     * Specify the initial view when opening the PDF.
     *
     * @example initialView(InitialView::Thumbnails)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('initial_view', enumClass: InitialView::class))]
    public function initialView(InitialView|null $initialView): static
    {
        if (!$initialView) {
            $this->getBodyBag()->unset('initialView');
        } else {
            $this->getBodyBag()->set('initialView', $initialView);
        }

        return $this;
    }

    /**
     * The page on which the PDF opens.
     *
     * @param int<1, max>|null $initialPage
     *
     * @example initialPage(3)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('initial_page', min: 1))]
    public function initialPage(int|null $initialPage): static
    {
        if (null === $initialPage) {
            $this->getBodyBag()->unset('initialPage');
        } else {
            ValidatorFactory::page($initialPage);
            $this->getBodyBag()->set('initialPage', $initialPage);
        }

        return $this;
    }

    /**
     * Initial magnification level.
     *
     * @example magnification(Magnification::FitVisible)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('magnification', enumClass: Magnification::class))]
    public function magnification(Magnification|null $magnification): static
    {
        if (!$magnification) {
            $this->getBodyBag()->unset('magnification');
        } else {
            $this->getBodyBag()->set('magnification', $magnification);
        }

        return $this;
    }

    /**
     * Initial zoom percentage when magnification is set to Magnification::UseZoomValue (4).
     *
     * @param int<1, 100>|null $zoom
     *
     * @example zoom(3)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('zoom', min: 1, max: 100))]
    public function zoom(int|null $zoom): static
    {
        if (null === $zoom) {
            $this->getBodyBag()->unset('zoom');
        } else {
            ValidatorFactory::zoom($zoom);
            $this->getBodyBag()->set('zoom', $zoom);
        }

        return $this;
    }

    /**
     * Page layout.
     *
     * @example pageLayout(PageLayout::SinglePage)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('page_layout', enumClass: PageLayout::class))]
    public function pageLayout(PageLayout|null $pageLayout): static
    {
        if (!$pageLayout) {
            $this->getBodyBag()->unset('pageLayout');
        } else {
            $this->getBodyBag()->set('pageLayout', $pageLayout);
        }

        return $this;
    }

    /**
     * Place the first page on the left when using two-column page layout.
     *
     * @example firstPageOnLeft() // is same as `->firstPageOnLeft(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('first_page_on_left'))]
    public function firstPageOnLeft(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('firstPageOnLeft');
        } else {
            $this->getBodyBag()->set('firstPageOnLeft', $bool);
        }

        return $this;
    }

    /**
     * Resize the viewer window to the size of the first page.
     *
     * @example resizeWindowToInitialPage() // is same as `->resizeWindowToInitialPage(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('resize_window_to_initial_page'))]
    public function resizeWindowToInitialPage(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('resizeWindowToInitialPage');
        } else {
            $this->getBodyBag()->set('resizeWindowToInitialPage', $bool);
        }

        return $this;
    }

    /**
     * Center the viewer window on the screen.
     *
     * @example centerWindow() // is same as `->centerWindow(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('center_window'))]
    public function centerWindow(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('centerWindow');
        } else {
            $this->getBodyBag()->set('centerWindow', $bool);
        }

        return $this;
    }

    /**
     * Open the PDF in full-screen mode.
     *
     * @example openInFullScreenMode() // is same as `->openInFullScreenMode(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('open_in_full_screen_mode'))]
    public function openInFullScreenMode(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('openInFullScreenMode');
        } else {
            $this->getBodyBag()->set('openInFullScreenMode', $bool);
        }

        return $this;
    }

    /**
     * Display the document title in the viewer title bar instead of the filename.
     *
     * @example displayPDFDocumentTitle() // is same as `->displayPDFDocumentTitle(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('display_pdf_document_title'))]
    public function displayPDFDocumentTitle(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('displayPDFDocumentTitle');
        } else {
            $this->getBodyBag()->set('displayPDFDocumentTitle', $bool);
        }

        return $this;
    }

    /**
     * Hide the viewer menu bar.
     *
     * @example hideViewerMenubar() // is same as `->hideViewerMenubar(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('hide_viewer_menubar'))]
    public function hideViewerMenubar(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('hideViewerMenubar');
        } else {
            $this->getBodyBag()->set('hideViewerMenubar', $bool);
        }

        return $this;
    }

    /**
     * Hide the viewer toolbar.
     *
     * @example hideViewerToolbar() // is same as `->hideViewerToolbar(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('hide_viewer_toolbar'))]
    public function hideViewerToolbar(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('hideViewerToolbar');
        } else {
            $this->getBodyBag()->set('hideViewerToolbar', $bool);
        }

        return $this;
    }

    /**
     * Hide the viewer window controls.
     *
     * @example hideViewerWindowControls() // is same as `->hideViewerWindowControls(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('hide_viewer_window_controls'))]
    public function hideViewerWindowControls(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('hideViewerWindowControls');
        } else {
            $this->getBodyBag()->set('hideViewerWindowControls', $bool);
        }

        return $this;
    }

    /**
     * Use transition effects when advancing slides in Impress presentations.
     *
     * @example useTransitionEffects() // is same as `->useTransitionEffects(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('use_transition_effects'))]
    public function useTransitionEffects(bool $bool = true): static
    {
        if (!$bool) {
            $this->getBodyBag()->unset('useTransitionEffects');
        } else {
            $this->getBodyBag()->set('useTransitionEffects', $bool);
        }

        return $this;
    }

    /**
     * Number of bookmark levels to show when opening the PDF. -1 shows all levels.
     *
     * @param int<-1, max>|null $openBookmarkLevels
     *
     * @example openBookmarkLevels(-1)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('open_bookmark_levels', min: -1))]
    public function openBookmarkLevels(int|null $openBookmarkLevels): static
    {
        if (null === $openBookmarkLevels) {
            $this->getBodyBag()->unset('openBookmarkLevels');
        } else {
            ValidatorFactory::bookmarkLevels($openBookmarkLevels);
            $this->getBodyBag()->set('openBookmarkLevels', $openBookmarkLevels);
        }

        return $this;
    }
}
